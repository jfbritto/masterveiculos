@extends('layouts.app')

@section('title', 'Tenants')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h5>Todos os Tenants</h5>
    <a href="{{ route('tenants.create') }}" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Tenant
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Domínio</th>
                    <th>Proprietário</th>
                    <th>Status</th>
                    <th>Mensalidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                <tr>
                    <td><a href="{{ route('tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                    <td><small>{{ $tenant->domain }}</small></td>
                    <td>{{ $tenant->owner_name }}</td>
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
                    <td>R$ {{ number_format($tenant->monthly_amount, 2, ',', '.') }}</td>
                    <td>
                        <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum tenant cadastrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
