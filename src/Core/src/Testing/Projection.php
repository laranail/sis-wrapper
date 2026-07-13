<?php

declare(strict_types=1);

namespace Simtabi\SIS\Testing;

use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * The seam the DeciderConformanceSuite drives. The core ships an in-memory implementation; the shell's
 * Eloquent registrar is expected to pass the same suite through an equivalent projection. Write the suite
 * once — it is the only thing preventing the two halves from drifting.
 */
interface Projection
{
    public function snapshotFor(Command $command): Snapshot;

    public function apply(Decision $decision): void;

    public function state(Identifier $identifier): ?LifecycleState;

    public function resolveAlias(string $alias): ?Identifier;

    public function subjectIdentifier(SubjectRef $subject): ?Identifier;

    public function nextSerial(IdClass $class, ?string $scope): int;
}
