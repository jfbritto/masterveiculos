<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class TenantApiService
{
    public function health(Tenant $tenant): array
    {
        return $this->request($tenant, 'GET', '/api/master/health');
    }

    public function stats(Tenant $tenant): array
    {
        return $this->request($tenant, 'GET', '/api/master/stats');
    }

    public function suspend(Tenant $tenant): array
    {
        return $this->request($tenant, 'GET', '/api/master/suspend');
    }

    public function reactivate(Tenant $tenant): array
    {
        return $this->request($tenant, 'GET', '/api/master/reactivate');
    }

    public function updateConfig(Tenant $tenant, array $config): array
    {
        return $this->request($tenant, 'GET', '/api/master/config', $config);
    }

    public function updateBilling(Tenant $tenant, array $data): array
    {
        return $this->request($tenant, 'GET', '/api/master/billing', $data);
    }

    public function updateBillingHistory(Tenant $tenant, array $data): array
    {
        return $this->request($tenant, 'GET', '/api/master/billing-history', $data);
    }

    private function request(Tenant $tenant, string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($tenant->domain, '/') . $endpoint;

        try {
            $response = Http::withHeaders([
                'X-Master-Token' => $tenant->api_token,
                'Accept' => 'application/json',
            ])->timeout(10)->{strtolower($method)}($url, $data);

            return [
                'success' => $response->successful(),
                'data' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
