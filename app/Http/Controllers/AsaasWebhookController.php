<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\TenantApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    // Dias de atraso para cada ação
    private const SOFT_BLOCK_DAYS = 5;
    private const HARD_BLOCK_DAYS = 15;

    public function __construct(
        private TenantApiService $apiService
    ) {}

    public function handle(Request $request)
    {
        $event = $request->input('event');
        $payment = $request->input('payment');

        if (!$event || !$payment) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('Asaas webhook received', ['event' => $event, 'payment_id' => $payment['id'] ?? null]);

        match ($event) {
            'PAYMENT_CREATED' => $this->onPaymentCreated($payment),
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->onPaymentConfirmed($payment, $event),
            'PAYMENT_OVERDUE' => $this->onPaymentOverdue($payment),
            'PAYMENT_REFUNDED' => $this->onPaymentRefunded($payment),
            'PAYMENT_DELETED', 'PAYMENT_RESTORED' => null, // ignorar
            default => Log::info("Asaas webhook event not handled: {$event}"),
        };

        return response()->json(['status' => 'ok']);
    }

    private function onPaymentCreated(array $data): void
    {
        $tenant = $this->findTenantBySubscription($data);
        if (!$tenant) return;

        Payment::updateOrCreate(
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

        $this->pushBillingToTenant($tenant, 'pending', $data);
    }

    private function onPaymentConfirmed(array $data, string $event): void
    {
        $tenant = $this->findTenantBySubscription($data);

        $status = $event === 'PAYMENT_CONFIRMED' ? 'confirmed' : 'received';

        Payment::where('asaas_payment_id', $data['id'])->update([
            'status' => $status,
            'paid_at' => $data['paymentDate'] ?? $data['confirmedDate'] ?? now()->toDateString(),
        ]);

        if ($tenant) {
            $this->pushBillingToTenant($tenant, $status, $data);
        }

        // Se o tenant estava suspenso por inadimplência, reativar
        if ($tenant && $tenant->isSuspended()) {
            $this->apiService->reactivate($tenant);
            $tenant->update(['status' => 'active']);
            Log::info("Tenant {$tenant->name} reativado após pagamento.");
        }
    }

    private function onPaymentOverdue(array $data): void
    {
        $tenant = $this->findTenantBySubscription($data);
        if (!$tenant) return;

        Payment::where('asaas_payment_id', $data['id'])->update([
            'status' => 'overdue',
        ]);

        $this->pushBillingToTenant($tenant, 'overdue', $data);

        // Calcular dias de atraso
        $dueDate = \Carbon\Carbon::parse($data['dueDate']);
        $daysOverdue = $dueDate->diffInDays(now());

        if ($daysOverdue >= self::HARD_BLOCK_DAYS && $tenant->status !== 'blocked') {
            $this->apiService->suspend($tenant);
            $tenant->update(['status' => 'blocked']);
            Log::warning("Tenant {$tenant->name} BLOQUEADO - {$daysOverdue} dias de atraso.");
        } elseif ($daysOverdue >= self::SOFT_BLOCK_DAYS && $tenant->status === 'active') {
            $this->apiService->suspend($tenant);
            $tenant->update(['status' => 'suspended']);
            Log::warning("Tenant {$tenant->name} SUSPENSO - {$daysOverdue} dias de atraso.");
        }
    }

    private function onPaymentRefunded(array $data): void
    {
        Payment::where('asaas_payment_id', $data['id'])->update([
            'status' => 'refunded',
        ]);
    }

    private function pushBillingToTenant(Tenant $tenant, string $status, array $data): void
    {
        try {
            $this->apiService->updateBilling($tenant, [
                'billing_status' => $status,
                'billing_amount' => $data['value'] ?? $tenant->monthly_amount,
                'billing_due_date' => $data['dueDate'] ?? null,
                'billing_invoice_url' => $data['invoiceUrl'] ?? null,
                'billing_type' => $data['billingType'] ?? null,
                'billing_subscription_status' => 'active',
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to push billing to tenant {$tenant->name}: {$e->getMessage()}");
        }
    }

    private function findTenantBySubscription(array $data): ?Tenant
    {
        // Tenta pelo externalReference (tenant_ID)
        if (!empty($data['externalReference'])) {
            $id = str_replace('tenant_', '', $data['externalReference']);
            $tenant = Tenant::find($id);
            if ($tenant) return $tenant;
        }

        // Tenta pelo subscription ID
        if (!empty($data['subscription'])) {
            return Tenant::where('asaas_subscription_id', $data['subscription'])->first();
        }

        Log::warning('Asaas webhook: tenant not found', $data);
        return null;
    }
}
