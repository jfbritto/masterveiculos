<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->integer('vehicles_count')->default(0);
            $table->integer('leads_count')->default(0);
            $table->integer('sales_count')->default(0);
            $table->decimal('disk_usage_mb', 10, 2)->default(0);
            $table->timestamp('last_admin_access_at')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stats');
    }
};
