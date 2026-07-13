<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * The state forbade it. An illegal lifecycle transition, releasing a commissioned identifier, an
 * operation against a terminal record. The HTTP surface renders these 409.
 */
abstract class SisStateException extends SisExceptionBase {}
