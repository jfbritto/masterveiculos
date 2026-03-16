<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\AsaasService;
use App\Services\TenantApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(
        private TenantApiService $apiService,
        private AsaasService $asaasService,
    ) {}

    public function index()
    {
        $tenants = Tenant::with('latestStats')->orderBy('name')->get();
        return view('tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('tenants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants',
            'monthly_amount' => 'required|string',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'required|string|max:20',
            'owner_cpf_cnpj' => 'required|string|max:18',
            'notes' => 'nullable|string',
        ]);

        // Converter valor BR para decimal
        $validated['monthly_amount'] = $this->parseMoneyBR($validated['monthly_amount']);
        $validated['api_token'] = Str::random(64);

        $tenant = Tenant::create($validated);

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant criado com sucesso.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['stats' => fn ($q) => $q->latest()->limit(30), 'payments' => fn ($q) => $q->latest()]);
        return view('tenants.show', compact('tenant'));
    }

    public function edit(Tenant $tenant)
    {
        return view('tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain,' . $tenant->id,
            'status' => 'required|in:active,suspended,blocked,inactive',
            'monthly_amount' => 'required|string',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'required|string|max:20',
            'owner_cpf_cnpj' => 'required|string|max:18',
            'notes' => 'nullable|string',
        ]);

        $validated['monthly_amount'] = $this->parseMoneyBR($validated['monthly_amount']);

        $tenant->update($validated);

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant atualizado.');
    }

    public function suspend(Tenant $tenant)
    {
        $result = $this->apiService->suspend($tenant);

        if ($result['success']) {
            $tenant->update(['status' => 'suspended']);
            return back()->with('success', 'Tenant suspenso com sucesso.');
        }

        // Suspende localmente mesmo se não conseguir comunicar com o tenant
        $tenant->update(['status' => 'suspended']);
        return back()->with('success', 'Tenant suspenso (sem comunicação com o site).');
    }

    public function reactivate(Tenant $tenant)
    {
        $result = $this->apiService->reactivate($tenant);

        if ($result['success']) {
            $tenant->update(['status' => 'active']);
            return back()->with('success', 'Tenant reativado com sucesso.');
        }

        $tenant->update(['status' => 'active']);
        return back()->with('success', 'Tenant reativado (sem comunicação com o site).');
    }

    public function refreshStats(Tenant $tenant)
    {
        $result = $this->apiService->stats($tenant);

        if ($result['success'] && $result['data']) {
            $tenant->stats()->create($result['data']);
            $tenant->update(['last_heartbeat_at' => now()]);
            return back()->with('success', 'Stats atualizados.');
        }

        return back()->with('error', 'Falha ao obter stats: ' . ($result['error'] ?? 'Erro desconhecido'));
    }

    public function activateBilling(Request $request, Tenant $tenant)
    {
        if ($tenant->asaas_subscription_id) {
            return back()->with('error', 'Cobrança já está ativa para este tenant.');
        }

        $request->validate([
            'first_due_date' => 'required|date|after_or_equal:today',
        ]);

        if (!config('services.asaas.api_key')) {
            return back()->with('error', 'API Key do Asaas não configurada.');
        }

        $result = $this->asaasService->createCustomerAndSubscription($tenant, $request->first_due_date);

        if ($tenant->fresh()->asaas_subscription_id) {
            // Envia dados de billing para o tenant
            $this->apiService->updateBilling($tenant, [
                'billing_status' => 'pending',
                'billing_amount' => $tenant->monthly_amount,
                'billing_due_date' => $request->first_due_date,
                'billing_subscription_status' => 'active',
            ]);

            return back()->with('success', 'Cobrança ativada! Primeiro vencimento: ' . \Carbon\Carbon::parse($request->first_due_date)->format('d/m/Y'));
        }

        return back()->with('error', 'Erro ao ativar cobrança no Asaas. Verifique os logs.');
    }

    public function cancelBilling(Tenant $tenant)
    {
        if (!$tenant->asaas_subscription_id) {
            return back()->with('error', 'Nenhuma cobrança ativa.');
        }

        $this->asaasService->cancelSubscription($tenant->asaas_subscription_id);
        $tenant->update(['asaas_subscription_id' => null]);

        // Limpa dados de billing no tenant
        $this->apiService->updateBilling($tenant, [
            'billing_status' => 'inactive',
            'billing_amount' => null,
            'billing_due_date' => null,
            'billing_invoice_url' => null,
            'billing_type' => null,
            'billing_subscription_status' => 'inactive',
        ]);

        return back()->with('success', 'Cobrança cancelada.');
    }

    public function regenerateToken(Tenant $tenant)
    {
        $tenant->update(['api_token' => Str::random(64)]);
        return back()->with('success', 'Token regenerado. Atualize a configuração no tenant.');
    }

    private function parseMoneyBR(string $value): float
    {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
