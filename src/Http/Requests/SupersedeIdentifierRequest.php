<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\SIS\Http\Requests\Concerns\ReadsSisRequest;
use Simtabi\Laranail\SIS\Rules\ValidIdentifier;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Supersede an identifier with a successor (§8). The successor's shape is validated by ValidIdentifier;
 * that it exists, is not the same identifier, and forms no cycle is decided against the register.
 */
final class SupersedeIdentifierRequest extends FormRequest
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
            'successor' => ['required', 'string', new ValidIdentifier],
        ];
    }

    public function successor(): Identifier
    {
        $value = $this->validated('successor');

        return app(SisEngine::class)->parse(is_string($value) ? $value : '');
    }
}
