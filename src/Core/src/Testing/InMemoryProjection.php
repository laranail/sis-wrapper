<?php

declare(strict_types=1);

namespace Simtabi\SIS\Testing;

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
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\AssignAlias;
use Simtabi\SIS\Decision\ChangeState;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\DeleteRecord;
use Simtabi\SIS\Decision\InsertRecord;
use Simtabi\SIS\Decision\SetSubject;
use Simtabi\SIS\Decision\SetSupersededBy;
use Simtabi\SIS\Exception\UnknownIdentifierException;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Identifier\SubjectRef;
use Simtabi\SIS\Policy\AliasPolicy;
use Simtabi\SIS\Snapshot\AttachSubjectSnapshot;
use Simtabi\SIS\Snapshot\CommissionSnapshot;
use Simtabi\SIS\Snapshot\ReleaseSnapshot;
use Simtabi\SIS\Snapshot\ReserveSnapshot;
use Simtabi\SIS\Snapshot\SupersedeSnapshot;
use Simtabi\SIS\Snapshot\TransitionSnapshot;
use Simtabi\SIS\Snapshot\VoidSnapshot;

/**
 * A read-only-ish in-memory reference "shell": it builds the minimal snapshot each command needs and
 * applies a Decision's effects to a plain in-memory store. NOT for production — nothing persists, nothing
 * is atomic, and the storage-layer immutability guarantees live in the database, not here.
 */
final class InMemoryProjection implements Projection
{
    /** @var array<string, Identifier> comparable => identifier */
    private array $identifiers = [];

    /** @var array<string, LifecycleState> comparable => state */
    private array $states = [];

    /** @var array<string, string> alias => comparable */
    private array $aliasIndex = [];

    /** @var array<string, string> subject reference => comparable */
    private array $subjectIndex = [];

    /** @var array<string, string> comparable => successor comparable */
    private array $supersededBy = [];

    /** @var array<string, int> class|scope => highest serial issued */
    private array $serialCounters = [];

    /** @var list<AppendAudit> */
    private array $audit = [];

    public function nextSerial(IdClass $class, ?string $scope): int
    {
        $key = $class->value . '|' . ($scope ?? '');
        $start = $class->serialStart();
        $next = max(($this->serialCounters[$key] ?? $start - 1) + 1, $start);
        $this->serialCounters[$key] = $next;

        return $next;
    }

    public function snapshotFor(Command $command): Snapshot
    {
        return match (true) {
            $command instanceof Reserve => new ReserveSnapshot($this->exists($command->identifier)),
            $command instanceof Commission => new CommissionSnapshot(
                $this->requireState($command->identifier),
                $command->alias !== null && isset($this->aliasIndex[$command->alias->value]),
                $command->subject !== null && isset($this->subjectIndex[$command->subject->reference()]),
            ),
            $command instanceof Transition => new TransitionSnapshot($this->requireState($command->identifier)),
            $command instanceof Release => new ReleaseSnapshot($this->requireState($command->identifier)),
            $command instanceof VoidIdentifier => new VoidSnapshot($this->requireState($command->identifier)),
            $command instanceof AttachSubject => new AttachSubjectSnapshot(
                $this->requireState($command->identifier),
                isset($this->subjectIndex[$command->subject->reference()]),
            ),
            $command instanceof Supersede => new SupersedeSnapshot(
                $this->requireState($command->identifier),
                $this->exists($command->successor),
                $this->forwardChain($command->successor->comparable()),
            ),
            default => throw new LogicException('No snapshot for ' . $command::class),
        };
    }

    public function apply(Decision $decision): void
    {
        foreach ($decision->effects() as $effect) {
            if ($effect instanceof InsertRecord) {
                $key = $effect->identifier->comparable();
                $this->identifiers[$key] = $effect->identifier;
                $this->states[$key] = $effect->state;

                if ($effect->subject !== null) {
                    $this->subjectIndex[$effect->subject->reference()] = $key;
                }
            } elseif ($effect instanceof ChangeState) {
                $this->states[$effect->identifier->comparable()] = $effect->to;
            } elseif ($effect instanceof AssignAlias) {
                $this->aliasIndex[$effect->alias->value] = $effect->identifier->comparable();
            } elseif ($effect instanceof SetSubject) {
                $this->subjectIndex[$effect->subject->reference()] = $effect->identifier->comparable();
            } elseif ($effect instanceof SetSupersededBy) {
                $this->supersededBy[$effect->identifier->comparable()] = $effect->successor->comparable();
            } elseif ($effect instanceof DeleteRecord) {
                $this->forget($effect->identifier->comparable());
            } elseif ($effect instanceof AppendAudit) {
                $this->audit[] = $effect;
            }
        }
    }

    public function state(Identifier $identifier): ?LifecycleState
    {
        return $this->states[$identifier->comparable()] ?? null;
    }

    public function resolveAlias(string $alias): ?Identifier
    {
        $key = $this->aliasIndex[strtoupper($alias)] ?? null;

        return $key === null ? null : $this->identifiers[$key];
    }

    public function subjectIdentifier(SubjectRef $subject): ?Identifier
    {
        $key = $this->subjectIndex[$subject->reference()] ?? null;

        return $key === null ? null : $this->identifiers[$key];
    }

    /** @return list<AppendAudit> */
    public function auditTrail(): array
    {
        return $this->audit;
    }

    private function exists(Identifier $identifier): bool
    {
        return isset($this->states[$identifier->comparable()]);
    }

    private function requireState(Identifier $identifier): LifecycleState
    {
        return $this->states[$identifier->comparable()]
            ?? throw UnknownIdentifierException::of((string) $identifier);
    }

    /** @return list<string> comparables reachable forward from $comparable via supersession pointers */
    private function forwardChain(string $comparable): array
    {
        $chain = [];
        $seen = [$comparable => true];
        $cursor = $this->supersededBy[$comparable] ?? null;

        while ($cursor !== null && !isset($seen[$cursor])) {
            $chain[] = $cursor;
            $seen[$cursor] = true;
            $cursor = $this->supersededBy[$cursor] ?? null;
        }

        return $chain;
    }

    private function forget(string $comparable): void
    {
        unset($this->identifiers[$comparable], $this->states[$comparable], $this->supersededBy[$comparable]);

        foreach ($this->aliasIndex as $alias => $target) {
            if ($target === $comparable) {
                unset($this->aliasIndex[$alias]);
            }
        }

        foreach ($this->subjectIndex as $subject => $target) {
            if ($target === $comparable) {
                unset($this->subjectIndex[$subject]);
            }
        }
    }

    /** Reserved-alias awareness is the decider's job; exposed here only for the conformance suite. */
    public function isAliasReserved(string $alias): bool
    {
        return AliasPolicy::isReserved($alias);
    }
}
