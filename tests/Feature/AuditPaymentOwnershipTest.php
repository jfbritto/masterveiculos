<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class AuditPaymentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const ASAAS = 'https://sandbox.asaas.com/api/v3/payments/';

    /** Pagamentos que o Asaas "devolve", por id. Os ausentes respondem 404. */
    private array $asaas = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.asaas.api_key' => 'chave-de-teste', 'services.asaas.sandbox' => true]);

        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), self::ASAAS)) {
                $id = rawurldecode(substr($request->url(), strlen(self::ASAAS)));

                return isset($this->asaas[$id])
                    ? Http::response(['id' => $id] + $this->asaas[$id])
                    : Http::response(['errors' => [['code' => 'invalid_action', 'description' => 'not found']]], 404);
            }

            return Http::response(['status' => 'ok']); // endpoints /api/master/* das lojas
        });
    }

    public function test_dry_run_lists_verdicts_and_changes_nothing(): void
    {
        $this->scenario();

        $output = $this->audit();

        $this->assertStringContainsString('pay_kqnxsbqhslrmoyqs', $output);
        $this->assertMatchesRegularExpression('/pay_own\b.*\|\s*nosso\s*\|/u', $output);
        $this->assertMatchesRegularExpression('/pay_kqnxsbqhslrmoyqs.*R\$ 5,00.*30\/09\/2026.*sub_helpcheck\s*\|\s*1\s*\|\s*não é nosso\s*\|/u', $output);
        $this->assertMatchesRegularExpression('/pay_card.*R\$ 300,00.*\|\s*não é nosso\s*\|/u', $output);
        $this->assertMatchesRegularExpression('/pay_sub_only.*\|\s*nosso\s*\|/u', $output);
        $this->assertMatchesRegularExpression('/pay_gone.*\|\s*incerto\s*\|.*sandbox/u', $output);
        $this->assertMatchesRegularExpression('/pay_other_tenant.*\|\s*incerto\s*\|.*tenant_2/u', $output);
        $this->assertStringNotContainsString('manual', $output);

        // O que precisa sair de cada loja, já que a loja não tem endpoint de exclusão.
        $this->assertStringContainsString("DELETE FROM billing_history WHERE asaas_payment_id IN ('pay_kqnxsbqhslrmoyqs');", $output);
        $this->assertStringContainsString("DELETE FROM billing_history WHERE asaas_payment_id IN ('pay_card');", $output);
        $this->assertStringContainsString('--force', $output);

        $this->assertDatabaseCount('payments', 7);
        $this->assertCount(0, $this->storeRequests());

        $asaasCalls = $this->asaasRequests();
        $this->assertCount(6, $asaasCalls); // a linha manual (sem asaas_payment_id) não é consultada
        $this->assertTrue($asaasCalls->every(fn (Request $r) => $r->hasHeader('access_token', 'chave-de-teste')));
        $this->assertTrue($asaasCalls->every(fn (Request $r) => $r->method() === 'GET'));

        Sleep::assertSleptTimes(5); // uma pausa entre cada par de chamadas
    }

    public function test_force_deletes_only_foreign_rows_and_resyncs_the_store_summary(): void
    {
        [$soavel, $friedrich] = $this->scenario();

        $output = $this->audit(['--force' => true]);

        $this->assertDatabaseMissing('payments', ['asaas_payment_id' => 'pay_kqnxsbqhslrmoyqs']);
        $this->assertDatabaseMissing('payments', ['asaas_payment_id' => 'pay_card']);
        foreach (['pay_own', 'pay_sub_only', 'pay_gone', 'pay_other_tenant'] as $kept) {
            $this->assertDatabaseHas('payments', ['asaas_payment_id' => $kept]);
        }
        $this->assertDatabaseHas('payments', ['asaas_payment_id' => null, 'tenant_id' => $soavel->id]);

        // Resumo de cobrança reenviado pelo endpoint autenticado da loja, já sem a fatura de fora.
        $summary = $this->storeRequests('/api/master/billing');
        $this->assertCount(2, $summary);
        $soavelSummary = $summary->first(fn (Request $r) => str_starts_with($r->url(), $soavel->domain));
        $this->assertSame('100.00', $soavelSummary['billing_amount']);
        $this->assertTrue($soavelSummary->hasHeader('X-Master-Token', $soavel->api_token));
        $this->assertNotNull($summary->first(fn (Request $r) => str_starts_with($r->url(), $friedrich->domain)));

        // Nada de histórico: a loja não tem como apagar por API, então o comando lista o SQL.
        $this->assertCount(0, $this->storeRequests('/api/master/billing-history'));
        $this->assertStringContainsString("DELETE FROM billing_history WHERE asaas_payment_id IN ('pay_kqnxsbqhslrmoyqs');", $output);
        $this->assertStringContainsString('2 pagamento(s) apagado(s)', $output);
    }

    public function test_force_clears_the_store_summary_when_nothing_is_left(): void
    {
        $tenant = $this->tenant(3, 'So Fora');
        $this->row($tenant, 'pay_only_foreign', ['amount' => 300, 'status' => 'confirmed']);
        $this->asaas['pay_only_foreign'] = ['externalReference' => '3', 'subscription' => 'sub_treinaedu', 'customer' => 'cus_x'];

        $this->audit(['--force' => true]);

        $this->assertDatabaseCount('payments', 0);
        $summary = $this->storeRequests('/api/master/billing');
        $this->assertCount(1, $summary);
        $this->assertSame('', $summary->first()['billing_status']);
        $this->assertSame('', $summary->first()['billing_amount']);
        $this->assertSame('active', $summary->first()['billing_subscription_status']); // a assinatura do tenant segue ativa
    }

    public function test_tenant_option_limits_the_audit(): void
    {
        [, $friedrich] = $this->scenario();

        $output = $this->audit(['--tenant' => $friedrich->id]);

        $this->assertStringContainsString('pay_card', $output);
        $this->assertStringNotContainsString('pay_kqnxsbqhslrmoyqs', $output);
        $this->assertCount(2, $this->asaasRequests());
    }

    public function test_unusual_payment_id_is_url_encoded_and_kept_out_of_the_sql(): void
    {
        $tenant = $this->tenant(1, 'Soavel');
        $weird = "pay_x'); DROP TABLE billing_history; --";
        $this->row($tenant, $weird);
        $this->row($tenant, 'pay_plain');
        $this->asaas[$weird] = ['externalReference' => '1', 'subscription' => 'sub_helpcheck'];
        $this->asaas['pay_plain'] = ['externalReference' => '1', 'subscription' => 'sub_helpcheck'];

        $output = $this->audit();

        $this->assertContains(self::ASAAS.rawurlencode($weird), $this->asaasRequests()->map(fn (Request $r) => $r->url()));
        $this->assertStringContainsString("DELETE FROM billing_history WHERE asaas_payment_id IN ('pay_plain');", $output);
        $this->assertStringNotContainsString('DROP TABLE billing_history; --\')', $output);
        $this->assertStringContainsString('revisar à mão', $output);
    }

    public function test_fails_without_the_asaas_api_key(): void
    {
        config(['services.asaas.api_key' => '']);
        $this->scenario();

        $this->assertSame(1, Artisan::call('payments:audit-ownership', ['--force' => true]));

        $this->assertDatabaseCount('payments', 7);
        Http::assertNothingSent();
    }

    public function test_nothing_to_do_when_every_row_is_ours(): void
    {
        $tenant = $this->tenant(1, 'Soavel');
        $this->row($tenant, 'pay_own');
        $this->asaas['pay_own'] = ['externalReference' => 'tenant_1', 'subscription' => 'sub_loja_1'];

        $output = $this->audit(['--force' => true]);

        $this->assertStringContainsString('Nenhum pagamento de fora', $output);
        $this->assertDatabaseCount('payments', 1);
        $this->assertCount(0, $this->storeRequests());
    }

    // ------------------------------------------------------------------

    /** @return array{0: Tenant, 1: Tenant} */
    private function scenario(): array
    {
        $soavel = $this->tenant(1, 'Soavel Veiculos');
        $friedrich = $this->tenant(2, 'Friedrich Veiculos');

        $this->row($soavel, 'pay_own', ['amount' => 100, 'due_date' => '2026-10-10']);
        $this->asaas['pay_own'] = ['externalReference' => 'tenant_1', 'subscription' => 'sub_loja_1', 'customer' => 'cus_loja_1'];

        // O Pix de R$ 5 do HelpCheck (referência "1") gravado na loja 1.
        $this->row($soavel, 'pay_kqnxsbqhslrmoyqs', ['amount' => 5, 'status' => 'confirmed', 'due_date' => '2026-09-30']);
        $this->asaas['pay_kqnxsbqhslrmoyqs'] = ['externalReference' => '1', 'subscription' => 'sub_helpcheck', 'customer' => 'cus_helpcheck'];

        // Cartão de R$ 300 de outra plataforma, com uuid que o MySQL converte em 2.
        $this->row($friedrich, 'pay_card', ['amount' => 300, 'status' => 'confirmed', 'billing_type' => 'CREDIT_CARD']);
        $this->asaas['pay_card'] = ['externalReference' => '2b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed', 'subscription' => 'sub_outra', 'customer' => 'cus_outra'];

        $this->row($friedrich, 'pay_sub_only', ['amount' => 150]);
        $this->asaas['pay_sub_only'] = ['externalReference' => null, 'subscription' => 'sub_loja_2', 'customer' => 'cus_loja_2'];

        $this->row($soavel, 'pay_gone', ['invoice_url' => 'https://sandbox.asaas.com/i/gone']); // 404

        $this->row($soavel, 'pay_other_tenant');
        $this->asaas['pay_other_tenant'] = ['externalReference' => 'tenant_2', 'subscription' => 'sub_loja_2'];

        $this->row($soavel, null, ['amount' => 42]); // lançamento manual, sem Asaas

        return [$soavel, $friedrich];
    }

    private function tenant(int $id, string $name): Tenant
    {
        return Tenant::factory()->create([
            'id' => $id,
            'name' => $name,
            'domain' => "https://loja{$id}.test",
            'asaas_customer_id' => "cus_loja_{$id}",
            'asaas_subscription_id' => "sub_loja_{$id}",
        ]);
    }

    private function row(Tenant $tenant, ?string $asaasId, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => $asaasId,
            'status' => 'pending',
        ], $attributes));
    }

    private function audit(array $options = []): string
    {
        $this->assertSame(0, Artisan::call('payments:audit-ownership', $options));

        return Artisan::output();
    }

    private function asaasRequests(): Collection
    {
        return Http::recorded(fn (Request $r) => str_starts_with($r->url(), self::ASAAS))->map(fn ($pair) => $pair[0])->values();
    }

    private function storeRequests(?string $path = null): Collection
    {
        return Http::recorded(fn (Request $r) => str_ends_with((string) parse_url($r->url(), PHP_URL_HOST), '.test')
            && ($path === null || parse_url($r->url(), PHP_URL_PATH) === $path))
            ->map(fn ($pair) => $pair[0])
            ->values();
    }
}
