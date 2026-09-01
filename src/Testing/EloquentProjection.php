<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Testing;

use Simtabi\Laranail\SIS\Contract\SerialIssuer;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Registrar\EffectApplier;
use Simtabi\Laranail\SIS\Services\SnapshotBuilder;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;
use Simtabi\SIS\Profile\ClassDefinition;
use Simtabi\SIS\Testing\Projection;

/**
 * The Eloquent-backed implementation of the core's Projection seam. It exists so the shell can run the
 * exact same DeciderConformanceSuite as the in-memory core — the one thing that keeps the two halves from
 * drifting. If this passes, the shell's snapshot-building and effect-applying agree with the deciders.
 */
final class EloquentProjection implements Projection
{
    public function __construct(
        private readonly SnapshotBuilder $snapshots,
        private readonly EffectApplier $applier,
        private readonly SerialIssuer $serials,
    ) {}

    public function snapshotFor(Command $command): Snapshot
    {
        return $this->snapshots->for($command);
    }

    public function apply(Decision $decision): void
    {
        $this->applier->apply($decision);
    }

    public function state(Identifier $identifier): ?LifecycleState
    {
        $record = SisRecord::query()->find((string) $identifier);

        return $record instanceof SisRecord ? $record->state : null;
    }

    public function resolveAlias(string $alias): ?Identifier
    {
        $record = SisRecord::query()->where('alias', strtoupper($alias))->first();

        return $record instanceof SisRecord ? app(SisEngine::class)->parse($record->identifier) : null;
    }

    public function subjectIdentifier(SubjectRef $subject): ?Identifier
    {
        $record = SisRecord::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->first();

        return $record instanceof SisRecord ? app(SisEngine::class)->parse($record->identifier) : null;
    }

    public function nextSerial(ClassDefinition $class, ?string $scope): int
    {
        return $this->serials->next($class, $scope);
    }
}
