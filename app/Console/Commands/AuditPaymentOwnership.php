<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\AsaasPaymentOwnership;
use App\Services\AsaasService;
use App\Services\TenantApiService;
use App\Services\TenantBillingSync;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Confere no Asaas se cada pagamento gravado no master é mesmo do seu tenant.
 *
 * Até 01/10/2026 o webhook atribuía a um tenant pagamentos de outras
 * plataformas da mesma conta Asaas (ver docs/webhook-asaas.md). Este comando
 * acha essas linhas. Dry-run por padrão; com --force apaga do master só as
 * "não é nosso" e reenvia à loja o resumo de cobrança correto.
 *
 * A loja (soavel) não tem endpoint para apagar histórico de cobrança, então o
 * comando imprime o SQL a rodar no banco de cada loja.
 */
class AuditPaymentOwnership extends Command
{
    protected $signature = 'payments:audit-ownership
        {--force : Apaga do master os pagamentos que não são do tenant e reenvia o resumo de cobrança à loja}
        {--tenant= : Audita só este tenant (id)}
        {--sleep=500 : Pausa entre as chamadas à API do Asaas, em milissegundos}';

    protected $description = 'Confere no Asaas se cada pagamento gravado é do seu tenant (dry-run por padrão)';

    private const LABELS = [
        AsaasPaymentOwnership::OURS => 'nosso',
        AsaasPaymentOwnership::NOT_OURS => 'não é nosso',
        AsaasPaymentOwnership::UNKNOWN => 'incerto',
    ];

    public function handle(
        AsaasService $asaas,
        AsaasPaymentOwnership $ownership,
        TenantBillingSync $billingSync,
        TenantApiService $apiService,
    ): int {
        if (empty(config('services.asaas.api_key'))) {
            $this->error('ASAAS_API_KEY não configurada: sem ela não dá para ler os pagamentos no Asaas.');

            return self::FAILURE;
        }

        $payments = Payment::with('tenant')
            ->whereNotNull('asaas_payment_id')
            ->when($this->option('tenant'), fn ($query, $id) => $query->where('tenant_id', (int) $id))
            ->orderBy('tenant_id')
            ->orderBy('due_date')
            ->get();

        $sleepMs = max(0, (int) $this->option('sleep'));
        $rows = [];
        $foreign = collect();
        $counts = array_fill_keys(array_keys(self::LABELS), 0);

        foreach ($payments as $index => $payment) {
            if ($index > 0 && $sleepMs > 0) {
                Sleep::for($sleepMs)->milliseconds();
            }

            $asaasPayment = $asaas->getPayment($payment->asaas_payment_id);
            $result = $ownership->verdict($payment->tenant, $asaasPayment);

            if ($asaasPayment === null && str_contains((string) $payment->invoice_url, 'sandbox.asaas.com')) {
                $result['reason'] .= ' (fatura do sandbox)';
            }

            $counts[$result['verdict']]++;
            if ($result['verdict'] === AsaasPaymentOwnership::NOT_OURS) {
                $foreign->push($payment);
            }

            $rows[] = [
                "#{$payment->tenant_id} {$payment->tenant->name}",
                $payment->asaas_payment_id,
                'R$ '.number_format((float) $payment->amount, 2, ',', '.'),
                $payment->due_date?->format('d/m/Y') ?? '—',
                $asaasPayment['subscription'] ?? '—',
                $asaasPayment['externalReference'] ?? '—',
                self::LABELS[$result['verdict']],
                $result['reason'],
            ];
        }

        $this->table(
            ['Tenant', 'Pagamento', 'Valor', 'Vencimento', 'Assinatura (Asaas)', 'Referência (Asaas)', 'Veredito', 'Motivo'],
            $rows,
        );
        $this->line(sprintf(
            '%d pagamento(s) auditado(s): %d nosso(s), %d não é nosso, %d incerto(s).',
            $payments->count(),
            $counts[AsaasPaymentOwnership::OURS],
            $counts[AsaasPaymentOwnership::NOT_OURS],
            $counts[AsaasPaymentOwnership::UNKNOWN],
        ));

        if ($counts[AsaasPaymentOwnership::UNKNOWN] > 0) {
            $this->warn('Os "incerto" nunca são apagados: revise à mão.');
        }

        if ($foreign->isEmpty()) {
            $this->info('Nenhum pagamento de fora. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->printStoreCleanup($foreign);

        if (! $this->option('force')) {
            $this->warn('Dry-run: nada foi apagado. Rode de novo com --force para apagar do master os "não é nosso".');

            return self::SUCCESS;
        }

        foreach ($foreign->groupBy('tenant_id') as $tenantPayments) {
            $tenant = $tenantPayments->first()->tenant;
            $ids = $tenantPayments->pluck('asaas_payment_id')->all();

            Payment::whereKey($tenantPayments->pluck('id')->all())->delete();
            Log::info("payments:audit-ownership apagou pagamentos de outra plataforma do tenant {$tenant->id}", ['asaas_payment_ids' => $ids]);

            $this->resyncSummary($tenant, $billingSync, $apiService);
        }

        $this->info("{$foreign->count()} pagamento(s) apagado(s) do master e resumo de cobrança reenviado às lojas.");
        $this->warn('Falta o histórico nas lojas: rode o SQL acima no banco de cada loja.');

        return self::SUCCESS;
    }

    /**
     * O resumo de cobrança da loja pode estar mostrando a fatura de fora:
     * reenvia o certo ou, se não sobrou fatura, limpa.
     */
    private function resyncSummary(Tenant $tenant, TenantBillingSync $billingSync, TenantApiService $apiService): void
    {
        if ($billingSync->pushSummary($tenant)) {
            return;
        }

        $result = $apiService->updateBilling($tenant, [
            'billing_status' => null,
            'billing_amount' => null,
            'billing_due_date' => null,
            'billing_invoice_url' => null,
            'billing_type' => null,
            'billing_subscription_status' => $tenant->asaas_subscription_id ? 'active' : null,
        ]);

        if (! $result['success']) {
            $this->warn("Não consegui limpar o resumo de cobrança da loja {$tenant->name}.");
        }
    }

    /** A loja não tem endpoint de exclusão do histórico: imprime o SQL por loja. */
    private function printStoreCleanup(Collection $foreign): void
    {
        $this->newLine();
        $this->line('A loja não tem endpoint para apagar histórico de cobrança. No banco de cada loja');
        $this->line('(no VPS: mysql <banco>; confira DB_DATABASE no .env em /var/www/<site>):');

        foreach ($foreign->groupBy('tenant_id') as $tenantPayments) {
            $tenant = $tenantPayments->first()->tenant;
            [$safe, $unsafe] = $tenantPayments->pluck('asaas_payment_id')
                ->partition(fn (string $id) => preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1);

            $this->newLine();
            $this->line("-- #{$tenant->id} {$tenant->name} ({$tenant->domain})");

            if ($safe->isNotEmpty()) {
                $list = $safe->map(fn (string $id) => "'{$id}'")->implode(', ');
                $this->line("DELETE FROM billing_history WHERE asaas_payment_id IN ({$list});");
            }

            foreach ($unsafe as $id) {
                $this->warn('-- id fora do formato do Asaas, revisar à mão: '.json_encode($id, JSON_UNESCAPED_UNICODE));
            }
        }

        $this->newLine();
    }
}
