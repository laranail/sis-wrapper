<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\SIS\Contract\Registrar;

/**
 * Assembles the registrar decorator stack from config. The documented order is the default, not a law — a
 * consumer may insert their own decorator by editing config('sis.registrar.stack'). The list is outermost
 * first; the innermost (EloquentRegistrar) is built with no inner, then each decorator wraps it.
 */
final class RegistrarFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(): Registrar
    {
        /** @var list<class-string<Registrar>> $stack */
        $stack = config('sis.registrar.stack', [EloquentRegistrar::class]);

        $registrar = null;

        foreach (array_reverse($stack) as $class) {
            $registrar = $registrar === null
                ? $this->container->make($class)
                : $this->container->make($class, ['inner' => $registrar]);
        }

        if (!$registrar instanceof Registrar) {
            $registrar = $this->container->make(EloquentRegistrar::class);
        }

        return $registrar;
    }
}
