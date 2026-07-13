<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * We are out of room. The serial space for a class and scope is exhausted, or no memorable alias
 * remains. The HTTP surface renders these 507. Widening a serial is always safe; narrowing is
 * forbidden (§2, §10).
 */
abstract class SisCapacityException extends SisExceptionBase {}
