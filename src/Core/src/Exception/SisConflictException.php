<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * Someone else got there first. A taken alias, a serial collision, a subject already named. Whether
 * seen as an advisory precondition in a snapshot or as a unique-index violation, both paths raise the
 * same exception. The HTTP surface renders these 409.
 */
abstract class SisConflictException extends SisExceptionBase {}
