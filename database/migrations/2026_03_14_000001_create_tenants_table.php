<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            // Loja
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('api_token', 64)->unique();
            $table->enum('status', ['active', 'suspended', 'blocked', 'inactive'])->default('active');
            $table->decimal('monthly_amount', 10, 2)->default(100.00);

            // Proprietário
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone');
            $table->string('owner_cpf_cnpj', 18);

            // Asaas
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable();

            // Controle
            $table->text('notes')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
