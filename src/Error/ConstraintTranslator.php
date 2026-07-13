<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Error;

use Simtabi\Laranail\SIS\Exception\ImmutableWriteAttemptedException;
use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\SisException;
use Simtabi\SIS\Exception\AliasTakenException;
use Simtabi\SIS\Exception\CannotReleaseCommissionedException;
use Simtabi\SIS\Exception\RegisterCorruptionException;
use Simtabi\SIS\Exception\SerialCollisionException;
use Simtabi\SIS\Exception\SubjectAlreadyNamedException;
use Simtabi\SIS\Identifier\Identifier;
use Throwable;

/**
 * The database is the authority; this turns its violations back into the SAME core exceptions the advisory
 * preconditions raise (defence in depth, not a DRY violation). It matches on our own trigger tags and
 * constraint names, NEVER on locale-dependent message prose, and uses the command in flight to fill in the
 * specifics. Returns null when the failure is not one of ours, so the caller rethrows the original.
 */
final class ConstraintTranslator
{
    public function translate(Throwable $error, Command $command): ?SisException
    {
        $message = $error->getMessage();

        if (str_contains($message, '[sis:immutable]')) {
            return ImmutableWriteAttemptedException::of($error);
        }

        if (str_contains($message, '[sis:no-delete]')) {
            return CannotReleaseCommissionedException::of($this->identifier($command), 'commissioned');
        }

        if (str_contains($message, '[sis:audit-append-only]')) {
            return RegisterCorruptionException::of('sis_audit', 'append-only trigger fired');
        }

        if ($this->violated($message, 'sis_alias_unique')) {
            return AliasTakenException::of($command instanceof Commission && $command->alias !== null ? $command->alias->value : '(alias)');
        }

        if ($this->violated($message, 'sis_serial_unique') || $this->violated($message, 'sis_register_pkey')) {
            $id = $this->identifierObject($command);

            return $id === null
                ? null
                : SerialCollisionException::of($id->class->code, $id->scope, $id->serial);
        }

        if ($this->violated($message, 'sis_subject_unique')) {
            $subject = $command instanceof Commission ? $command->subject : ($command instanceof AttachSubject ? $command->subject : null);

            return $subject === null
                ? null
                : SubjectAlreadyNamedException::of($subject->type, $subject->id);
        }

        return null;
    }

    private function violated(string $message, string $constraint): bool
    {
        return str_contains($message, $constraint);
    }

    private function identifier(Command $command): string
    {
        $id = $this->identifierObject($command);

        return $id === null ? '(unknown)' : (string) $id;
    }

    private function identifierObject(Command $command): ?Identifier
    {
        return match (true) {
            $command instanceof Reserve,
            $command instanceof Commission,
            $command instanceof Transition,
            $command instanceof Supersede,
            $command instanceof Release,
            $command instanceof VoidIdentifier,
            $command instanceof AttachSubject => $command->identifier,
            default => null,
        };
    }
}
