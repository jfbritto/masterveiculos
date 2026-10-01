<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\AsaasPaymentOwnership;
use App\Services\TenantApiService;
use App\Services\TenantBillingSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __construct(
        private TenantApiService $apiService,
        private TenantBillingSync $billingSync,
        private AsaasPaymentOwnership $ownership,
    ) {}

    public function handle(Request $request)
    {
        // O token já foi conferido (VerifyAsaasWebhookToken). Daqui em diante,
        // nada de 4xx para um evento que o master não trata: o Asaas conta como
        // falha e, depois de várias, interrompe a fila inteira do webhook.
        //
        // A única exceção é um corpo que nem JSON é. O Asaas sempre manda JSON,
        // então isso só vem de um cliente manual ou quebrado com o token, e aí
        // um erro explícito ajuda mais que um 200 silencioso.
        json_decode($request->getContent());
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Asaas webhook: corpo não é JSON; recusado.', ['bytes' => strlen($request->getContent())]);

            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        // json() lê o corpo mesmo se o Content-Type não for application/json.
        $event = $request->json('event');
        $payment = $request->json('payment');

        if (! is_string($event) || $event === '' || ! is_array($payment) || empty($payment['id'])) {
            Log::info('Asaas webhook: evento sem pagamento (outro tipo de evento); ignorado', [
                'event' => is_string($event) ? $event : null,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        // A conta do Asaas é compartilhada com outras plataformas: a maioria
        // dos eventos que chegam aqui NÃO é do master. Esses respondem 200 (o
        // Asaas não deve reenviar) e não tocam em nada.
        $match = $this->ownership->match($payment);
        $tenant = $match['tenant'];

        if (! $tenant) {
            $context = [
                'event' => $event,
                'payment_id' => $payment['id'],
                'externalReference' => $payment['externalReference'] ?? null,
                'subscription' => $payment['subscription'] ?? null,
                'reason' => $match['reason'],
            ];

            if ($match['conflict']) {
                Log::warning('Asaas webhook: referência e assinatura de tenants diferentes; evento ignorado', $context);
            } else {
                Log::info('Asaas webhook: pagamento não é do master (outra plataforma da conta); ignorado', $context);
            }

            return response()->json(['status' => 'ignored']);
        }

        Log::info('Asaas webhook received', ['event' => $event, 'payment_id' => $payment['id'], 'tenant_id' => $tenant->id]);

        match ($event) {
            'PAYMENT_CREATED' => $this->onPaymentCreated($tenant, $payment),
            'PAYMENT_UPDATED' => $this->onPaymentUpdated($tenant, $payment),
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->onPaymentConfirmed($tenant, $payment, $event),
            'PAYMENT_OVERDUE' => $this->onPaymentOverdue($tenant, $payment),
            'PAYMENT_REFUNDED' => $this->onPaymentRefunded($tenant, $payment),
            'PAYMENT_DELETED', 'PAYMENT_RESTORED' => null, // ignorar
            default => Log::info("Asaas webhook event not handled: {$event}"),
        };

        return response()->json(['status' => 'ok']);
    }

    private function onPaymentCreated(Tenant $tenant, array $data): void
    {
        $payment = Payment::updateOrCreate(
            ['asaas_payment_id' => $data['id']],
            [
                'tenant_id' => $tenant->id,
                'amount' => $data['value'],
                'status' => 'pending',
                'due_date' => $data['dueDate'],
                'billing_type' => $data['billingType'] ?? null,
                'invoice_url' => $data['invoiceUrl'] ?? null,
            ]
        );

        $this->billingSync->pushSummary($tenant);
        $this->billingSync->pushPaymentRecord($tenant, $payment);
    }

    private function onPaymentUpdated(Tenant $tenant, array $data): void
    {
        $payment = $this->tenantPayment($tenant, $data)->first();
        if (! $payment) {
            return;
        }

        $payment->update([
            'billing_type' => $data['billingType'] ?? $payment->billing_type,
            'invoice_url' => $data['invoiceUrl'] ?? $payment->invoice_url,
        ]);

        $this->billingSync->pushSummary($tenant);
        $this->billingSync->pushPaymentRecord($tenant, $payment->fresh());
    }

    private function onPaymentConfirmed(Tenant $tenant, array $data, string $event): void
    {
        $status = $event === 'PAYMENT_CONFIRMED' ? 'confirmed' : 'received';

        $this->tenantPayment($tenant, $data)->update([
            'status' => $status,
            'paid_at' => $data['paymentDate'] ?? $data['confirmedDate'] ?? now()->toDateString(),
        ]);

        $this->billingSync->pushSummary($tenant);
        $payment = $this->tenantPayment($tenant, $data)->first();
        if ($payment) {
            $this->billingSync->pushPaymentRecord($tenant, $payment);
        }

        // Se o tenant estava suspenso por inadimplência, reativar
        if ($tenant->isSuspended()) {
            $this->apiService->reactivate($tenant);
            $tenant->update(['status' => 'active']);
            Log::info("Tenant {$tenant->name} reativado após pagamento.");
        }
    }

    private function onPaymentOverdue(Tenant $tenant, array $data): void
    {
        $this->tenantPayment($tenant, $data)->update([
            'status' => 'overdue',
        ]);

        $this->billingSync->pushSummary($tenant);
        $payment = $this->tenantPayment($tenant, $data)->first();
        if ($payment) {
            $this->billingSync->pushPaymentRecord($tenant, $payment);
        }
    }

    private function onPaymentRefunded(Tenant $tenant, array $data): void
    {
        $this->tenantPayment($tenant, $data)->update([
            'status' => 'refunded',
        ]);

        $payment = $this->tenantPayment($tenant, $data)->first();
        if ($payment) {
            $this->billingSync->pushPaymentRecord($tenant, $payment);
        }
    }

    /** A linha deste pagamento, só se for do tenant do evento. */
    private function tenantPayment(Tenant $tenant, array $data): Builder
    {
        return Payment::where('tenant_id', $tenant->id)
            ->where('asaas_payment_id', (string) $data['id']);
    }
}
