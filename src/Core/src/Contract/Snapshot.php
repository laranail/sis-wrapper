<?php

declare(strict_types=1);

namespace Simtabi\SIS\Contract;

/**
 * A snapshot is the immutable set of facts a decision needs from the register — and nothing more. It is
 * command-specific; a fat snapshot is a leaked query. The shell's SnapshotBuilder loads exactly the
 * fields each decider reads.
 */
interface Snapshot {}
