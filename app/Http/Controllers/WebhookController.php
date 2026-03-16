<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function heartbeat(Request $request)
    {
        $token = $request->header('X-Master-Token');

        if (!$token) {
            return response()->json(['error' => 'Token missing'], 401);
        }

        $tenant = Tenant::where('api_token', $token)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'vehicles_count' => 'nullable|integer',
            'leads_count' => 'nullable|integer',
            'sales_count' => 'nullable|integer',
            'disk_usage_mb' => 'nullable|numeric',
            'last_admin_access_at' => 'nullable|date',
            'extra_data' => 'nullable|array',
        ]);

        $tenant->stats()->create($validated);
        $tenant->update(['last_heartbeat_at' => now()]);

        return response()->json([
            'status' => $tenant->status,
            'message' => 'Heartbeat received',
        ]);
    }
}
