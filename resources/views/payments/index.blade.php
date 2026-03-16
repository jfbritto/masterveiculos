@extends('layouts.app')

@section('title', 'Pagamentos')

@section('actions')
<a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg"></i> Novo Pagamento
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Todos os Pagamentos</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Tenant</th>
                        <th class="text-end">Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Pago em</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('tenants.show', $payment->tenant) }}" class="fw-medium text-decoration-none">{{ $payment->tenant->name }}</a>
                        </td>
                        <td class="text-end fw-medium">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td>{{ $payment->due_date->format('d/m/Y') }}</td>
                        <td>
                            @if($payment->status === 'paid')
                                <span class="badge" style="background:#dcfce7;color:#166534;">
                                    <i class="bi bi-check-circle me-1"></i>Pago
                                </span>
                            @elseif($payment->status === 'pending')
                                @if($payment->isOverdue())
                                    <span class="badge" style="background:#fee2e2;color:#991b1b;">
                                        <i class="bi bi-exclamation-circle me-1"></i>Atrasado
                                    </span>
                                @else
                                    <span class="badge" style="background:#fef3c7;color:#92400e;">
                                        <i class="bi bi-clock me-1"></i>Pendente
                                    </span>
                                @endif
                            @elseif($payment->status === 'cancelled')
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">Cancelado</span>
                            @endif
                        </td>
                        <td class="small">{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-center">
                            @if($payment->status === 'pending')
                                <div class="d-flex justify-content-center gap-1">
                                    <form action="{{ route('payments.mark-paid', $payment) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Marcar como pago"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <form action="{{ route('payments.cancel', $payment) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-receipt"></i>
                                <p class="mb-0">Nenhum pagamento registrado.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payments->hasPages())
        <div class="card-footer bg-white border-top d-flex justify-content-center py-2">
            {{ $payments->links() }}
        </div>
    @endif
</div>
@endsection
