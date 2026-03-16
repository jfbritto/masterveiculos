<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Payment;
use App\Services\TenantApiService;

class SyncController extends Controller
{
    public function __construct(
        private TenantApiService $apiService
    ) {}

    public function syncAll()
    {
        $tenants = Tenant::all();
        $results = [];

        foreach ($tenants as $tenant) {
            $health = $this->apiService->health($tenant);
            $stats = $this->apiService->stats($tenant);

            $tenantResult = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'online' => $health['success'],
            ];

            if ($stats['success'] && $stats['data']) {
                $tenant->stats()->create($stats['data']);
                $tenant->update(['last_heartbeat_at' => now()]);
                $tenantResult['stats'] = $stats['data'];
            } else {
                $tenantResult['stats'] = null;
                $tenantResult['error'] = $stats['error'] ?? 'Sem resposta';
            }

            $results[] = $tenantResult;
        }

        // Recalcular totais
        $tenants = Tenant::with('latestStats')->get();

        return response()->json([
            'tenants' => $results,
            'totals' => [
                'vehicles' => $tenants->sum(fn ($t) => $t->latestStats?->vehicles_count ?? 0),
                'leads' => $tenants->sum(fn ($t) => $t->latestStats?->leads_count ?? 0),
                'sales' => $tenants->sum(fn ($t) => $t->latestStats?->sales_count ?? 0),
                'active' => $tenants->where('status', 'active')->count(),
                'suspended' => $tenants->whereIn('status', ['suspended', 'blocked'])->count(),
                'online' => collect($results)->where('online', true)->count(),
                'overdue_payments' => Payment::where('status', 'pending')->where('due_date', '<', now())->count(),
            ],
        ]);
    }
}
