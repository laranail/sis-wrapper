<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * Webhook endpoints — §2.11, §2.13. The secret is encrypted at rest (an encrypted cast on the model) and
 * is write-only over the API: accepted on create, never returned, regenerated rather than read. The owner
 * is an optional polymorphic reference under the same morph map.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('webhook_endpoints'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->string('owner_type', 64)->nullable();
            $table->string('owner_id', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->string('circuit_state', 16)->default('closed');
            $table->timestampTz('circuit_opened_at')->nullable();
            $table->unsignedInteger('failures')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::connection($this->sisConnection())->dropIfExists($this->sisTable('webhook_endpoints'));
    }
};
