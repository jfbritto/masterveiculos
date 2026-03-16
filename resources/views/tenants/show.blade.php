@extends('layouts.app')

@section('title', $tenant->name)

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-pencil"></i> Editar
    </a>
    @if($tenant->status === 'active')
        <form id="form-suspend" action="{{ route('tenants.suspend', $tenant) }}" method="POST">
            @csrf
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="confirmAction('form-suspend', 'Suspender Tenant', 'O site ficará em modo manutenção para os visitantes.', 'Sim, suspender')">
                <i class="bi bi-pause-circle"></i> Suspender
            </button>
        </form>
    @elseif($tenant->isSuspended() || $tenant->status === 'inactive')
        <form action="{{ route('tenants.reactivate', $tenant) }}" method="POST">
            @csrf
            <button class="btn btn-sm btn-outline-success"><i class="bi bi-play-circle"></i> Reativar</button>
        </form>
    @endif
</div>
@endsection

@section('content')
<div class="row g-3 mb-3">
    {{-- Main info --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Tenant</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="text-muted small mb-1">Domínio</div>
                            <div class="fw-medium">
                                <i class="bi bi-globe2 me-1 text-muted"></i>{{ $tenant->domain }}
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small mb-1">Mensalidade</div>
                            <div class="fw-semibold fs-5" style="color: #166534;">
                                R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small mb-1">Status</div>
                            @if($tenant->status === 'active')
                                <span class="badge" style="background:#dcfce7;color:#166534;font-size:0.8rem;padding:0.4em 0.8em;">
                                    <i class="bi bi-check-circle me-1"></i>Ativo
                                </span>
                            @elseif($tenant->status === 'suspended')
                                <span class="badge" style="background:#fef3c7;color:#92400e;font-size:0.8rem;padding:0.4em 0.8em;">
                                    <i class="bi bi-pause-circle me-1"></i>Suspenso
                                </span>
                            @elseif($tenant->status === 'blocked')
                                <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:0.8rem;padding:0.4em 0.8em;">
                                    <i class="bi bi-x-circle me-1"></i>Bloqueado
                                </span>
                            @else
                                <span class="badge" style="background:#f1f5f9;color:#64748b;font-size:0.8rem;padding:0.4em 0.8em;">
                                    <i class="bi bi-dash-circle me-1"></i>Inativo
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted small mb-2 fw-semibold">
                                <i class="bi bi-person me-1"></i>Proprietário
                            </div>
                            <div class="mb-2">
                                <div class="fw-medium">{{ $tenant->owner_name }}</div>
                            </div>
                            <div class="mb-2 small">
                                <i class="bi bi-card-text me-1 text-muted"></i>{{ $tenant->owner_cpf_cnpj }}
                            </div>
                            <div class="mb-2 small">
                                <i class="bi bi-envelope me-1 text-muted"></i>{{ $tenant->owner_email }}
                            </div>
                            <div class="small">
                                <i class="bi bi-telephone me-1 text-muted"></i>{{ $tenant->owner_phone }}
                            </div>
                        </div>
                    </div>
                </div>
                @if($tenant->notes)
                    <div class="mt-3 p-2 px-3 rounded-2" style="background:#fffbeb;border:1px solid #fde68a;">
                        <small class="text-muted"><i class="bi bi-sticky me-1"></i><strong>Obs:</strong> {{ $tenant->notes }}</small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Stats</h6>
                <form action="{{ route('tenants.refresh-stats', $tenant) }}" method="POST">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary" title="Atualizar stats">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </form>
            </div>
            <div class="card-body">
                @if($tenant->latestStats)
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 rounded-2 text-center" style="background:#eef2ff;">
                                <div class="fw-bold" style="color:#6366f1;">{{ $tenant->latestStats->vehicles_count }}</div>
                                <div class="small text-muted">Veículos</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-2 text-center" style="background:#f0fdf4;">
                                <div class="fw-bold" style="color:#22c55e;">{{ $tenant->latestStats->leads_count }}</div>
                                <div class="small text-muted">Leads</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-2 text-center" style="background:#ecfeff;">
                                <div class="fw-bold" style="color:#06b6d4;">{{ $tenant->latestStats->sales_count }}</div>
                                <div class="small text-muted">Vendas</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-2 text-center" style="background:#f1f5f9;">
                                <div class="fw-bold" style="color:#64748b;">{{ $tenant->latestStats->disk_usage_mb }} MB</div>
                                <div class="small text-muted">Disco</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2 text-center">
                        <i class="bi bi-clock me-1"></i>{{ $tenant->latestStats->created_at->diffForHumans() }}
                    </div>
                @else
                    <div class="text-center text-muted py-2">
                        <i class="bi bi-bar-chart" style="font-size:1.5rem;"></i>
                        <p class="small mb-0 mt-1">Sem dados ainda.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Billing --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Cobrança Asaas</h6>
            </div>
            <div class="card-body">
                @if($tenant->asaas_subscription_id)
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div style="width:8px;height:8px;background:#22c55e;border-radius:50%;"></div>
                        <span class="fw-medium small" style="color:#166534;">Cobrança ativa</span>
                    </div>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-cash me-1"></i>R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}/mês
                    </div>
                    @if($tenant->asaas_customer_id)
                        <div class="small text-muted mb-1">
                            <i class="bi bi-person me-1"></i>{{ $tenant->asaas_customer_id }}
                        </div>
                    @endif
                    <div class="small text-muted mb-3">
                        <i class="bi bi-receipt me-1"></i>{{ $tenant->asaas_subscription_id }}
                    </div>
                    <form id="form-cancel-billing" action="{{ route('tenants.cancel-billing', $tenant) }}" method="POST">
                        @csrf
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="confirmAction('form-cancel-billing', 'Cancelar Cobrança', 'A assinatura será cancelada no Asaas.', 'Sim, cancelar')">
                            <i class="bi bi-x-circle"></i> Cancelar Cobrança
                        </button>
                    </form>
                @else
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div style="width:8px;height:8px;background:#94a3b8;border-radius:50%;"></div>
                        <span class="small text-muted">Cobrança não ativada</span>
                    </div>
                    <form action="{{ route('tenants.activate-billing', $tenant) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">Primeiro vencimento</label>
                            <input type="date" name="first_due_date" class="form-control form-control-sm" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success w-100">
                            <i class="bi bi-credit-card"></i> Ativar Cobrança
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- API Token --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-key me-2"></i>API Token</h6>
            </div>
            <div class="card-body">
                <div class="p-2 rounded-2 mb-2" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <code class="d-block text-break" style="font-size: 0.7rem; color: #475569;">{{ $tenant->api_token }}</code>
                </div>
                <form id="form-regenerate" action="{{ route('tenants.regenerate-token', $tenant) }}" method="POST">
                    @csrf
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="confirmAction('form-regenerate', 'Regenerar Token', 'O tenant perderá conexão até atualizar o token.', 'Sim, regenerar')">
                        <i class="bi bi-arrow-repeat"></i> Regenerar Token
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Danger zone --}}
@if($tenant->status !== 'inactive')
<div class="card mb-3" style="border-color:#fecaca;">
    <div class="card-header" style="background:#fef2f2;border-bottom-color:#fecaca;">
        <h6 class="mb-0" style="color:#991b1b;"><i class="bi bi-exclamation-triangle me-2"></i>Zona de Perigo</h6>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="small fw-medium mb-1">Desativar Tenant</div>
                <div class="small text-muted">Cancela a cobrança no Asaas, suspende o site e marca como inativo.</div>
            </div>
            <form id="form-deactivate" action="{{ route('tenants.deactivate', $tenant) }}" method="POST">
                @csrf
                <button type="button" class="btn btn-sm btn-danger" onclick="confirmAction('form-deactivate', 'Desativar Tenant', 'Isso irá cancelar a cobrança, suspender o site e desativar o tenant por completo. Esta ação pode ser revertida reativando manualmente.', 'Sim, desativar')">
                    <i class="bi bi-power"></i> Desativar
                </button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Payments table --}}
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Pagamentos</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Tipo</th>
                        <th>Pago em</th>
                        <th class="text-center">Fatura</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenant->payments as $payment)
                    <tr>
                        <td class="ps-3 fw-medium">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td>{{ $payment->due_date->format('d/m/Y') }}</td>
                        <td>
                            @if($payment->isPaid())
                                <span class="badge" style="background:#dcfce7;color:#166534;">
                                    <i class="bi bi-check-circle me-1"></i>Pago
                                </span>
                            @elseif($payment->isOverdue())
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">
                                    <i class="bi bi-exclamation-circle me-1"></i>Atrasado
                                </span>
                            @elseif($payment->status === 'pending')
                                <span class="badge" style="background:#fef3c7;color:#92400e;">
                                    <i class="bi bi-clock me-1"></i>Pendente
                                </span>
                            @elseif($payment->status === 'cancelled')
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">Cancelado</span>
                            @elseif($payment->status === 'refunded')
                                <span class="badge" style="background:#ecfeff;color:#0e7490;">Estornado</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $payment->billing_type ?? '-' }}</td>
                        <td class="small">{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-center">
                            @if($payment->invoice_url)
                                <a href="{{ $payment->invoice_url }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver fatura">
                                    <i class="bi bi-receipt"></i>
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state" style="padding:2rem;">
                                <i class="bi bi-receipt" style="font-size:2rem;"></i>
                                <p class="mb-0 small">Pagamentos serão gerados automaticamente pelo Asaas.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
