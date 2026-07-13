<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Illuminate\Console\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\IdClass;

/**
 * The first thing anyone runs when "it says 403 and I don't know why." Prints the ability list, the current
 * resolver, and — with --actor=type:id — exactly what that actor may do. Canonical name
 * `laranail::sis-wrapper.permissions` (short alias `sis:permissions`).
 */
final class SisPermissionsCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::sis-wrapper.permissions {--actor= : Check an actor, given as type:id (e.g. user:1)}';

    protected $description = 'List SIS abilities, the current resolver, and what an actor can do.';

    public function __construct()
    {
        parent::__construct();
        $this->setAliases(['sis:permissions']);
    }

    public function handle(PermissionResolver $resolver): int
    {
        $this->line('Resolver: <info>' . $resolver::class . '</info>');

        $actor = $this->actorOption();
        $context = new AuthorizationContext(IdClass::Standard);

        foreach (SisAbility::cases() as $ability) {
            if ($actor === null) {
                $this->line('  ' . $ability->value);

                continue;
            }

            $allowed = $resolver->allows($actor, $ability, $context);
            $this->line(sprintf('  %s %s', $allowed ? '<info>[allow]</info>' : '<fg=red>[deny]</>', $ability->value));
        }

        return self::SUCCESS;
    }

    private function actorOption(): ?Actor
    {
        $option = $this->option('actor');

        if (!is_string($option) || !str_contains($option, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $option, 2);

        return Actor::of($type, $id);
    }
}
