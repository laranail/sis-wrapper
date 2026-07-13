<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;

/**
 * The first thing anyone runs when "it says 403 and I don't know why." Prints the ability list, the current
 * resolver, and — with --actor=type:id — exactly what that actor may do. Canonical name
 * `laranail::sis-wrapper.permissions` (short alias `sis:permissions`).
 */
final class SisPermissionsCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['sis:permissions'];

    protected $signature = 'laranail::sis-wrapper.permissions {--actor= : Check an actor, given as type:id (e.g. user:1)}';

    protected $description = 'List SIS abilities, the current resolver, and what an actor can do.';

    public function handle(PermissionResolver $resolver): int
    {
        $this->line(__('sis::messages.commands.permissions.resolver', ['resolver' => $resolver::class]));

        $actor = $this->actorOption();
        $context = new AuthorizationContext(app(SisEngine::class)->class(SimClass::STANDARD));

        foreach (SisAbility::cases() as $ability) {
            if ($actor === null) {
                $this->line(__('sis::messages.commands.permissions.ability', [
                    'ability' => $ability->value,
                    'label' => $ability->label(),
                ]));

                continue;
            }

            $allowed = $resolver->allows($actor, $ability, $context);
            $this->line(__('sis::messages.commands.permissions.ability_actor', [
                'decision' => $allowed ? '<info>[allow]</info>' : '<fg=red>[deny]</>',
                'ability' => $ability->value,
                'label' => $ability->label(),
            ]));
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
