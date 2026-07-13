<?php

declare(strict_types=1);

namespace Simtabi\SIS\Contract;

/**
 * An effect is a DESCRIPTION of a write. The core returns effects; it never performs one. The shell's
 * registrar applies them inside a single transaction, in order.
 */
interface Effect {}
