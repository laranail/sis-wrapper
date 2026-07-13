<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * Idempotency keys — §2.11, §2.13. The unique is on (actor_reference, idempotency_key), NEVER key alone:
 * a global key namespace lets one tenant replay another tenant's write. A key reused with a different
 * request hash is a 422, not a guess.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('idempotency_keys'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('actor_reference', 128);
            $table->string('idempotency_key', 255);
            $table->char('request_hash', 64);
            $table->longText('response')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('expires_at')->index();

            $table->unique(['actor_reference', 'idempotency_key'], 'sis_idempotency_actor_key_unique');
        });
    }

    public function down(): void
    {
        Schema::connection($this->sisConnection())->dropIfExists($this->sisTable('idempotency_keys'));
    }
};
