<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * A caller error: a correct caller cannot hit these. Malformed input, an unknown class, a scope that
 * does not match the class. The HTTP surface renders these 400.
 */
abstract class SisLogicException extends SisExceptionBase {}
