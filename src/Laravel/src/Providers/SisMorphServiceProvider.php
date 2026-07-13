<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\SIS\Morph\MorphAliasRegistry;
use Simtabi\Laranail\SIS\Services\MorphResolver;

/**
 * Boots FIRST, and has one job: enforce the morph map (§2.5). `Relation::enforceMorphMap()` registers the
 * governed alias map and turns on `requireMorphMap()`, so Eloquent throws the moment anything tries to
 * write a morph for an unmapped model. A raw fully-qualified class name in an immutable, never-deleted row
 * is a time bomb; the register outlives your namespace layout by design. Nothing writes a subject until
 * the map is enforced.
 */
final class SisMorphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MorphAliasRegistry::class, static function (): MorphAliasRegistry {
            /** @var array<string, class-string<Model>> $map */
            $map = (array) config('sis.morph.aliases', []);

            return new MorphAliasRegistry($map);
        });

        $this->app->singleton(MorphResolver::class);
    }

    public function boot(): void
    {
        // Merges with any existing morph map and turns on requireMorphMap(). From here, an unmapped morph
        // is a loud Eloquent failure, not a silently stored class name.
        Relation::enforceMorphMap($this->morphMap());
    }

    /** @return array<string, class-string<Model>> */
    private function morphMap(): array
    {
        /** @var array<string, class-string<Model>> $map */
        $map = (array) config('sis.morph.aliases', []);

        return $map;
    }
}
