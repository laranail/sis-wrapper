<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Policy\CapacityPolicy;
use Simtabi\SIS\Profile\ClassDefinition;

/**
 * How full each serial space is (§2.13). Reserving burns a serial permanently, so a human is warned before
 * a space is gone, not after. The core CapacityPolicy decides the fraction and the threshold; this reads
 * the highest issued serial per class and scope.
 */
final class CapacityService
{
    public function usage(ClassDefinition $class, ?string $scope, int $width = 6): float
    {
        $highest = SisRecord::query()
            ->where('class', $class->code)
            ->when(
                $scope !== null,
                fn ($query) => $query->where('scope', strtoupper($scope ?? '')),
                fn ($query) => $query->whereNull('scope'),
            )
            ->max('serial');

        $highest = is_numeric($highest) ? (int) $highest : 0;

        if ($highest < $class->serialStart()) {
            return 0.0;
        }

        return (new CapacityPolicy)->usage($class, $highest, $width);
    }

    /**
     * Every class+scope space at or beyond the warning threshold.
     *
     * @return list<array{class: string, scope: ?string, usage: float}>
     */
    public function nearingExhaustion(int $width = 6): array
    {
        $out = [];
        $classes = app(SisEngine::class)->classes();
        $policy = new CapacityPolicy;

        $rows = SisRecord::query()->toBase()
            ->select('class', 'scope')
            ->selectRaw('max(serial) as highest')
            ->groupBy('class', 'scope')
            ->get();

        foreach ($rows as $row) {
            $class = $classes->tryClass((string) $row->class);

            if ($class === null) {
                continue;
            }

            $usage = $policy->usage($class, (int) $row->highest, $width);

            if ($usage >= CapacityPolicy::DEFAULT_WARN_THRESHOLD) {
                $out[] = [
                    'class' => $class->code,
                    'scope' => $row->scope === null ? null : (string) $row->scope,
                    'usage' => $usage,
                ];
            }
        }

        return $out;
    }
}
