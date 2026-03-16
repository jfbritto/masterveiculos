@extends('layouts.app')

@section('title', 'Novo Pagamento')

@section('content')
<div class="card" style="max-width: 500px;">
    <div class="card-body">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Tenant *</label>
                <select name="tenant_id" class="form-select @error('tenant_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" {{ old('tenant_id', $selectedTenant) == $tenant->id ? 'selected' : '' }}>
                            {{ $tenant->name }}
                        </option>
                    @endforeach
                </select>
                @error('tenant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Valor (R$) *</label>
                <input type="number" step="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Data de Vencimento *</label>
                <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}" required>
                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Registrar Pagamento</button>
                <a href="{{ route('payments.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
