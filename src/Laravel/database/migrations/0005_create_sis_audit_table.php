<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The append-only audit trail — SIM-STD-0001:2026 §9, §2.9. One row per applied effect, with the actor by
 * reference (never a name or email), the ability checked and the resolver's verdict, and a hash chain so
 * tampering under the append-only trigger is detectable. down() throws: you cannot reverse an append-only
 * table without destroying the trail. migrate:fresh is the only reset, and it is a schema operation.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('audit'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('identifier', 40)->index();
            $table->string('action', 64);
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('before_state', 16)->nullable();
            $table->string('after_state', 16)->nullable();
            $table->string('ability', 64)->nullable();
            $table->string('verdict', 16)->nullable();
            $table->string('correlation_id', 64)->index();
            $table->string('idempotency_key', 255)->nullable();
            $table->json('context')->nullable();
            $table->char('hash', 64)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'sis_audit is append-only; it cannot be rolled back without destroying the audit trail. '
            . 'Use migrate:fresh (a schema reset) if you must start over.',
        );
    }
};
