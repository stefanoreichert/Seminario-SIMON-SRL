<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_tracked_batches', function (Blueprint $table): void {
            $table->foreignId('batch_id')->primary()->constrained('inventory_batches');
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('activated_at');
        });
        Schema::create('inventory_pair_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 28)->unique();
            $table->foreignId('batch_id')->constrained('inventory_batches');
            $table->string('state', 20)->default('available');
            $table->unsignedBigInteger('order_line_id')->nullable()->index();
            $table->foreignId('picked_by')->nullable()->constrained('users');
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements');
            $table->index(['batch_id', 'state']);
        });
        Schema::create('inventory_pair_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('request_digest', 64);
            $table->text('request');
            $table->timestamp('occurred_at');
        });
        Schema::create('inventory_expiry_settings', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('anticipation_days')->nullable();
        });
        DB::table('inventory_expiry_settings')->insert(['id' => 1, 'anticipation_days' => null]);
        Schema::create('inventory_supply_lots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('name', 150);
            $table->string('lot', 100);
            $table->date('expires_on');
            $table->foreignId('production_id')->nullable()->constrained('production_batches');
            $table->timestamp('registered_at');
            $table->index('expires_on');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER inventory_pair_identity_no_update BEFORE UPDATE ON inventory_pair_units WHEN NEW.code != OLD.code OR NEW.batch_id != OLD.batch_id OR OLD.state = 'dispatched' BEGIN SELECT RAISE(ABORT, 'La identidad del par despachado es inmutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_pair_units_no_delete BEFORE DELETE ON inventory_pair_units BEGIN SELECT RAISE(ABORT, 'La identidad del par es inmutable'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER inventory_pair_identity_no_update BEFORE UPDATE ON inventory_pair_units FOR EACH ROW BEGIN IF NEW.code != OLD.code OR NEW.batch_id != OLD.batch_id OR OLD.state = 'dispatched' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La identidad del par despachado es inmutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_pair_units_no_delete BEFORE DELETE ON inventory_pair_units FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La identidad del par es inmutable'");
        }

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $trigger = 'inventory_pair_operations_no_'.strtolower($operation);

            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON inventory_pair_operations BEGIN SELECT RAISE(ABORT, 'El registro de picking es inmutable'); END");
            } elseif (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON inventory_pair_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El registro de picking es inmutable'");
            }
        }
    }

    public function down(): void
    {
        foreach (['inventory_supply_lots', 'inventory_expiry_settings', 'inventory_pair_operations', 'inventory_pair_units', 'inventory_tracked_batches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
