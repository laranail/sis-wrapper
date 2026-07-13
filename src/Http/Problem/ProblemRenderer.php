<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Problem;

use ReflectionClass;
use Simtabi\Laranail\SIS\Exception\IdempotencyConflictException;
use Simtabi\Laranail\SIS\Exception\UnauthorizedCommandException;
use Simtabi\SIS\Contract\SisException;
use Simtabi\SIS\Exception\SisCapacityException;
use Simtabi\SIS\Exception\SisConflictException;
use Simtabi\SIS\Exception\SisIntegrityException;
use Simtabi\SIS\Exception\SisStateException;

/**
 * Renders a SIS exception as RFC 9457 application/problem+json. The `type` URI is STABLE and a PUBLIC
 * CONTRACT — changing one is a breaking change — and resolves to an anchor in docs/errors.md. The body
 * carries only curated, user-safe fields: no SQLSTATE, table name, file path, or stack frame ever reaches
 * a caller (Part II rule 11).
 */
final class ProblemRenderer
{
    public const string TYPE_BASE = 'https://opensource.simtabi.com/documentation/laranail/sis-wrapper/errors';

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function render(SisException $exception, ?string $correlationId = null): array
    {
        $status = self::statusFor($exception);

        $body = [
            'type' => self::TYPE_BASE . '#' . self::slug($exception),
            'title' => self::titleFor($status),
            'status' => $status,
            'detail' => $exception->getMessage(),
            'spec_clause' => $exception->specClause(),
        ];

        if ($correlationId !== null) {
            $body['correlation_id'] = $correlationId;
        }

        return ['status' => $status, 'body' => $body];
    }

    private static function statusFor(SisException $exception): int
    {
        return match (true) {
            $exception instanceof UnauthorizedCommandException => 403,
            $exception instanceof IdempotencyConflictException => 422,
            $exception instanceof SisIntegrityException => 500,
            $exception instanceof SisConflictException, $exception instanceof SisStateException => 409,
            $exception instanceof SisCapacityException => 507,
            default => 400,
        };
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            403 => 'Forbidden',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            500 => 'Register Integrity Failure',
            507 => 'Insufficient Storage',
            default => 'Bad Request',
        };
    }

    private static function slug(SisException $exception): string
    {
        $short = (new ReflectionClass($exception))->getShortName();
        $short = (string) preg_replace('/Exception$/', '', $short);
        $kebab = (string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short);

        return strtolower($kebab);
    }
}
