<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('latestStats')->get();

        $totalVehicles = $tenants->sum(fn ($t) => $t->latestStats?->vehicles_count ?? 0);
        $totalLeads = $tenants->sum(fn ($t) => $t->latestStats?->leads_count ?? 0);
        $totalSales = $tenants->sum(fn ($t) => $t->latestStats?->sales_count ?? 0);

        $activeTenants = $tenants->where('status', 'active')->count();
        $suspendedTenants = $tenants->whereIn('status', ['suspended', 'blocked'])->count();
        $onlineTenants = $tenants->filter(fn ($t) => $t->isOnline())->count();

        $overduePayments = Payment::where('status', 'pending')
            ->where('due_date', '<', now())
            ->count();

        return view('dashboard', compact(
            'tenants',
            'totalVehicles',
            'totalLeads',
            'totalSales',
            'activeTenants',
            'suspendedTenants',
            'onlineTenants',
            'overduePayments'
        ));
    }
}
