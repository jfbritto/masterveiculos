# Webhook do Asaas

`POST https://veiculos.helpflux.com.br/api/webhook/asaas`
(`routes/api.php` → `AsaasWebhookController@handle`)

## A conta é compartilhada

A conta do Asaas atende várias plataformas do dono (HelpCheck, TreinaEdu,
master-veiculos e outras). O webhook do master recebe **todos** os eventos da
conta, e a maioria não é dele.

O master marca o que é dele com `externalReference = "tenant_<id>"` no cliente e
na assinatura (`AsaasService`), e guarda o id da assinatura em
`tenants.asaas_subscription_id`. O dono de um evento sai de
`AsaasPaymentOwnership::match()`:

1. `externalReference` **exatamente** `tenant_<id>` → esse tenant, desde que a
   assinatura do evento (se vier) não seja de outro tenant;
2. senão, `subscription` igual a `asaas_subscription_id` de um tenant;
3. senão o evento **não é do master**: responde 200, não grava nada, não
   empurra nada para a loja e registra em log `info`
   ("pagamento não é do master").

Antes de 01/10/2026 qualquer referência caía em `Tenant::find()`: o `"1"` do
HelpCheck virava o tenant 1, e o MySQL convertia uuids como `"3f2a..."` no
inteiro 3. Pagamentos de outras plataformas foram gravados no master e
empurrados para o histórico de cobrança das lojas. Para limpar isso, ver
[Auditoria](#auditoria-de-pagamentos-gravados).

## Autenticação

O Asaas manda no header `asaas-access-token` o token cadastrado no webhook. O
middleware `VerifyAsaasWebhookToken` compara com `ASAAS_WEBHOOK_TOKEN`
(`config('services.asaas.webhook_token')`) usando `hash_equals`:

| Situação | Resposta |
|---|---|
| header ausente ou diferente | 401, log `warning` |
| `ASAAS_WEBHOOK_TOKEN` vazio no servidor | 401 para **tudo**, log `error` |
| token certo | segue para o controller |

Fecha por padrão: esquecer o `.env` derruba o webhook em vez de abri-lo.

## Implantação sem queda

O Asaas começa a mandar o header assim que o token é cadastrado no painel, e o
código antigo ignora o header. Por isso o token entra **antes** do código.
Faça na ordem, e só faça o merge no passo 4: push em `main` já é deploy.

Servidor: `ssh helpflux` (root, porta 22022). App em `/var/www/masterveiculos`,
que roda em PHP 8.4 (`php8.4`).

**1. Gerar o token** (no Mac). Guarde no gerenciador de senhas.

```bash
openssl rand -hex 32
```

**2. Pôr no `.env` do master**, sem deixar o token no histórico do shell:

```bash
cd /var/www/masterveiculos
grep -c '^ASAAS_WEBHOOK_TOKEN=' .env    # esperado: 0
read -rs TOKEN                          # cole o token e Enter
printf '\nASAAS_WEBHOOK_TOKEN=%s\n' "$TOKEN" >> .env
unset TOKEN
grep -c '^ASAAS_WEBHOOK_TOKEN=' .env    # esperado: 1
```

O código que está no ar não lê essa chave: nada muda ainda.

**3. Cadastrar o mesmo token no painel do Asaas.** Integrações → Webhooks →
o webhook do master (URL acima) → Editar → *Token de autenticação* → salvar.
Mexa só no webhook do master, não nos das outras plataformas. Se o painel
recusar o token, gere outro (passo 1) e refaça o passo 2.

**4. Deploy.** Merge da branch em `main` e push. O GitHub Actions
(`.github/workflows/deploy.yml`) chama `/home/deploy/deploy-masterveiculos.sh`
por SSH. Não há job de teste no workflow do master: rode `php artisan test`
antes do merge.

**5. Config cache.** Logo depois do deploy:

```bash
cd /var/www/masterveiculos
ls bootstrap/cache/config.php    # existe = a config está em cache
```

Se existir, e o log do deploy no Actions não mostrar `config:cache`, rode
agora (o cache antigo não tem a chave nova e o webhook ficaria recusando tudo):

```bash
sudo -u deploy php8.4 artisan config:cache
```

**6. Conferir.**

```bash
php8.4 artisan tinker --execute="var_dump(strlen((string) config('services.asaas.webhook_token')));"
# esperado: int(64)

curl -s -o /dev/null -w '%{http_code}\n' -X POST https://veiculos.helpflux.com.br/api/webhook/asaas \
  -H 'Content-Type: application/json' -d '{}'
# esperado: 401 (sem token)

read -rs TOKEN
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://veiculos.helpflux.com.br/api/webhook/asaas \
  -H 'Content-Type: application/json' -H "asaas-access-token: $TOKEN" -d '{}'
unset TOKEN
# esperado: 400 (passou na autenticação; payload vazio, nada processado)

tail -f storage/logs/laravel.log | grep -i asaas
```

No log: linhas `info` "pagamento não é do master" para eventos das outras
plataformas são normais. **Não** deve aparecer "ASAAS_WEBHOOK_TOKEN não
configurado" nem "asaas-access-token ausente ou inválido" para os envios do
Asaas.

No painel do Asaas, confira que a fila do webhook do master não está
interrompida. O Asaas reenvia eventos que falharam, mas interrompe a fila
depois de uma sequência de falhas; se isso acontecer, corrija o token (passos
2 e 5) e reative a fila no painel.

## Auditoria de pagamentos gravados

Ver a seção seguinte, adicionada junto com o comando `payments:audit-ownership`.
