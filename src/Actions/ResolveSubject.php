<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/** Reverse lookup: which identifier names this thing? (§2.5). A read; authorize it as hard as a write (§2.13). */
final class ResolveSubject
{
    public function __construct(
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(SubjectRef $subject): ?Identifier
    {
        return $this->read->bySubject($subject)?->identifier();
    }
}
