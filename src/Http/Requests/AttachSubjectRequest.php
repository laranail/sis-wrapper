<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\SIS\Http\Requests\Concerns\ReadsSisRequest;
use Simtabi\Laranail\SIS\Rules\KnownMorphAlias;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * Attach the polymorphic subject to a still-reserved identifier (§5, §9). The type must be a mapped morph
 * alias — a raw class name never crosses the wire; "one thing, one identifier" is enforced by the decider.
 */
final class AttachSubjectRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:64', app(KnownMorphAlias::class)],
            'id' => ['required', 'string', 'max:64'],
        ];
    }

    public function subject(): SubjectRef
    {
        return SubjectRef::of($this->validatedString('type'), $this->validatedString('id'));
    }
}
