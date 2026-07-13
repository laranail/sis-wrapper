<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The transactional outbox — §2.7. Events are written here in the same transaction as the effects, then
 * relayed after commit. Relay is at-least-once, so every listener must be idempotent.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('outbox'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_type', 128);
            $table->string('identifier', 40)->nullable()->index();
            $table->json('payload');
            $table->string('correlation_id', 64)->index();
            $table->timestampTz('available_at')->index();
            $table->timestampTz('relayed_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->sisConnection())->dropIfExists($this->sisTable('outbox'));
    }
};
