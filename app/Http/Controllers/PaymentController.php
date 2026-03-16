<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('tenant')->latest()->paginate(20);
        return view('payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();
        $selectedTenant = $request->query('tenant_id');
        return view('payments.create', compact('tenants', 'selectedTenant'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        Payment::create($validated);

        return redirect()->route('payments.index')->with('success', 'Pagamento registrado.');
    }

    public function markPaid(Payment $payment, Request $request)
    {
        $payment->update([
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Pagamento marcado como pago.');
    }

    public function cancel(Payment $payment)
    {
        $payment->update(['status' => 'cancelled']);
        return back()->with('success', 'Pagamento cancelado.');
    }
}
