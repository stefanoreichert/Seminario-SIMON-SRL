<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_current_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('business_customers');
            $table->string('currency', 3);
            $table->unsignedTinyInteger('minor_scale');
            $table->timestamp('opened_at');
            $table->unique(['customer_id', 'currency']);
        });
        Schema::create('customer_current_account_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('customer_current_accounts');
            $table->foreignId('actor_id')->constrained('users');
            $table->uuid('operation_id')->unique();
            $table->string('kind', 30);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('source');
            $table->text('note');
            $table->string('request_digest', 64);
            $table->timestamp('recorded_at');
            $table->index(['account_id', 'id']);
        });

        foreach (['customer_current_accounts', 'customer_current_account_entries'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $trigger = $table.'_no_'.strtolower($operation);

                if (DB::getDriverName() === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'El historial financiero es inmutable'); END");
                } elseif (DB::getDriverName() === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial financiero es inmutable'");
                } else {
                    throw new RuntimeException('La protección financiera requiere MySQL o SQLite en esta entrega.');
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_current_account_entries');
        Schema::dropIfExists('customer_current_accounts');
    }
};
