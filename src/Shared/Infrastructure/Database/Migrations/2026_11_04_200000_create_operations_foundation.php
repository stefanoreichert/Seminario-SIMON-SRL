<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->string('sales_identifier', 1)->nullable()->unique();
        });

        Schema::create('access_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
        });
        Schema::create('access_role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->string('permission');
            $table->primary(['role_id', 'permission']);
        });
        Schema::create('access_user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('access_assignment_lock', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('access_assignment_lock')->insert(['id' => 1, 'revision' => 0]);

        Schema::create('business_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cuit', 32)->unique();
            $table->text('credit_history')->default('');
        });
        Schema::create('catalog_variants', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('model', 100);
            $table->string('color', 100);
            $table->string('size', 30);
            $table->unique(['model', 'color', 'size']);
        });
        Schema::create('customer_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('business_customers');
            $table->foreignId('actor_id')->constrained('users');
            $table->text('note');
            $table->timestamp('recorded_at');
        });
        Schema::create('business_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('registration_id')->unique();
            $table->string('request_digest', 64);
            $table->string('number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained('business_customers');
            $table->foreignId('seller_id')->constrained('users');
            $table->string('origin', 1);
            $table->string('status')->default('Confirmado');
            $table->timestamp('created_at');
        });
        Schema::create('business_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('business_orders');
            $table->foreignId('variant_id')->constrained('catalog_variants');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('dispatched')->default(0);
            $table->unique(['order_id', 'variant_id']);
        });
        Schema::create('dispatch_authorizations', function (Blueprint $table): void {
            $table->foreignId('order_id')->primary()->constrained('business_orders');
            $table->foreignId('authorized_by')->constrained('users');
            $table->boolean('billing_validated');
            $table->boolean('credit_validated');
            $table->text('conditions');
            $table->timestamp('updated_at');
        });
        Schema::create('production_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('registration_id')->unique();
            $table->foreignId('variant_id')->constrained('catalog_variants');
            $table->string('lot', 100);
            $table->date('manufactured_on');
            $table->unsignedInteger('in_process');
        });
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('catalog_variants');
            $table->string('lot', 100);
            $table->date('manufactured_on');
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['variant_id', 'lot', 'manufactured_on']);
        });
        Schema::create('inventory_audit_head', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('lock_revision')->default(0);
            $table->unsignedBigInteger('sequence')->default(0);
            $table->string('digest', 64);
        });
        DB::table('inventory_audit_head')->insert(['id' => 1, 'lock_revision' => 0, 'sequence' => 0, 'digest' => str_repeat('0', 64)]);
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('request_digest', 64);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('kind', 30);
            $table->unsignedInteger('quantity');
            $table->foreignId('batch_id')->constrained('inventory_batches');
            $table->foreignId('order_line_id')->nullable()->constrained('business_order_lines');
            $table->timestamp('occurred_at');
        });
        Schema::create('inventory_audits', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence')->primary();
            $table->foreignId('movement_id')->unique()->constrained('inventory_movements');
            $table->text('payload');
            $table->string('previous_digest', 64);
            $table->string('digest', 64);
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['inventory_audits', 'inventory_movements'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_".strtolower($operation)." BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'El historial de inventario es inmutable'); END");
                }
            }
        } elseif (DB::getDriverName() === 'mysql') {
            foreach (['inventory_audits', 'inventory_movements'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_".strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial de inventario es inmutable'");
                }
            }
        } else {
            throw new RuntimeException('La protección de auditoría de esta entrega requiere SQLite o MySQL.');
        }
    }

    public function down(): void
    {
        foreach (['inventory_audits', 'inventory_movements', 'inventory_audit_head', 'inventory_batches', 'production_batches', 'dispatch_authorizations', 'business_order_lines', 'business_orders', 'customer_credit_entries', 'catalog_variants', 'business_customers', 'access_user_roles', 'access_role_permissions', 'access_roles', 'access_assignment_lock'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['sales_identifier']);
            $table->dropColumn(['is_active', 'sales_identifier']);
        });
    }
};
