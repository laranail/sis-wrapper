<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\SIS\Services\SisManager;

/**
 * The `Sis` facade — the programmatic register API. It resolves the SisManager, so every stateful call runs
 * the same Action → registrar-decorator stack the HTTP layer uses.
 *
 * @method static \Simtabi\SIS\Identifier\Identifier reserve(\Simtabi\SIS\Profile\ClassDefinition|\Simtabi\SIS\Enums\SimClass|string $class, ?string $scope = null, string $reason = '', ?\Simtabi\SIS\Identifier\Actor $actor = null, int $width = 6)
 * @method static \Simtabi\SIS\Identifier\Identifier commission(\Simtabi\SIS\Identifier\Identifier $identifier, ?\Simtabi\SIS\Identifier\Alias $alias = null, string $description = '', ?\Simtabi\SIS\Identifier\SubjectRef $subject = null, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier suspend(\Simtabi\SIS\Identifier\Identifier $identifier, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier restore(\Simtabi\SIS\Identifier\Identifier $identifier, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier decommission(\Simtabi\SIS\Identifier\Identifier $identifier, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier transitionTo(\Simtabi\SIS\Identifier\Identifier $identifier, \Simtabi\SIS\Enums\LifecycleState $state, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier supersede(\Simtabi\SIS\Identifier\Identifier $identifier, \Simtabi\SIS\Identifier\Identifier $successor, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\SIS\Identifier\Identifier attachSubject(\Simtabi\SIS\Identifier\Identifier $identifier, \Simtabi\SIS\Identifier\SubjectRef $subject, ?\Simtabi\SIS\Identifier\Actor $actor = null)
 * @method static \Simtabi\Laranail\SIS\Models\SisRecord|null find(\Simtabi\SIS\Identifier\Identifier $identifier)
 * @method static \Simtabi\SIS\Identifier\Identifier|null resolveAlias(string $alias)
 * @method static \Simtabi\SIS\Identifier\Identifier|null resolveSubject(\Simtabi\SIS\Identifier\SubjectRef $subject)
 * @method static list<\Simtabi\SIS\Identifier\Identifier> chain(\Simtabi\SIS\Identifier\Identifier $identifier)
 * @method static \Simtabi\SIS\Identifier\Identifier terminalSuccessor(\Simtabi\SIS\Identifier\Identifier $identifier)
 * @method static \Simtabi\SIS\Command\Minter mint(\Simtabi\SIS\Profile\ClassDefinition|\Simtabi\SIS\Enums\SimClass|string $class)
 * @method static bool isValid(string $value)
 * @method static \Simtabi\SIS\Identifier\Identifier parse(string $value)
 * @method static \Simtabi\SIS\Profile\ClassDefinition|null classOf(string $value)
 * @method static \Simtabi\SIS\Identifier\AliasCandidates aliasCandidates(string $legalName)
 * @method static \Simtabi\SIS\Version\Version version(string $value)
 *
 * @see SisManager
 */
final class Sis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SisManager::class;
    }
}
