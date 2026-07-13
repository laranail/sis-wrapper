<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use LogicException;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Exception\UnauthorizedCommandException;
use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * Maps a command to the ability it requires and the class/scope context it acts in, then asks the
 * configured resolver. One decision point: policies and gates both funnel here. Authorization is checked
 * before the decider runs, so an unauthorised command never opens a transaction and never burns a serial.
 */
final class Authorizer
{
    public function __construct(
        private readonly PermissionResolver $resolver,
    ) {}

    public function authorize(Command $command): void
    {
        $ability = self::abilityFor($command);
        $identifier = self::identifierOf($command);

        $this->authorizeAbility($command->actor(), $ability, new AuthorizationContext($identifier->class, $identifier->scope, $identifier));
    }

    /**
     * The pre-flight check an Action runs BEFORE issuing a serial, so an unauthorised actor never burns
     * one. The AuthorizingRegistrar re-checks the full command as defence in depth.
     */
    public function authorizeAbility(Actor $actor, SisAbility $ability, AuthorizationContext $context): void
    {
        if (!$this->resolver->allows($actor, $ability, $context)) {
            throw UnauthorizedCommandException::of($actor, $ability->value);
        }
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
