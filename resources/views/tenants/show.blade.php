@extends('layouts.app')

@section('title', $tenant->name)

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Informações do Tenant</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                    @if($tenant->status === 'active')
                        <form id="form-suspend" action="{{ route('tenants.suspend', $tenant) }}" method="POST">
                            @csrf
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="confirmAction('form-suspend', 'Suspender Tenant', 'O site ficará em modo manutenção para os visitantes.', 'Sim, suspender')"><i class="bi bi-pause-circle"></i> Suspender</button>
                        </form>
                    @elseif($tenant->isSuspended() || $tenant->status === 'inactive')
                        <form action="{{ route('tenants.reactivate', $tenant) }}" method="POST">
                            @csrf
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-play-circle"></i> Reativar</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Domínio:</strong> {{ $tenant->domain }}</p>
                        <p><strong>Mensalidade:</strong> R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}</p>
                        <p><strong>Status:</strong>
                            @if($tenant->status === 'active')
                                <span class="badge bg-success">Ativo</span>
                            @elseif($tenant->status === 'suspended')
                                <span class="badge bg-warning">Suspenso</span>
                            @elseif($tenant->status === 'blocked')
                                <span class="badge bg-danger">Bloqueado</span>
                            @else
                                <span class="badge bg-secondary">Inativo</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Proprietário:</strong> {{ $tenant->owner_name }}</p>
                        <p><strong>CPF/CNPJ:</strong> {{ $tenant->owner_cpf_cnpj }}</p>
                        <p><strong>Email:</strong> {{ $tenant->owner_email }}</p>
                        <p><strong>Telefone:</strong> {{ $tenant->owner_phone }}</p>
                    </div>
                </div>
                @if($tenant->notes)
                    <p class="mt-2"><strong>Obs:</strong> {{ $tenant->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Stats</h6>
                <form action="{{ route('tenants.refresh-stats', $tenant) }}" method="POST">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></button>
                </form>
            </div>
            <div class="card-body">
                @if($tenant->latestStats)
                    <p><strong>Veículos:</strong> {{ $tenant->latestStats->vehicles_count }}</p>
                    <p><strong>Leads:</strong> {{ $tenant->latestStats->leads_count }}</p>
                    <p><strong>Vendas:</strong> {{ $tenant->latestStats->sales_count }}</p>
                    <p><strong>Disco:</strong> {{ $tenant->latestStats->disk_usage_mb }} MB</p>
                    <p class="text-muted small">Atualizado: {{ $tenant->latestStats->created_at->diffForHumans() }}</p>
                @else
                    <p class="text-muted">Sem dados ainda.</p>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Cobrança Asaas</h6></div>
            <div class="card-body">
                @if($tenant->asaas_subscription_id)
                    <p class="text-success mb-2"><i class="bi bi-check-circle"></i> Cobrança ativa</p>
                    <p class="small text-muted mb-2">R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}/mês</p>
                    @if($tenant->asaas_customer_id)
                        <p class="small text-muted mb-2">Cliente: {{ $tenant->asaas_customer_id }}</p>
                    @endif
                    <p class="small text-muted mb-3">Assinatura: {{ $tenant->asaas_subscription_id }}</p>
                    <form id="form-cancel-billing" action="{{ route('tenants.cancel-billing', $tenant) }}" method="POST">
                        @csrf
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmAction('form-cancel-billing', 'Cancelar Cobrança', 'A assinatura será cancelada no Asaas.', 'Sim, cancelar')">
                            <i class="bi bi-x-circle"></i> Cancelar Cobrança
                        </button>
                    </form>
                @else
                    <p class="text-muted mb-3"><i class="bi bi-clock"></i> Cobrança não ativada</p>
                    <form action="{{ route('tenants.activate-billing', $tenant) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">Primeiro vencimento</label>
                            <input type="date" name="first_due_date" class="form-control form-control-sm" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-credit-card"></i> Ativar Cobrança
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">API Token</h6></div>
            <div class="card-body">
                <code class="d-block text-break mb-2" style="font-size: 0.75rem;">{{ $tenant->api_token }}</code>
                <form id="form-regenerate" action="{{ route('tenants.regenerate-token', $tenant) }}" method="POST">
                    @csrf
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmAction('form-regenerate', 'Regenerar Token', 'O tenant perderá conexão até atualizar o token.', 'Sim, regenerar')"><i class="bi bi-key"></i> Regenerar</button>
                </form>
            </div>
        </div>
    </div>

        @if($tenant->status !== 'inactive')
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10"><h6 class="mb-0 text-danger">Zona de Perigo</h6></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Desativar o tenant cancela a cobrança no Asaas, suspende o site e marca como inativo.</p>
                <form id="form-deactivate" action="{{ route('tenants.deactivate', $tenant) }}" method="POST">
                    @csrf
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmAction('form-deactivate', 'Desativar Tenant', 'Isso irá cancelar a cobrança, suspender o site e desativar o tenant por completo. Esta ação pode ser revertida reativando manualmente.', 'Sim, desativar')">
                        <i class="bi bi-power"></i> Desativar Tenant
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Pagamentos</h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Tipo</th>
                    <th>Pago em</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenant->payments as $payment)
                <tr>
                    <td>R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                    <td>{{ $payment->due_date->format('d/m/Y') }}</td>
                    <td>
                        @if($payment->isPaid())
                            <span class="badge bg-success">Pago</span>
                        @elseif($payment->isOverdue())
                            <span class="badge bg-danger">Atrasado</span>
                        @elseif($payment->status === 'pending')
                            <span class="badge bg-warning">Pendente</span>
                        @elseif($payment->status === 'cancelled')
                            <span class="badge bg-secondary">Cancelado</span>
                        @elseif($payment->status === 'refunded')
                            <span class="badge bg-info">Estornado</span>
                        @endif
                    </td>
                    <td>{{ $payment->billing_type ?? '-' }}</td>
                    <td>{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        @if($payment->invoice_url)
                            <a href="{{ $payment->invoice_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-receipt"></i>
                            </a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">Pagamentos serão gerados automaticamente pelo Asaas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
