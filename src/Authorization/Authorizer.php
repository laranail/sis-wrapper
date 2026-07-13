<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Illuminate\Support\Str;
use LogicException;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Enums\AuditVerdict;
use Simtabi\Laranail\SIS\Exception\UnauthorizedCommandException;
use Simtabi\Laranail\SIS\Services\AuditWriter;
use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Maps a command to the ability it requires and the class/scope context it acts in, then asks the
 * configured resolver. One decision point: policies and gates both funnel here. Authorization is checked
 * before the decider runs, so an unauthorised command never opens a transaction and never burns a serial.
 */
final class Authorizer
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditWriter $audit,
    ) {}

    public function authorize(Command $command): void
    {
        $ability = self::abilityFor($command);
        $identifier = self::identifierOf($command);

        $this->authorizeAbility(
            $command->actor(),
            $ability,
            new AuthorizationContext($identifier->class, $identifier->scope, $identifier),
            $command->correlationId(),
            $command->idempotencyKey(),
        );
    }

    /**
     * The pre-flight check an Action runs BEFORE issuing a serial, so an unauthorised actor never burns
     * one. The AuthorizingRegistrar re-checks the full command as defence in depth.
     *
     * A denial is recorded to the audit trail (verdict Denied) before the exception is thrown, so a rejected
     * attempt leaves a trace. Transaction caveat: a pre-flight denial here (in an Action, with no active
     * transaction) persists; the rare AuthorizingRegistrar-level denial runs INSIDE the write transaction and
     * so rolls back with it. That is acceptable — the pre-flight check is the guaranteed early catch for real
     * denials, and it is the one that persists.
     */
    public function authorizeAbility(
        Actor $actor,
        SisAbility $ability,
        AuthorizationContext $context,
        string $correlationId = '',
        string $idempotencyKey = '',
    ): void {
        if (!$this->resolver->allows($actor, $ability, $context)) {
            $this->audit->write(
                identifier: $context->record !== null ? (string) $context->record : null,
                action: 'authorize',
                actor: $actor,
                before: null,
                after: null,
                ability: $ability,
                verdict: AuditVerdict::Denied,
                correlationId: $this->resolveCorrelationId($correlationId),
                idempotencyKey: $idempotencyKey,
                context: ['operation' => 'authorize', 'ability' => $ability->value],
                at: now(),
            );

            throw UnauthorizedCommandException::of($actor, $ability->value);
        }
    }

    /**
     * The correlation id for a deny-audit row. `correlation_id` is NOT NULL, so it is always populated: the
     * caller's id if it has one, else the request-scoped id an outer middleware set, else a fresh uuid.
     */
    private function resolveCorrelationId(string $correlationId): string
    {
        if ($correlationId !== '') {
            return $correlationId;
        }

        $fromRequest = request()->attributes->get('sis.correlation_id');

        if (is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        return (string) Str::uuid();
    }

    private static function abilityFor(Command $command): SisAbility
    {
        return match (true) {
            $command instanceof Reserve => SisAbility::Reserve,
            $command instanceof Commission => SisAbility::Commission,
            $command instanceof Transition => match ($command->to) {
                LifecycleState::Suspended => SisAbility::Suspend,
                LifecycleState::Decommissioned => SisAbility::Decommission,
                default => SisAbility::Restore,
            },
            $command instanceof Supersede => SisAbility::Supersede,
            $command instanceof Release, $command instanceof VoidIdentifier => SisAbility::Release,
            $command instanceof AttachSubject => SisAbility::AttachSubject,
            default => throw new LogicException('No ability mapped for ' . $command::class),
        };
    }

    private static function identifierOf(Command $command): Identifier
    {
        return match (true) {
            $command instanceof Reserve,
            $command instanceof Commission,
            $command instanceof Transition,
            $command instanceof Supersede,
            $command instanceof Release,
            $command instanceof VoidIdentifier,
            $command instanceof AttachSubject => $command->identifier,
            default => throw new LogicException('No identifier on ' . $command::class),
        };
    }
}
