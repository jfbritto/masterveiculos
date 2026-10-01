<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Decide se um pagamento do Asaas é de um tenant do master.
 *
 * A conta do Asaas é compartilhada com outras plataformas do dono (HelpCheck,
 * TreinaEdu e outras), e o webhook do master recebe os eventos de TODAS. O
 * master marca o que é dele com externalReference "tenant_<id>" (ver
 * AsaasService) e guarda o id da assinatura em tenants.asaas_subscription_id.
 *
 * Antes, qualquer referência caía em Tenant::find(str_replace('tenant_', '', ...)):
 * o "1" do HelpCheck virava o tenant 1, e o MySQL convertia uuids como
 * "3f2a..." no inteiro 3. Em 30/09/2026 um Pix de R$ 5 do HelpCheck foi parar
 * no histórico de cobrança da loja soavelveiculos.
 */
class AsaasPaymentOwnership
{
    public const OURS = 'ours';

    public const NOT_OURS = 'not_ours';

    public const UNKNOWN = 'unknown';

    /**
     * "tenant_12" -> 12. Qualquer outra forma ("12", "tenant_012", "tenant_12x",
     * "helpcheck_12", um uuid...) -> null.
     */
    public static function tenantIdFromReference(mixed $reference): ?int
    {
        if (! is_string($reference) || ! preg_match('/^tenant_([1-9][0-9]{0,17})\z/', $reference, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Tenant dono do pagamento de um evento do webhook.
     *
     * 1. externalReference exatamente "tenant_<id>": é desse tenant, desde que
     *    a assinatura do evento (se houver) não seja de OUTRO tenant;
     * 2. senão, a assinatura do evento é exatamente a de um tenant;
     * 3. senão, o evento não é do master.
     *
     * @return array{tenant: ?Tenant, reason: string, conflict: bool}
     */
    public function match(array $payment): array
    {
        $subscription = self::text($payment['subscription'] ?? null);
        $bySubscription = $subscription !== ''
            ? Tenant::where('asaas_subscription_id', $subscription)->first()
            : null;

        $referencedId = self::tenantIdFromReference($payment['externalReference'] ?? null);

        if ($referencedId !== null) {
            $tenant = Tenant::find($referencedId);

            if (! $tenant) {
                return $this->noMatch("externalReference tenant_{$referencedId} não existe no master");
            }

            if ($bySubscription && $bySubscription->id !== $tenant->id) {
                return $this->noMatch(
                    "externalReference é do tenant {$tenant->id}, mas a assinatura é do tenant {$bySubscription->id}",
                    conflict: true,
                );
            }

            return ['tenant' => $tenant, 'reason' => 'externalReference', 'conflict' => false];
        }

        if ($bySubscription) {
            return ['tenant' => $bySubscription, 'reason' => 'subscription', 'conflict' => false];
        }

        return $this->noMatch('sem externalReference tenant_<id> e sem assinatura de um tenant');
    }

    /**
     * Veredito da auditoria para uma linha de Payment gravada para $tenant.
     *
     * Erra para o lado de NÃO apagar: só é "not_ours" quando nada liga o
     * pagamento ao tenant. Pagamento de outro tenant do master vira "unknown"
     * (revisar à mão), não "not_ours".
     *
     * @param  array|null  $payment  o pagamento lido do Asaas; null se não deu para ler
     * @return array{verdict: string, reason: string}
     */
    public function verdict(Tenant $tenant, ?array $payment): array
    {
        if ($payment === null) {
            return ['verdict' => self::UNKNOWN, 'reason' => 'não foi possível ler o pagamento no Asaas'];
        }

        $subscription = self::text($payment['subscription'] ?? null);
        $customer = self::text($payment['customer'] ?? null);
        $referencedId = self::tenantIdFromReference($payment['externalReference'] ?? null);

        if ($referencedId === $tenant->id) {
            return ['verdict' => self::OURS, 'reason' => "externalReference tenant_{$tenant->id}"];
        }

        if ($referencedId !== null) {
            return ['verdict' => self::UNKNOWN, 'reason' => "externalReference é de outro tenant (tenant_{$referencedId})"];
        }

        if ($subscription !== '' && $subscription === (string) $tenant->asaas_subscription_id) {
            return ['verdict' => self::OURS, 'reason' => 'assinatura do tenant'];
        }

        if ($subscription !== '' && ($other = Tenant::where('asaas_subscription_id', $subscription)->first())) {
            return ['verdict' => self::UNKNOWN, 'reason' => "assinatura de outro tenant (#{$other->id})"];
        }

        if ($customer !== '' && $customer === (string) $tenant->asaas_customer_id) {
            return ['verdict' => self::OURS, 'reason' => 'cliente do tenant no Asaas'];
        }

        return ['verdict' => self::NOT_OURS, 'reason' => 'referência, assinatura e cliente não são do tenant'];
    }

    private function noMatch(string $reason, bool $conflict = false): array
    {
        return ['tenant' => null, 'reason' => $reason, 'conflict' => $conflict];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
