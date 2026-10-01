<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-do-webhook-asaas-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.asaas.webhook_token' => self::TOKEN]);

        // Nenhuma chamada real: nem para o Asaas, nem para as lojas.
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    }

    // ------------------------------------------------------------------
    // Eventos do próprio master (comportamento preservado)
    // ------------------------------------------------------------------

    public function test_rejects_invalid_payload(): void
    {
        $this->postJson('/api/webhook/asaas', [], $this->headers())->assertStatus(400);
    }

    public function test_payment_created_stores_payment_and_pushes_to_the_store(): void
    {
        $tenant = $this->tenant(1);

        $this->webhook('PAYMENT_CREATED', [
            'id' => 'pay_123',
            'value' => 100.00,
            'dueDate' => '2026-04-15',
            'billingType' => 'PIX',
            'invoiceUrl' => 'https://asaas.com/i/123',
            'externalReference' => 'tenant_1',
            'subscription' => 'sub_loja_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_123',
            'amount' => '100.00',
            'status' => 'pending',
            'billing_type' => 'PIX',
        ]);

        $billing = $this->sentTo($tenant, '/api/master/billing');
        $this->assertCount(1, $billing);
        $this->assertSame('pending', $billing->first()['billing_status']);
        $this->assertSame('https://asaas.com/i/123', $billing->first()['billing_invoice_url']);
        $this->assertTrue($billing->first()->hasHeader('X-Master-Token', $tenant->api_token));

        $history = $this->sentTo($tenant, '/api/master/billing-history');
        $this->assertCount(1, $history);
        $this->assertSame('pay_123', $history->first()['asaas_payment_id']);
        $this->assertSame('2026-04-15', $history->first()['due_date']);
    }

    public function test_finds_tenant_by_subscription_id(): void
    {
        $tenant = $this->tenant(1);

        $this->webhook('PAYMENT_CREATED', [
            'id' => 'pay_sub_lookup',
            'value' => 100,
            'dueDate' => '2026-05-01',
            'subscription' => 'sub_loja_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_sub_lookup',
        ]);
    }

    public function test_finds_tenant_by_reference_after_its_subscription_was_cancelled(): void
    {
        // cancelBilling/deactivate zeram o asaas_subscription_id; a referência continua valendo.
        $tenant = $this->tenant(1, ['asaas_subscription_id' => null]);
        $this->payment($tenant, 'pay_old_sub');

        $this->webhook('PAYMENT_CONFIRMED', [
            'id' => 'pay_old_sub',
            'paymentDate' => '2026-04-10',
            'externalReference' => 'tenant_1',
            'subscription' => 'sub_cancelada',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_old_sub', 'status' => 'confirmed']);
    }

    public function test_payment_updated_updates_billing_type_and_pushes(): void
    {
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_upd', ['billing_type' => 'UNDEFINED']);

        $this->webhook('PAYMENT_UPDATED', [
            'id' => 'pay_upd',
            'billingType' => 'CREDIT_CARD',
            'invoiceUrl' => 'https://asaas.com/i/upd',
            'externalReference' => 'tenant_1',
            'subscription' => 'sub_loja_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'asaas_payment_id' => 'pay_upd',
            'billing_type' => 'CREDIT_CARD',
            'invoice_url' => 'https://asaas.com/i/upd',
        ]);
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/billing'));
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/billing-history'));
    }

    public function test_payment_updated_without_local_row_does_nothing(): void
    {
        $this->tenant(1);

        $this->webhook('PAYMENT_UPDATED', [
            'id' => 'pay_desconhecido',
            'billingType' => 'PIX',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_payment_confirmed_updates_status_and_pushes(): void
    {
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_456');

        $this->webhook('PAYMENT_CONFIRMED', [
            'id' => 'pay_456',
            'paymentDate' => '2026-04-10',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_456', 'status' => 'confirmed']);
        $this->assertSame('2026-04-10', Payment::where('asaas_payment_id', 'pay_456')->first()->paid_at->toDateString());

        $history = $this->sentTo($tenant, '/api/master/billing-history');
        $this->assertCount(1, $history);
        $this->assertSame('confirmed', $history->first()['status']);
        $this->assertSame('2026-04-10', $history->first()['paid_at']);
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/billing'));
        $this->assertCount(0, $this->sentTo($tenant, '/api/master/reactivate'));
    }

    public function test_payment_received_sets_received_status(): void
    {
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_rec');

        $this->webhook('PAYMENT_RECEIVED', [
            'id' => 'pay_rec',
            'paymentDate' => '2026-04-11',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_rec', 'status' => 'received']);
    }

    public function test_payment_confirmed_reactivates_suspended_tenant(): void
    {
        $tenant = $this->tenant(1, ['status' => 'suspended']);
        $this->payment($tenant, 'pay_789');

        $this->webhook('PAYMENT_CONFIRMED', [
            'id' => 'pay_789',
            'paymentDate' => '2026-04-10',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/reactivate'));
    }

    public function test_payment_received_reactivates_blocked_tenant(): void
    {
        $tenant = $this->tenant(1, ['status' => 'blocked']);
        $this->payment($tenant, 'pay_blk');

        $this->webhook('PAYMENT_RECEIVED', [
            'id' => 'pay_blk',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/reactivate'));
    }

    public function test_payment_overdue_marks_payment_and_pushes_without_suspending(): void
    {
        // Suspender/bloquear é do billing:check-overdue (agendado), não do webhook.
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_overdue');

        $this->webhook('PAYMENT_OVERDUE', [
            'id' => 'pay_overdue',
            'dueDate' => now()->subDays(16)->format('Y-m-d'),
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_overdue', 'status' => 'overdue']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertSame('overdue', $this->sentTo($tenant, '/api/master/billing')->first()['billing_status']);
        $this->assertCount(1, $this->sentTo($tenant, '/api/master/billing-history'));
        $this->assertCount(0, $this->sentTo($tenant, '/api/master/suspend'));
    }

    public function test_payment_refunded_updates_status_and_pushes_history(): void
    {
        $tenant = $this->tenant(1);
        Payment::factory()->paid()->create(['tenant_id' => $tenant->id, 'asaas_payment_id' => 'pay_refund']);

        $this->webhook('PAYMENT_REFUNDED', [
            'id' => 'pay_refund',
            'externalReference' => 'tenant_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_refund', 'status' => 'refunded']);
        $this->assertSame('refunded', $this->sentTo($tenant, '/api/master/billing-history')->first()['status']);
    }

    public function test_payment_deleted_is_ignored(): void
    {
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_del');

        $this->webhook('PAYMENT_DELETED', ['id' => 'pay_del', 'externalReference' => 'tenant_1'])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_del', 'status' => 'pending']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Eventos de outras plataformas da mesma conta Asaas
    // ------------------------------------------------------------------

    public static function foreignReferences(): array
    {
        return [
            'id cru do HelpCheck' => ['1'],
            'uuid que começa com o id' => ['1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed'],
            'prefixo do HelpCheck' => ['helpcheck_1'],
            'prefixo do TreinaEdu' => ['treinaedu:1'],
            'tenant_ com sufixo' => ['tenant_1x'],
            'tenant_ no meio' => ['xtenant_1'],
            'tenant_ com zero à esquerda' => ['tenant_01'],
            'tenant_ sem número' => ['tenant_'],
            'Tenant_ maiúsculo' => ['Tenant_1'],
            'sem referência' => [null],
        ];
    }

    #[DataProvider('foreignReferences')]
    public function test_foreign_payment_is_ignored(?string $reference): void
    {
        $tenant = $this->tenant(1, ['status' => 'suspended']);
        Log::spy();

        foreach (['PAYMENT_CREATED', 'PAYMENT_UPDATED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE', 'PAYMENT_REFUNDED'] as $event) {
            $this->webhook($event, [
                'id' => 'pay_kqnxsbqhslrmoyqs',
                'value' => 5.00,
                'dueDate' => '2026-09-30',
                'paymentDate' => '2026-09-30',
                'externalReference' => $reference,
                'subscription' => 'sub_de_outra_plataforma',
            ])->assertOk();
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('warning');
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'não é do master'));
    }

    public function test_foreign_events_do_not_touch_a_leaked_row(): void
    {
        // Linha gravada pelo bug antigo: pagamento do HelpCheck atribuído à loja 1.
        $tenant = $this->tenant(1);
        $this->payment($tenant, 'pay_kqnxsbqhslrmoyqs', ['amount' => 5]);

        $this->webhook('PAYMENT_CONFIRMED', ['id' => 'pay_kqnxsbqhslrmoyqs', 'externalReference' => '1'])->assertOk();
        $this->webhook('PAYMENT_REFUNDED', ['id' => 'pay_kqnxsbqhslrmoyqs', 'externalReference' => '1'])->assertOk();

        $this->assertDatabaseHas('payments', ['asaas_payment_id' => 'pay_kqnxsbqhslrmoyqs', 'status' => 'pending']);
        Http::assertNothingSent();
    }

    public function test_reference_to_a_missing_tenant_is_ignored(): void
    {
        $this->tenant(1);

        $this->webhook('PAYMENT_CREATED', [
            'id' => 'pay_x',
            'value' => 100,
            'dueDate' => '2026-05-01',
            'externalReference' => 'tenant_999',
            'subscription' => 'sub_loja_1',
        ])->assertOk();

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_reference_and_subscription_of_different_tenants_is_ignored(): void
    {
        $this->tenant(1);
        $this->tenant(2);

        $this->webhook('PAYMENT_CREATED', [
            'id' => 'pay_conflict',
            'value' => 100,
            'dueDate' => '2026-05-01',
            'externalReference' => 'tenant_1',
            'subscription' => 'sub_loja_2',
        ])->assertOk();

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_subscription_match_accepts_a_reference_that_is_not_from_the_master(): void
    {
        $tenant = $this->tenant(1);

        $this->webhook('PAYMENT_CREATED', [
            'id' => 'pay_sub_ref',
            'value' => 100,
            'dueDate' => '2026-05-01',
            'externalReference' => 'algo-editado-no-painel',
            'subscription' => 'sub_loja_1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['tenant_id' => $tenant->id, 'asaas_payment_id' => 'pay_sub_ref']);
    }

    // ------------------------------------------------------------------

    private function tenant(int $id, array $attributes = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'id' => $id,
            'domain' => "https://loja{$id}.test",
            'asaas_customer_id' => "cus_loja_{$id}",
            'asaas_subscription_id' => "sub_loja_{$id}",
        ], $attributes));
    }

    private function payment(Tenant $tenant, string $asaasId, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => $asaasId,
            'status' => 'pending',
        ], $attributes));
    }

    private function headers(?string $token = self::TOKEN): array
    {
        return $token === null ? [] : ['asaas-access-token' => $token];
    }

    private function webhook(string $event, array $payment, ?string $token = self::TOKEN): TestResponse
    {
        return $this->postJson('/api/webhook/asaas', ['event' => $event, 'payment' => $payment], $this->headers($token));
    }

    /** Requisições que o master mandou para um endpoint da loja. */
    private function sentTo(Tenant $tenant, string $path): Collection
    {
        return Http::recorded(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) === parse_url($tenant->domain, PHP_URL_HOST)
            && parse_url($request->url(), PHP_URL_PATH) === $path)
            ->map(fn (array $pair) => $pair[0])
            ->values();
    }
}
