<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Outbox\OutboxStore;
use Simtabi\Laranail\SIS\Services\SnapshotBuilder;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Decision\Decision;

/**
 * The innermost registrar: load the snapshot, run the pure decider, apply its effects. It performs no
 * transaction, authorization, or logging of its own — those are decorators wrapped around it. The database
 * constraints and triggers are the authority; this applies the decision the core already validated.
 */
final class EloquentRegistrar implements Registrar
{
    public function __construct(
        private readonly SnapshotBuilder $snapshots,
        private readonly EffectApplier $applier,
        private readonly OutboxStore $outbox,
        private readonly SisEngine $engine,
    ) {}

    public function apply(Command $command): Decision
    {
        $decision = $this->engine->decide($command, $this->snapshots->for($command));

        $this->applier->apply($decision);
        $this->outbox->write($decision->events());

        return $decision;
    }
}
