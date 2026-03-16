@extends('layouts.app')

@section('title', 'Tenants')

@section('actions')
<a href="{{ route('tenants.create') }}" class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg"></i> Novo Tenant
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-buildings me-2"></i>Todos os Tenants</h6>
        <span class="text-muted small">{{ $tenants->count() }} {{ $tenants->count() === 1 ? 'registro' : 'registros' }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Nome</th>
                        <th>Domínio</th>
                        <th>Proprietário</th>
                        <th>Status</th>
                        <th class="text-end">Mensalidade</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('tenants.show', $tenant) }}" class="fw-medium text-decoration-none">{{ $tenant->name }}</a>
                        </td>
                        <td><span class="text-muted small">{{ $tenant->domain }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:28px;height:28px;background:#eef2ff;color:#6366f1;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:600;">
                                    {{ strtoupper(substr($tenant->owner_name, 0, 2)) }}
                                </div>
                                <span class="small">{{ $tenant->owner_name }}</span>
                            </div>
                        </td>
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
                        <td class="text-end fw-medium">R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-sm btn-outline-secondary" title="Ver detalhes">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-buildings"></i>
                                <p class="mb-1">Nenhum tenant cadastrado</p>
                                <a href="{{ route('tenants.create') }}" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-plus-lg"></i> Criar primeiro tenant
                                </a>
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
