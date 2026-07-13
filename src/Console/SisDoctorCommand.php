<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Panel\PanelSupport;
use Simtabi\Laranail\SIS\Services\CapacityService;
use Simtabi\Laranail\SIS\Services\IntegrityService;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;

/**
 * "Is the register healthy?" — the first thing anyone runs when something is wrong, and the spine of the
 * runbook. It reports each check as OK / WARN / FAIL and exits non-zero if any check is a hard failure.
 *
 * Canonical name `laranail::sis-wrapper.doctor` (short alias `sis:doctor`).
 */
final class SisDoctorCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['sis:doctor'];

    protected $signature = 'laranail::sis-wrapper.doctor';

    protected $description = 'Check the health of the SIS register (schema, triggers, integrity, capacity).';

    public function handle(MorphAliasRegistry $morphs, IntegrityService $integrity, CapacityService $capacity): int
    {
        $failed = false;

        // 1. Schema present.
        $missing = $this->missingTables();
        if ($missing === []) {
            $this->ok(__('sis::messages.commands.doctor.schema_present'));
        } else {
            $failed = true;
            $this->problem(__('sis::messages.commands.doctor.schema_missing', ['tables' => implode(', ', $missing)]));
        }

        // 2. Storage-layer guarantee (the triggers) is enforced only on the trigger-capable drivers.
        $driver = $this->driver();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->ok(__('sis::messages.commands.doctor.triggers_supported', ['driver' => $driver]));
        } else {
            $this->warn(__('sis::messages.commands.doctor.triggers_unsupported', ['driver' => $driver]));
        }

        // 3. Check characters verify across a sample (corruption, or a bug in us).
        $corrupt = $integrity->sampleCorrupt();
        if ($corrupt === []) {
            $this->ok(__('sis::messages.commands.doctor.sample_clean'));
        } else {
            $failed = true;
            $this->problem(__('sis::messages.commands.doctor.sample_corrupt', ['identifiers' => implode(', ', array_slice($corrupt, 0, 5))]));
        }

        // 4. Every stored subject alias is resolvable through the morph map.
        $unknown = $this->unresolvableSubjectAliases($morphs);
        if ($unknown === []) {
            $this->ok(__('sis::messages.commands.doctor.aliases_resolve'));
        } else {
            $failed = true;
            $this->problem(__('sis::messages.commands.doctor.aliases_unknown', ['aliases' => implode(', ', $unknown)]));
        }

        // 5. Outbox lag.
        $pending = SisOutbox::query()->whereNull('relayed_at')->count();
        if ($pending === 0) {
            $this->ok(__('sis::messages.commands.doctor.outbox_drained'));
        } else {
            $this->warn(__('sis::messages.commands.doctor.outbox_pending', ['count' => $pending]));
        }

        // 6. Capacity headroom.
        $nearing = $capacity->nearingExhaustion();
        if ($nearing === []) {
            $this->ok(__('sis::messages.commands.doctor.capacity_headroom'));
        } else {
            foreach ($nearing as $space) {
                $this->warn(__('sis::messages.commands.doctor.capacity_nearing', [
                    'class' => $space['class'],
                    'scope' => $space['scope'] !== null ? ' scoped to ' . $space['scope'] : '',
                    'percent' => (int) round($space['usage'] * 100),
                ]));
            }
        }

        // 7. Admin panels (informational): where the RegisterPanelPresenter can be wired. SIS is headless, so
        // none is the norm — the JSON API and the Sis facade are the integration surface.
        $panels = PanelSupport::detected();
        $this->ok($panels === []
            ? __('sis::messages.commands.doctor.headless')
            : __('sis::messages.commands.doctor.panels', ['panels' => implode(', ', $panels)]));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function missingTables(): array
    {
        $prefix = Config::string('sis.database.prefix', 'sis_');
        $connection = $this->connection();
        $missing = [];

        foreach (['register', 'audit', 'outbox', 'idempotency_keys', 'serials'] as $table) {
            if (!Schema::connection($connection)->hasTable($prefix . $table)) {
                $missing[] = $prefix . $table;
            }
        }

        return $missing;
    }

    /** @return list<string> */
    private function unresolvableSubjectAliases(MorphAliasRegistry $morphs): array
    {
        $unknown = [];

        SisRecord::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->each(function (mixed $type) use ($morphs, &$unknown): void {
                if (is_string($type) && !$morphs->hasAlias($type)) {
                    $unknown[] = $type;
                }
            });

        return $unknown;
    }

    private function ok(string $message): void
    {
        $this->line("<info>[OK]</info> {$message}");
    }

    private function problem(string $message): void
    {
        $this->line("<fg=red>[FAIL]</> {$message}");
    }

    private function connection(): ?string
    {
        $connection = config('sis.database.connection');

        return is_string($connection) ? $connection : null;
    }

    private function driver(): string
    {
        return (string) Schema::connection($this->connection())->getConnection()->getDriverName();
    }
}
