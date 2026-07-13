<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Enums\LifecycleState;

/** Validates that a target state is a legal transition from a given state (§6.2), via the state machine. */
final class ValidLifecycleTransition implements ValidationRule
{
    public function __construct(
        private readonly LifecycleState $from,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $to = is_string($value) ? LifecycleState::tryFrom($value) : null;

        if ($to === null || !$this->from->canTransitionTo($to)) {
            $fail('sis::validation.illegal_transition')->translate(['from' => $this->from->value]);
        }
    }
}
