<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * The data is wrong. A check character that does not verify, a corrupt register row. This is the
 * alarm: a human reads it today. The HTTP surface renders these 500 and they page.
 */
abstract class SisIntegrityException extends SisExceptionBase {}
