<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The per-(class, scope) serial counters — SIM-STD-0001:2026 §9. Serial issuance must be atomic: two
 * callers must never receive the same serial. The DatabaseSerialIssuer increments a row here under a
 * row lock, so gaps are tolerated (a rolled-back reservation still advanced the counter) but reuse is
 * impossible — exactly the guarantee the spec requires.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('serials'), function (Blueprint $table): void {
            $table->char('class', 3);
            $table->string('scope_key', 6)->default('');
            $table->unsignedBigInteger('highest');
            $table->primary(['class', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->sisConnection())->dropIfExists($this->sisTable('serials'));
    }
};
