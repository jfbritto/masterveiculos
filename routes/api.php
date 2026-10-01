<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\VerifyAsaasWebhookToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook endpoint - tenants enviam heartbeat/stats para cá
Route::post('/webhook/heartbeat', [WebhookController::class, 'heartbeat']);

// Webhook do Asaas - recebe eventos de pagamento (header asaas-access-token, ver docs/webhook-asaas.md)
Route::post('/webhook/asaas', [AsaasWebhookController::class, 'handle'])
    ->middleware(VerifyAsaasWebhookToken::class);
