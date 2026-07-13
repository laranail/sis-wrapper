<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use LogicException;
use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Snapshot\AttachSubjectSnapshot;
use Simtabi\SIS\Snapshot\CommissionSnapshot;
use Simtabi\SIS\Snapshot\ReleaseSnapshot;
use Simtabi\SIS\Snapshot\ReserveSnapshot;
use Simtabi\SIS\Snapshot\SupersedeSnapshot;
use Simtabi\SIS\Snapshot\TransitionSnapshot;
use Simtabi\SIS\Snapshot\VoidSnapshot;

/**
 * The single entry point into the pure decision layer. It pairs each command with its decider and its
 * snapshot type. This is the whole surface the shell's registrar needs: load the snapshot, decide, apply.
 */
final class Decider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Command $command, Snapshot $snapshot): Decision
    {
        return match (true) {
            $command instanceof Reserve && $snapshot instanceof ReserveSnapshot => ReserveDecider::decide($command, $snapshot),
            $command instanceof Commission && $snapshot instanceof CommissionSnapshot => CommissionDecider::decide($command, $snapshot),
            $command instanceof Transition && $snapshot instanceof TransitionSnapshot => TransitionDecider::decide($command, $snapshot),
            $command instanceof Supersede && $snapshot instanceof SupersedeSnapshot => SupersedeDecider::decide($command, $snapshot),
            $command instanceof Release && $snapshot instanceof ReleaseSnapshot => ReleaseDecider::decide($command, $snapshot),
            $command instanceof VoidIdentifier && $snapshot instanceof VoidSnapshot => VoidDecider::decide($command, $snapshot),
            $command instanceof AttachSubject && $snapshot instanceof AttachSubjectSnapshot => AttachSubjectDecider::decide($command, $snapshot),
            default => throw new LogicException(
                sprintf('No decider pairs command %s with snapshot %s.', $command::class, $snapshot::class),
            ),
        };
    }
}
