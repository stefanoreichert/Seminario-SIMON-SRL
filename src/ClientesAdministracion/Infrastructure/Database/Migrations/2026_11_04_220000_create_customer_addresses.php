<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_delivery_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('business_customers');
            $table->string('label', 100);
            $table->string('street');
            $table->string('locality', 100);
            $table->string('province', 100);
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('references')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_delivery_addresses');
    }
};
