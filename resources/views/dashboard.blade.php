@extends('layouts.app')

@section('title', 'Dashboard')

@section('actions')
<div class="d-flex gap-2">
    <button id="btn-sync" class="btn btn-sm btn-outline-secondary" onclick="syncTenants()">
        <i class="bi bi-arrow-clockwise"></i> Sincronizar
    </button>
    <a href="{{ route('tenants.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Novo Tenant
    </a>
</div>
@endsection

@section('content')
{{-- Sync banners --}}
<div id="sync-banner" class="alert alert-info d-flex align-items-center mb-3 d-none">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    <span>Sincronizando dados dos tenants...</span>
</div>
<div id="sync-done" class="alert alert-success d-none mb-3">
    <i class="bi bi-check-circle-fill me-1"></i> <span id="sync-message"></span>
</div>
<div id="sync-error" class="alert alert-warning d-none mb-3">
    <i class="bi bi-exclamation-triangle-fill me-1"></i> <span id="sync-error-message"></span>
</div>

{{-- Stat cards row 1 --}}
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="card stat-card blue">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-buildings"></i></div>
                <div>
                    <div class="stat-value" id="stat-active">{{ $activeTenants }}</div>
                    <div class="stat-label">Tenants Ativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card green">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-wifi"></i></div>
                <div>
                    <div class="stat-value" id="stat-online">{{ $onlineTenants }}</div>
                    <div class="stat-label">Online Agora</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card yellow">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-pause-circle"></i></div>
                <div>
                    <div class="stat-value" id="stat-suspended">{{ $suspendedTenants }}</div>
                    <div class="stat-label">Suspensos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card red">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-value" id="stat-overdue">{{ $overduePayments }}</div>
                    <div class="stat-label">Pgtos Atrasados</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stat cards row 2 --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card blue">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-car-front"></i></div>
                <div>
                    <div class="stat-value" id="stat-vehicles">{{ number_format($totalVehicles) }}</div>
                    <div class="stat-label">Total Veículos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card green">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-value" id="stat-leads">{{ number_format($totalLeads) }}</div>
                    <div class="stat-label">Total Leads</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card info">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="stat-value" id="stat-sales">{{ number_format($totalSales) }}</div>
                    <div class="stat-label">Total Vendas</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Tenants table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-buildings me-2"></i>Tenants</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Nome</th>
                        <th>Domínio</th>
                        <th>Status</th>
                        <th class="text-center">Veículos</th>
                        <th class="text-center">Leads</th>
                        <th class="text-center">Heartbeat</th>
                    </tr>
                </thead>
                <tbody id="tenants-table">
                    @forelse($tenants as $tenant)
                    <tr data-tenant-id="{{ $tenant->id }}">
                        <td class="ps-3">
                            <a href="{{ route('tenants.show', $tenant) }}" class="fw-medium text-decoration-none">{{ $tenant->name }}</a>
                        </td>
                        <td><span class="text-muted small">{{ $tenant->domain }}</span></td>
                        <td>
                            @if($tenant->status === 'active')
                                <span class="badge" style="background:#dcfce7;color:#166534;">Ativo</span>
                            @elseif($tenant->status === 'suspended')
                                <span class="badge" style="background:#fef3c7;color:#92400e;">Suspenso</span>
                            @elseif($tenant->status === 'blocked')
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Bloqueado</span>
                            @else
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">Inativo</span>
                            @endif
                        </td>
                        <td class="text-center td-vehicles">{{ $tenant->latestStats?->vehicles_count ?? '-' }}</td>
                        <td class="text-center td-leads">{{ $tenant->latestStats?->leads_count ?? '-' }}</td>
                        <td class="text-center td-heartbeat">
                            @if($tenant->isOnline())
                                <span class="badge badge-online"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Online</span>
                            @else
                                <span class="badge badge-offline"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Offline</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-buildings"></i>
                                <p class="mb-0">Nenhum tenant cadastrado.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
var _syncing = false;

function formatNumber(n) {
    return new Intl.NumberFormat('pt-BR').format(n);
}

function syncTenants() {
    if (_syncing) return;
    _syncing = true;

    var banner = document.getElementById('sync-banner');
    var done = document.getElementById('sync-done');
    var errorDiv = document.getElementById('sync-error');
    var btn = document.getElementById('btn-sync');

    banner.classList.remove('d-none');
    done.classList.add('d-none');
    errorDiv.classList.add('d-none');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';

    fetch('{{ route("sync.tenants") }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('stat-active').textContent = data.totals.active;
        document.getElementById('stat-online').textContent = data.totals.online;
        document.getElementById('stat-suspended').textContent = data.totals.suspended;
        document.getElementById('stat-overdue').textContent = data.totals.overdue_payments;
        document.getElementById('stat-vehicles').textContent = formatNumber(data.totals.vehicles);
        document.getElementById('stat-leads').textContent = formatNumber(data.totals.leads);
        document.getElementById('stat-sales').textContent = formatNumber(data.totals.sales);

        data.tenants.forEach(function(t) {
            var row = document.querySelector('tr[data-tenant-id="' + t.id + '"]');
            if (!row) return;

            if (t.stats) {
                row.querySelector('.td-vehicles').textContent = t.stats.vehicles_count || '-';
                row.querySelector('.td-leads').textContent = t.stats.leads_count || '-';
            }

            var hb = row.querySelector('.td-heartbeat');
            if (t.online) {
                hb.innerHTML = '<span class="badge badge-online"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Online</span>';
            } else {
                hb.innerHTML = '<span class="badge badge-offline"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Offline</span>';
            }
        });

        var online = data.tenants.filter(function(t) { return t.online; }).length;
        var offline = data.tenants.filter(function(t) { return !t.online; }).length;
        var msg = 'Dados atualizados. ' + online + ' online';
        if (offline > 0) msg += ', ' + offline + ' offline';

        banner.classList.add('d-none');
        document.getElementById('sync-message').textContent = msg;
        done.classList.remove('d-none');

        setTimeout(function() { done.classList.add('d-none'); }, 5000);
    })
    .catch(function(err) {
        banner.classList.add('d-none');
        document.getElementById('sync-error-message').textContent = 'Erro ao sincronizar: ' + err.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(function() {
        _syncing = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sincronizar';
    });
}

@if($tenants->count() > 0)
    syncTenants();
@endif
</script>
@endpush
@endsection
