@extends('layouts.app')

@section('title', 'Editar Tenant')

@section('content')
<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form action="{{ route('tenants.update', $tenant) }}" method="POST">
            @csrf
            @method('PUT')

            <h6 class="mb-3">Dados da Loja</h6>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nome da Loja *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $tenant->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Mensalidade (R$)</label>
                    <input type="text" name="monthly_amount" id="monthly_amount" class="form-control @error('monthly_amount') is-invalid @enderror" value="{{ old('monthly_amount', number_format($tenant->monthly_amount, 2, ',', '.')) }}">
                    @error('monthly_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status', $tenant->status) === 'active' ? 'selected' : '' }}>Ativo</option>
                        <option value="suspended" {{ old('status', $tenant->status) === 'suspended' ? 'selected' : '' }}>Suspenso</option>
                        <option value="blocked" {{ old('status', $tenant->status) === 'blocked' ? 'selected' : '' }}>Bloqueado</option>
                        <option value="inactive" {{ old('status', $tenant->status) === 'inactive' ? 'selected' : '' }}>Inativo</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Domínio *</label>
                <input type="text" name="domain" class="form-control @error('domain') is-invalid @enderror" value="{{ old('domain', $tenant->domain) }}" required>
                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <hr>
            <h6 class="mb-3">Dados do Proprietário</h6>

            <div class="row">
                <div class="col-md-7 mb-3">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name', $tenant->owner_name) }}" required>
                    @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">CPF/CNPJ *</label>
                    <input type="text" name="owner_cpf_cnpj" id="cpf_cnpj" class="form-control @error('owner_cpf_cnpj') is-invalid @enderror" value="{{ old('owner_cpf_cnpj', $tenant->owner_cpf_cnpj) }}" required>
                    @error('owner_cpf_cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-7 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="owner_email" class="form-control @error('owner_email') is-invalid @enderror" value="{{ old('owner_email', $tenant->owner_email) }}" required>
                    @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Telefone *</label>
                    <input type="text" name="owner_phone" id="phone" class="form-control @error('owner_phone') is-invalid @enderror" value="{{ old('owner_phone', $tenant->owner_phone) }}" required>
                    @error('owner_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $tenant->notes) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@include('tenants._masks')
@endsection
