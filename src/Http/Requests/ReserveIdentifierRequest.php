<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;

/**
 * authorize() defers to the Action/registrar (never an inline if); rules() composes framework rules; and
 * toData() converts to the DTO the Action takes — the controller never hand-builds a DTO. Whether the scope
 * matches the class is enforced by the DTO constructor (the real validation gate), so a ScopeMismatch here
 * surfaces as a problem+json.
 */
final class ReserveIdentifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'class' => ['required', Rule::enum(SimClass::class)],
            'scope' => ['nullable', 'string', 'max:6'],
            'reason' => ['required', 'string'],
            'width' => ['nullable', 'integer', 'min:6', 'max:9'],
        ];
    }

    public function toData(Actor $actor): ReserveData
    {
        $scope = $this->validated('scope');
        $width = $this->validated('width');

        return new ReserveData(
            class: app(SisEngine::class)->class($this->validatedString('class')),
            scope: is_string($scope) && $scope !== '' ? $scope : null,
            reason: $this->validatedString('reason'),
            actor: $actor,
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->stringAttribute('sis.correlation_id'),
            idempotencyKey: $this->stringAttribute('sis.idempotency_key'),
            width: is_numeric($width) ? (int) $width : 6,
        );
    }

    private function validatedString(string $key): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : '';
    }

    private function stringAttribute(string $key): string
    {
        $value = $this->attributes->get($key);

        return is_string($value) ? $value : '';
    }
}
