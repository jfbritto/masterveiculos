<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'vehicles_count',
        'leads_count',
        'sales_count',
        'disk_usage_mb',
        'last_admin_access_at',
        'extra_data',
    ];

    protected $casts = [
        'last_admin_access_at' => 'datetime',
        'extra_data' => 'array',
        'disk_usage_mb' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
