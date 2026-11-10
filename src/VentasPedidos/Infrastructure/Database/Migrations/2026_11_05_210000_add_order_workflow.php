<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('revision')->default(0);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('business_order_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('request_digest', 64);
            $table->foreignId('order_id')->constrained('business_orders');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 30);
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_order_changes');

        if (DB::getDriverName() === 'mysql' && ! Schema::hasIndex('business_orders', ['customer_id'])) {
            Schema::table('business_orders', function (Blueprint $table): void {
                $table->index('customer_id');
            });
        }

        Schema::table('business_orders', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropColumn('revision');
        });
    }
};
