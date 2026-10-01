<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o webhook do Asaas pelo header "asaas-access-token".
 *
 * O Asaas manda nesse header o token cadastrado no webhook (painel do Asaas >
 * Integrações > Webhooks). Sem esta checagem, qualquer um podia mandar um
 * PAYMENT_CONFIRMED falso e reativar um tenant suspenso ou injetar faturas.
 *
 * Fecha por padrão: sem ASAAS_WEBHOOK_TOKEN configurado, recusa tudo.
 * Ver docs/webhook-asaas.md.
 */
class VerifyAsaasWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.asaas.webhook_token');

        if (! is_string($expected) || $expected === '') {
            Log::error('Asaas webhook: ASAAS_WEBHOOK_TOKEN não configurado; requisição recusada.');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $received = $request->header('asaas-access-token');

        if (! is_string($received) || $received === '' || ! hash_equals($expected, $received)) {
            Log::warning('Asaas webhook: asaas-access-token ausente ou inválido; requisição recusada.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
