<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Morph\MorphAliasRegistry;
use Simtabi\Laranail\SIS\Panel\PanelSupport;
use Simtabi\Laranail\SIS\Services\CapacityService;
use Simtabi\Laranail\SIS\Services\IntegrityService;

/**
 * "Is the register healthy?" — the first thing anyone runs when something is wrong, and the spine of the
 * runbook. It reports each check as OK / WARN / FAIL and exits non-zero if any check is a hard failure.
 *
 * Canonical name `laranail::sis-wrapper.doctor` (short alias `sis:doctor`).
 */
final class SisDoctorCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::sis-wrapper.doctor';

    protected $description = 'Check the health of the SIS register (schema, triggers, integrity, capacity).';

    public function __construct()
    {
        parent::__construct();
        $this->setAliases(['sis:doctor']);
    }

    public function handle(MorphAliasRegistry $morphs, IntegrityService $integrity, CapacityService $capacity): int
    {
        $failed = false;

        // 1. Schema present.
        $missing = $this->missingTables();
        if ($missing === []) {
            $this->ok('schema present');
        } else {
            $failed = true;
            $this->problem('schema missing tables: ' . implode(', ', $missing));
        }

        // 2. Storage-layer guarantee (the triggers) is enforced only on the trigger-capable drivers.
        $driver = $this->driver();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->ok("storage-layer triggers supported on driver '{$driver}'");
        } else {
            $this->warn("[WARN] storage-layer immutability is NOT enforced on driver '{$driver}' — not for production");
        }

        // 3. Check characters verify across a sample (corruption, or a bug in us).
        $corrupt = $integrity->sampleCorrupt();
        if ($corrupt === []) {
            $this->ok('no check-character failures in the sample');
        } else {
            $failed = true;
            $this->problem('corrupt identifiers: ' . implode(', ', array_slice($corrupt, 0, 5)));
        }

        // 4. Every stored subject alias is resolvable through the morph map.
        $unknown = $this->unresolvableSubjectAliases($morphs);
        if ($unknown === []) {
            $this->ok('every stored subject alias resolves');
        } else {
            $failed = true;
            $this->problem('unknown morph aliases in the register: ' . implode(', ', $unknown));
        }

        // 5. Outbox lag.
        $pending = SisOutbox::query()->whereNull('relayed_at')->count();
        if ($pending === 0) {
            $this->ok('outbox drained');
        } else {
            $this->warn("[WARN] {$pending} outbox message(s) pending relay");
        }

        // 6. Capacity headroom.
        $nearing = $capacity->nearingExhaustion();
        if ($nearing === []) {
            $this->ok('capacity headroom across all spaces');
        } else {
            foreach ($nearing as $space) {
                $this->warn(sprintf('[WARN] %s%s is %d%% through its serial space', $space['class'], $space['scope'] !== null ? ' scoped to ' . $space['scope'] : '', (int) round($space['usage'] * 100)));
            }
        }

        // 7. Admin panels (informational): where the RegisterPanelPresenter can be wired. SIS is headless, so
        // none is the norm — the JSON API and the Sis facade are the integration surface.
        $panels = PanelSupport::detected();
        $this->ok($panels === []
            ? 'headless — no admin panel detected (JSON API and facade are the integration surface)'
            : 'admin panel(s) available for the register presenter: ' . implode(', ', $panels));

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
