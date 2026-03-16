@extends('layouts.app')

@section('title', 'Pagamentos')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h5>Todos os Pagamentos</h5>
    <a href="{{ route('payments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Pagamento
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tenant</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Pago em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td><a href="{{ route('tenants.show', $payment->tenant) }}">{{ $payment->tenant->name }}</a></td>
                    <td>R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                    <td>{{ $payment->due_date->format('d/m/Y') }}</td>
                    <td>
                        @if($payment->status === 'paid')
                            <span class="badge bg-success">Pago</span>
                        @elseif($payment->status === 'pending')
                            <span class="badge {{ $payment->isOverdue() ? 'bg-danger' : 'bg-warning' }}">
                                {{ $payment->isOverdue() ? 'Atrasado' : 'Pendente' }}
                            </span>
                        @elseif($payment->status === 'cancelled')
                            <span class="badge bg-secondary">Cancelado</span>
                        @endif
                    </td>
                    <td>{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        @if($payment->status === 'pending')
                            <form action="{{ route('payments.mark-paid', $payment) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <form action="{{ route('payments.cancel', $payment) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum pagamento registrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())
        <div class="card-footer">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
