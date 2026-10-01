<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Empurra para a loja (soavel) o resumo de cobrança e o histórico de faturas.
 *
 * Extraído do AsaasWebhookController sem mudança de comportamento, para ser
 * reaproveitado pelo payments:audit-ownership.
 */
class TenantBillingSync
{
    public function __construct(
        private TenantApiService $apiService
    ) {}

    /**
     * Resumo de cobrança: a fatura em aberto mais próxima ou, se todas foram
     * pagas, a última paga. Retorna false quando o tenant não tem nenhuma das
     * duas e nada foi enviado.
     */
    public function pushSummary(Tenant $tenant): bool
    {
        try {
            // Busca a fatura pendente mais próxima do tenant
            $nextPayment = Payment::where('tenant_id', $tenant->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->orderBy('due_date', 'asc')
                ->first();

            if ($nextPayment) {
                $result = $this->apiService->updateBilling($tenant, [
                    'billing_status' => $nextPayment->status,
                    'billing_amount' => $nextPayment->amount,
                    'billing_due_date' => $nextPayment->due_date,
                    'billing_invoice_url' => $nextPayment->invoice_url,
                    'billing_type' => $nextPayment->billing_type,
                    'billing_subscription_status' => 'active',
                ]);
            } else {
                // Todas as faturas pagas — busca a última paga para mostrar status
                $lastPaid = Payment::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['confirmed', 'received'])
                    ->orderBy('due_date', 'desc')
                    ->first();

                if ($lastPaid) {
                    $result = $this->apiService->updateBilling($tenant, [
                        'billing_status' => $lastPaid->status,
                        'billing_amount' => $lastPaid->amount,
                        'billing_due_date' => $lastPaid->due_date,
                        'billing_invoice_url' => null,
                        'billing_type' => $lastPaid->billing_type,
                        'billing_subscription_status' => 'active',
                    ]);
                }
            }

            if (isset($result) && ! $result['success']) {
                Log::warning("Push billing to tenant {$tenant->name} failed", $result);
            } elseif (isset($result)) {
                Log::info("Push billing to tenant {$tenant->name} succeeded");
            }

            return isset($result);
        } catch (\Exception $e) {
            Log::warning("Failed to push billing to tenant {$tenant->name}: {$e->getMessage()}");

            return false;
        }
    }

    public function pushPaymentRecord(Tenant $tenant, Payment $payment): void
    {
        try {
            $result = $this->apiService->updateBillingHistory($tenant, [
                'asaas_payment_id' => $payment->asaas_payment_id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'due_date' => $payment->due_date instanceof Carbon ? $payment->due_date->toDateString() : $payment->due_date,
                'paid_at' => $payment->paid_at ? ($payment->paid_at instanceof Carbon ? $payment->paid_at->toDateString() : $payment->paid_at) : null,
                'billing_type' => $payment->billing_type,
                'invoice_url' => $payment->invoice_url,
            ]);

            if (! $result['success']) {
                Log::warning("Push payment record to tenant {$tenant->name} failed", $result);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to push payment record to tenant {$tenant->name}: {$e->getMessage()}");
        }
    }
}
