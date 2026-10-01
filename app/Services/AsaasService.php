<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key', '');
        $this->baseUrl = config('services.asaas.sandbox', true)
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
    }

    public function createCustomerAndSubscription(Tenant $tenant, string $firstDueDate): void
    {
        // Se já tem cliente no Asaas, reutiliza
        $customerId = $tenant->asaas_customer_id;

        if (!$customerId) {
            $customer = $this->createCustomer($tenant);

            if (!$customer) {
                return;
            }

            $customerId = $customer['id'];
            $tenant->update(['asaas_customer_id' => $customerId]);
        }

        $subscription = $this->createSubscription($tenant, $customerId, $firstDueDate);

        if ($subscription) {
            $tenant->update(['asaas_subscription_id' => $subscription['id']]);
        }
    }

    public function createCustomer(Tenant $tenant): ?array
    {
        $cpfCnpj = preg_replace('/\D/', '', $tenant->owner_cpf_cnpj);

        $response = $this->request('POST', '/customers', [
            'name' => $tenant->owner_name,
            'email' => $tenant->owner_email,
            'phone' => preg_replace('/\D/', '', $tenant->owner_phone),
            'cpfCnpj' => $cpfCnpj,
            'externalReference' => 'tenant_' . $tenant->id,
        ]);

        return $response;
    }

    public function createSubscription(Tenant $tenant, string $customerId, string $firstDueDate): ?array
    {
        $response = $this->request('POST', '/subscriptions', [
            'customer' => $customerId,
            'billingType' => 'UNDEFINED',
            'value' => (float) $tenant->monthly_amount,
            'nextDueDate' => $firstDueDate,
            'cycle' => 'MONTHLY',
            'description' => "Mensalidade {$tenant->name} - HelpFlux Veículos",
            'externalReference' => 'tenant_' . $tenant->id,
        ]);

        return $response;
    }

    public function cancelSubscription(string $subscriptionId): ?array
    {
        return $this->request('DELETE', "/subscriptions/{$subscriptionId}");
    }

    /** GET /v3/payments/{id}. Null se não achar ou se a API falhar (o erro vai para o log). */
    public function getPayment(string $paymentId): ?array
    {
        // O id vem do banco, gravado por eventos do webhook: nunca confiar nele como caminho.
        return $this->request('GET', '/payments/'.rawurlencode($paymentId));
    }

    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Asaas: API key não configurada.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->{strtolower($method)}($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Asaas API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Asaas API exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
