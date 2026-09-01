<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Psr\Log\LoggerInterface;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Contract\SisException;
use Simtabi\SIS\Decision\Decision;
use Throwable;

/**
 * The outermost decorator: every command and every failure is reported through the central handler. A log
 * line is structured, carries the correlation id, and — for a SIS exception — its context(). It never
 * swallows: it logs and rethrows.
 */
final class LoggingRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function apply(Command $command): Decision
    {
        try {
            $decision = $this->inner->apply($command);

            $this->logger->info('sis.command.applied', [
                'command' => $command::class,
                'correlation_id' => $command->correlationId(),
                'actor' => $command->actor()->reference(),
            ]);

            return $decision;
        } catch (SisException $e) {
            $this->logger->log($e->criticality() === 'critical' ? 'error' : 'warning', 'sis.command.failed', $e->context());

            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('sis.command.error', [
                'command' => $command::class,
                'correlation_id' => $command->correlationId(),
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
