<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Http\Requests\Concerns\ReadsSisRequest;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * Commission a reserved identifier: optionally binding its alias and subject in the same act. The alias and
 * subject shapes are validated by the core value objects on the way in; whether they are free is decided
 * against the register. authorize() defers to the registrar stack, never an inline check.
 */
final class CommissionIdentifierRequest extends FormRequest
{
    use ReadsSisRequest;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'alias' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1024'],
            'subject' => ['nullable', 'array'],
            'subject.type' => ['required_with:subject', 'string', 'max:64'],
            'subject.id' => ['required_with:subject', 'string', 'max:64'],
        ];
    }

    public function toData(Actor $actor): CommissionData
    {
        $alias = $this->validated('alias');
        $subject = $this->validated('subject');

        return new CommissionData(
            identifier: $this->routeIdentifier(),
            actor: $actor,
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
            alias: is_string($alias) && $alias !== '' ? Alias::of($alias) : null,
            description: $this->validatedString('description'),
            subject: is_array($subject) && isset($subject['type'], $subject['id'])
                ? SubjectRef::of((string) $subject['type'], (string) $subject['id'])
                : null,
        );
    }
}
