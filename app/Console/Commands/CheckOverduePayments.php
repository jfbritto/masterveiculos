<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\TenantApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverduePayments extends Command
{
    protected $signature = 'billing:check-overdue';
    protected $description = 'Verifica faturas vencidas e suspende/bloqueia tenants inadimplentes';

    private const SOFT_BLOCK_DAYS = 5;
    private const HARD_BLOCK_DAYS = 15;

    public function handle(TenantApiService $apiService): int
    {
        $this->info('Verificando faturas vencidas...');

        // Busca todos os tenants com faturas overdue
        $tenantIds = Payment::where('status', 'overdue')
            ->distinct()
            ->pluck('tenant_id');

        $affected = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant || $tenant->status === 'inactive') {
                continue;
            }

            // Pega a fatura vencida mais antiga do tenant
            $oldestOverdue = Payment::where('tenant_id', $tenantId)
                ->where('status', 'overdue')
                ->orderBy('due_date', 'asc')
                ->first();

            if (!$oldestOverdue) {
                continue;
            }

            $dueDate = \Carbon\Carbon::parse($oldestOverdue->due_date)->startOfDay();
            $daysOverdue = (int) $dueDate->diffInDays(now()->startOfDay(), false);

            if ($daysOverdue < self::SOFT_BLOCK_DAYS) {
                continue;
            }

            // Hard block: 15+ dias
            if ($daysOverdue >= self::HARD_BLOCK_DAYS && $tenant->status !== 'blocked') {
                $apiService->suspend($tenant);
                $tenant->update(['status' => 'blocked']);
                Log::warning("Tenant {$tenant->name} BLOQUEADO - {$daysOverdue} dias de atraso.");
                $this->warn("BLOQUEADO: {$tenant->name} - {$daysOverdue} dias de atraso");
                $affected++;
            }
            // Soft block: 5+ dias
            elseif ($daysOverdue >= self::SOFT_BLOCK_DAYS && $tenant->status === 'active') {
                $apiService->suspend($tenant);
                $tenant->update(['status' => 'suspended']);
                Log::warning("Tenant {$tenant->name} SUSPENSO - {$daysOverdue} dias de atraso.");
                $this->warn("SUSPENSO: {$tenant->name} - {$daysOverdue} dias de atraso");
                $affected++;
            }
        }

        $this->info("Concluído. {$affected} tenant(s) afetado(s).");

        return self::SUCCESS;
    }
}
