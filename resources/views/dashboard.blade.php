@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
{{-- Banner de sync --}}
<div id="sync-banner" class="alert alert-info d-flex align-items-center mb-4 d-none">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    <span>Sincronizando dados dos tenants...</span>
</div>
<div id="sync-done" class="alert alert-success d-none mb-4">
    <i class="bi bi-check-circle"></i> <span id="sync-message"></span>
</div>
<div id="sync-error" class="alert alert-warning d-none mb-4">
    <i class="bi bi-exclamation-triangle"></i> <span id="sync-error-message"></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card blue">
            <div class="card-body">
                <div class="text-muted small">Tenants Ativos</div>
                <div class="fs-3 fw-bold" id="stat-active">{{ $activeTenants }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card green">
            <div class="card-body">
                <div class="text-muted small">Online Agora</div>
                <div class="fs-3 fw-bold" id="stat-online">{{ $onlineTenants }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card yellow">
            <div class="card-body">
                <div class="text-muted small">Suspensos</div>
                <div class="fs-3 fw-bold" id="stat-suspended">{{ $suspendedTenants }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card red">
            <div class="card-body">
                <div class="text-muted small">Pagamentos Atrasados</div>
                <div class="fs-3 fw-bold" id="stat-overdue">{{ $overduePayments }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Total Veículos</div>
                <div class="fs-2 fw-bold text-primary" id="stat-vehicles">{{ number_format($totalVehicles) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Total Leads</div>
                <div class="fs-2 fw-bold text-success" id="stat-leads">{{ number_format($totalLeads) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Total Vendas</div>
                <div class="fs-2 fw-bold text-info" id="stat-sales">{{ number_format($totalSales) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Tenants</h6>
        <div class="d-flex gap-2">
            <button id="btn-sync" class="btn btn-sm btn-outline-secondary" onclick="syncTenants()">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <a href="{{ route('tenants.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus"></i> Novo Tenant
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Domínio</th>
                    <th>Status</th>
                    <th>Veículos</th>
                    <th>Leads</th>
                    <th>Heartbeat</th>
                </tr>
            </thead>
            <tbody id="tenants-table">
                @forelse($tenants as $tenant)
                <tr data-tenant-id="{{ $tenant->id }}">
                    <td>
                        <a href="{{ route('tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                    </td>
                    <td><small class="text-muted">{{ $tenant->domain }}</small></td>
                    <td>
                        @if($tenant->status === 'active')
                            <span class="badge bg-success">Ativo</span>
                        @elseif($tenant->status === 'suspended')
                            <span class="badge bg-warning">Suspenso</span>
                        @elseif($tenant->status === 'blocked')
                            <span class="badge bg-danger">Bloqueado</span>
                        @else
                            <span class="badge bg-secondary">Inativo</span>
                        @endif
                    </td>
                    <td class="td-vehicles">{{ $tenant->latestStats?->vehicles_count ?? '-' }}</td>
                    <td class="td-leads">{{ $tenant->latestStats?->leads_count ?? '-' }}</td>
                    <td class="td-heartbeat">
                        @if($tenant->isOnline())
                            <span class="badge badge-online text-white">Online</span>
                        @else
                            <span class="badge badge-offline text-white">Offline</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum tenant cadastrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

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
                hb.innerHTML = '<span class="badge badge-online text-white">Online</span>';
            } else {
                hb.innerHTML = '<span class="badge badge-offline text-white">Offline</span>';
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
        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Atualizar';
    });
}

@if($tenants->count() > 0)
    syncTenants();
@endif
</script>
@endsection
