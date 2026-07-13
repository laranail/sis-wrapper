<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The morph alias allocations, recorded so the mapping is auditable and cannot be quietly edited in a
 * config file (decision D4). Config resolves; this table remembers. Append-only — down() throws, because
 * a reassigned alias is forbidden, not rolled back.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('morph_aliases'), function (Blueprint $table): void {
            $table->string('alias', 64)->primary();
            $table->string('model_class', 255);
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'sis_morph_aliases is append-only: a morph alias, once allocated, is never reassigned. '
            . 'Use migrate:fresh if you must reset the schema.',
        );
    }
};
