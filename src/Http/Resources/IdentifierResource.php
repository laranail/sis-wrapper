<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Simtabi\Laranail\SIS\Models\SisRecord;

/**
 * The versioned, stable wire format for a register record — not "whatever toArray() did today." Changing a
 * field's shape is a breaking change (§2.12).
 *
 * @mixin SisRecord
 */
final class IdentifierResource extends JsonResource
{
    /** No "data" envelope — the wire format is the record itself. */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'class' => $this->class,
            'scope' => $this->scope,
            'serial' => $this->serial,
            'alias' => $this->alias,
            'state' => $this->state->value,
            'spec_edition' => $this->spec_edition,
            'subject' => $this->subject_type !== null
                ? ['type' => $this->subject_type, 'id' => $this->subject_id]
                : null,
            'superseded_by' => $this->superseded_by,
        ];
    }
}
