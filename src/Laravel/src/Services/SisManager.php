<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Simtabi\Laranail\SIS\Actions\AttachSubject;
use Simtabi\Laranail\SIS\Actions\CommissionIdentifier;
use Simtabi\Laranail\SIS\Actions\ReserveIdentifier;
use Simtabi\Laranail\SIS\Actions\ResolveAlias;
use Simtabi\Laranail\SIS\Actions\ResolveSubject;
use Simtabi\Laranail\SIS\Actions\SupersedeIdentifier;
use Simtabi\Laranail\SIS\Actions\TraceSupersessionChain;
use Simtabi\Laranail\SIS\Actions\TransitionIdentifier;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Command\Minter;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\AliasCandidates;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Identifier\SubjectRef;
use Simtabi\SIS\Sis as Core;
use Simtabi\SIS\Version\Version;

/**
 * The programmatic entry point behind the `Sis` facade: the full register API without going through HTTP.
 * Every stateful call runs the same Action → registrar-decorator stack the controllers use, so authorization,
 * transactions, audit, and the outbox apply identically. Read helpers delegate to the read model; the pure
 * grammar/check/alias/version helpers pass straight through to the zero-dependency core.
 */
final class SisManager
{
    public function __construct(
        private readonly ReserveIdentifier $reserve,
        private readonly CommissionIdentifier $commission,
        private readonly TransitionIdentifier $transition,
        private readonly SupersedeIdentifier $supersede,
        private readonly AttachSubject $attach,
        private readonly ResolveAlias $resolveAlias,
        private readonly ResolveSubject $resolveSubject,
        private readonly TraceSupersessionChain $trace,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
    ) {}

    // --- Stateful register operations (through the registrar stack) --------------------------------

    public function reserve(IdClass $class, ?string $scope = null, string $reason = '', ?Actor $actor = null, int $width = 6): Identifier
    {
        return ($this->reserve)(new ReserveData(
            class: $class,
            scope: $scope,
            reason: $reason,
            actor: $this->actor($actor),
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
            width: $width,
        ));
    }

    public function commission(Identifier $identifier, ?Alias $alias = null, string $description = '', ?SubjectRef $subject = null, ?Actor $actor = null): Identifier
    {
        return ($this->commission)(new CommissionData(
            identifier: $identifier,
            actor: $this->actor($actor),
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
            alias: $alias,
            description: $description,
            subject: $subject,
        ));
    }

    public function suspend(Identifier $identifier, ?Actor $actor = null): Identifier
    {
        return $this->transition->suspend($identifier, $this->context($actor));
    }

    public function restore(Identifier $identifier, ?Actor $actor = null): Identifier
    {
        return $this->transition->restore($identifier, $this->context($actor));
    }

    public function decommission(Identifier $identifier, ?Actor $actor = null): Identifier
    {
        return $this->transition->decommission($identifier, $this->context($actor));
    }

    public function transitionTo(Identifier $identifier, LifecycleState $state, ?Actor $actor = null): Identifier
    {
        return $this->transition->to($identifier, $state, $this->context($actor));
    }

    public function supersede(Identifier $identifier, Identifier $successor, ?Actor $actor = null): Identifier
    {
        return ($this->supersede)($identifier, $successor, $this->context($actor));
    }

    public function attachSubject(Identifier $identifier, SubjectRef $subject, ?Actor $actor = null): Identifier
    {
        return ($this->attach)($identifier, $subject, $this->context($actor));
    }

    // --- Reads -------------------------------------------------------------------------------------

    public function find(Identifier $identifier): ?SisRecord
    {
        return $this->read->find($identifier);
    }

    public function resolveAlias(string $alias): ?Identifier
    {
        return ($this->resolveAlias)($alias);
    }

    public function resolveSubject(SubjectRef $subject): ?Identifier
    {
        return ($this->resolveSubject)($subject);
    }

    /** @return list<Identifier> the supersession chain forward, terminal successor last */
    public function chain(Identifier $identifier): array
    {
        return ($this->trace)($identifier);
    }

    public function terminalSuccessor(Identifier $identifier): Identifier
    {
        return $this->trace->terminal($identifier);
    }

    // --- Pure passthroughs to the zero-dependency core ---------------------------------------------

    public function mint(IdClass $class): Minter
    {
        return Core::mint($class);
    }

    public function isValid(string $value): bool
    {
        return Core::validate($value);
    }

    public function parse(string $value): Identifier
    {
        return Core::parse($value);
    }

    public function classOf(string $value): ?IdClass
    {
        return Core::identify($value);
    }

    public function aliasCandidates(string $legalName): AliasCandidates
    {
        return Core::aliasCandidates($legalName);
    }

    public function version(string $value): Version
    {
        return Core::version($value);
    }

    // --- Envelope construction ---------------------------------------------------------------------

    private function context(?Actor $actor): CommandContext
    {
        return new CommandContext(
            actor: $this->actor($actor),
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
        );
    }

    private function actor(?Actor $actor): Actor
    {
        return $actor ?? $this->actors->current();
    }

    private function correlationId(): string
    {
        return (string) Str::uuid();
    }

    private function idempotencyKey(): string
    {
        return (string) Str::uuid();
    }
}
