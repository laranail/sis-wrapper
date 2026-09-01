<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Simtabi\Laranail\SIS\Http\Requests\Concerns\ReadsSisRequest;
use Simtabi\SIS\Enums\LifecycleState;

/**
 * A lifecycle transition on a commissioned identifier (§6.2). Only the operator-driven targets are accepted
 * here — suspend, restore (→ commissioned), and decommission; reserving and voiding are separate acts.
 * Whether the transition is legal from the current state is decided against the register, not asserted here.
 */
final class TransitionIdentifierRequest extends FormRequest
{
    use ReadsSisRequest;

    private const array TARGETS = ['commissioned', 'suspended', 'decommissioned'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', Rule::in(self::TARGETS)],
        ];
    }

    public function targetState(): LifecycleState
    {
        return LifecycleState::from($this->validatedString('state'));
    }
}
