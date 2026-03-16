<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook endpoint - tenants enviam heartbeat/stats para cá
Route::post('/webhook/heartbeat', [WebhookController::class, 'heartbeat']);

// Webhook do Asaas - recebe eventos de pagamento
Route::post('/webhook/asaas', [AsaasWebhookController::class, 'handle']);
