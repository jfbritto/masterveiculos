<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'api_token',
        'status',
        'monthly_amount',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_cpf_cnpj',
        'asaas_customer_id',
        'asaas_subscription_id',
        'notes',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'monthly_amount' => 'decimal:2',
    ];

    public function stats(): HasMany
    {
        return $this->hasMany(TenantStat::class);
    }

    public function latestStats(): HasOne
    {
        return $this->hasOne(TenantStat::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return in_array($this->status, ['suspended', 'blocked']);
    }

    public function isOnline(): bool
    {
        return $this->last_heartbeat_at && $this->last_heartbeat_at->diffInMinutes(now()) < 120;
    }
}
