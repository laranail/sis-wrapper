<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Profile\ClassDefinition;

/**
 * The granularity that matters, without a 308-permission matrix: the ability is the action; the class and
 * the scope are arguments. Class-level ("commission invoices, not people") and — critically — scope-level
 * ("commission for ADIQ, not ADLS"). The scope check is the multi-tenant control: without it, any user who
 * can raise an invoice can raise it against any client in the company.
 */
final readonly class AuthorizationContext
{
    public function __construct(
        public ClassDefinition $class,
        public ?string $scope = null,
        public ?Identifier $record = null,
    ) {}
}
