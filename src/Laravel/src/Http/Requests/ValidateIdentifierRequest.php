<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The stateless validate endpoint touches no register, so it needs only that an identifier string was sent;
 * whether it is *valid* is what the endpoint answers.
 */
final class ValidateIdentifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
        ];
    }
}
