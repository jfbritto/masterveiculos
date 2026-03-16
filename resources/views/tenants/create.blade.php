@extends('layouts.app')

@section('title', 'Novo Tenant')

@section('content')
<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form action="{{ route('tenants.store') }}" method="POST">
            @csrf

            <h6 class="mb-3">Dados da Loja</h6>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nome da Loja *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Mensalidade (R$)</label>
                    <input type="text" name="monthly_amount" id="monthly_amount" class="form-control @error('monthly_amount') is-invalid @enderror" value="{{ old('monthly_amount', '100,00') }}">
                    @error('monthly_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Domínio *</label>
                <input type="text" name="domain" class="form-control @error('domain') is-invalid @enderror" value="{{ old('domain') }}" placeholder="https://loja.com.br" required>
                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <hr>
            <h6 class="mb-3">Dados do Proprietário</h6>

            <div class="row">
                <div class="col-md-7 mb-3">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name') }}" required>
                    @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">CPF/CNPJ *</label>
                    <input type="text" name="owner_cpf_cnpj" id="cpf_cnpj" class="form-control @error('owner_cpf_cnpj') is-invalid @enderror" value="{{ old('owner_cpf_cnpj') }}" placeholder="000.000.000-00" required>
                    @error('owner_cpf_cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-7 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="owner_email" class="form-control @error('owner_email') is-invalid @enderror" value="{{ old('owner_email') }}" required>
                    @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Telefone *</label>
                    <input type="text" name="owner_phone" id="phone" class="form-control @error('owner_phone') is-invalid @enderror" value="{{ old('owner_phone') }}" placeholder="(00) 00000-0000" required>
                    @error('owner_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Criar Tenant</button>
                <a href="{{ route('tenants.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@include('tenants._masks')
@endsection
