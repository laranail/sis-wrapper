<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Security;

/**
 * Redacts secrets and credentials from any structured payload before it reaches a log, a report, or an
 * exception context (Part II rule 15). Actor references and identifiers are business data and may appear;
 * secrets, tokens, and credentials never do.
 */
final class Redactor
{
    private const array SENSITIVE = [
        'secret', 'password', 'passwd', 'token', 'authorization', 'api_key', 'apikey',
        'credential', 'private_key', 'signature',
    ];

    private const string MASK = '[REDACTED]';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $out[$key] = self::MASK;
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $out[$key] = $this->redact($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
