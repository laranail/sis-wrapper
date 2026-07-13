<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use LogicException;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Exception\UnknownIdentifierException;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Identifier\SubjectRef;
use Simtabi\SIS\Snapshot\AttachSubjectSnapshot;
use Simtabi\SIS\Snapshot\CommissionSnapshot;
use Simtabi\SIS\Snapshot\ReleaseSnapshot;
use Simtabi\SIS\Snapshot\ReserveSnapshot;
use Simtabi\SIS\Snapshot\SupersedeSnapshot;
use Simtabi\SIS\Snapshot\TransitionSnapshot;
use Simtabi\SIS\Snapshot\VoidSnapshot;

/**
 * Loads the minimal snapshot each command needs from the register — and nothing more. A fat snapshot is a
 * leaked query. This is the shell half of the seam: the ranking, transition legality, and cycle detection
 * stay in the core; the queries stay here.
 */
final class SnapshotBuilder
{
    public function for(Command $command): Snapshot
    {
        return match (true) {
            $command instanceof Reserve => new ReserveSnapshot($this->exists($command->identifier)),
            $command instanceof Commission => new CommissionSnapshot(
                $this->requireState($command->identifier),
                $command->alias !== null && $this->aliasTaken($command->alias->value),
                $command->subject !== null && $this->subjectNamed($command->subject),
            ),
            $command instanceof Transition => new TransitionSnapshot($this->requireState($command->identifier)),
            $command instanceof Release => new ReleaseSnapshot($this->requireState($command->identifier)),
            $command instanceof VoidIdentifier => new VoidSnapshot($this->requireState($command->identifier)),
            $command instanceof AttachSubject => new AttachSubjectSnapshot(
                $this->requireState($command->identifier),
                $this->subjectNamed($command->subject),
            ),
            $command instanceof Supersede => new SupersedeSnapshot(
                $this->requireState($command->identifier),
                $this->exists($command->successor),
                $this->forwardChain($command->successor),
            ),
            default => throw new LogicException('No snapshot builder for ' . $command::class),
        };
    }

    private function exists(Identifier $identifier): bool
    {
        return SisRecord::query()->whereKey((string) $identifier)->exists();
    }

    private function requireState(Identifier $identifier): LifecycleState
    {
        $record = SisRecord::query()->find((string) $identifier);

        if (!$record instanceof SisRecord) {
            throw UnknownIdentifierException::of((string) $identifier);
        }

        return $record->state;
    }

    private function aliasTaken(string $alias): bool
    {
        return SisRecord::query()->where('alias', $alias)->exists();
    }

    private function subjectNamed(SubjectRef $subject): bool
    {
        return SisRecord::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->exists();
    }

    /** @return list<string> comparables reachable forward from the successor via supersession pointers */
    private function forwardChain(Identifier $successor): array
    {
        $chain = [];
        $seen = [$successor->comparable() => true];
        $cursor = $this->supersededBy((string) $successor);

        while ($cursor !== null) {
            $comparable = Identifier::parse($cursor)->comparable();

            if (isset($seen[$comparable])) {
                break;
            }

            $chain[] = $comparable;
            $seen[$comparable] = true;
            $cursor = $this->supersededBy($cursor);
        }

        return $chain;
    }

    private function supersededBy(string $identifier): ?string
    {
        return SisRecord::query()->where('identifier', $identifier)->first()?->superseded_by;
    }
}
