<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\SIS\Http\Problem\ProblemRenderer;
use Simtabi\SIS\Contract\SisException;

/**
 * The HTTP surface is opt-in (§2.11): nothing is registered unless config('sis.api.enabled') is true. When
 * it is, the versioned routes load under the configured prefix and middleware (auth is the consumer's —
 * default auth:sanctum, deny otherwise), and SIS exceptions render as RFC 9457 problem+json.
 */
final class SisRouteServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__ . '/../..';

    public function boot(): void
    {
        if (!Config::boolean('sis.api.enabled', false)) {
            return;
        }

        $this->registerProblemRenderer();

        Route::group([
            'prefix' => Config::string('sis.api.prefix', 'api/sis/v1'),
            'middleware' => array_merge(
                Config::array('sis.api.middleware', ['api']),
                Config::array('sis.api.auth_middleware', []),
            ),
        ], fn () => $this->loadRoutesFrom(self::ROOT . '/routes/api.php'));
    }

    private function registerProblemRenderer(): void
    {
        // The application's exception handler (Foundation, or a dev decorator over it) exposes renderable().
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(static function (SisException $exception, Request $request): ?JsonResponse {
            if (!$request->expectsJson()) {
                return null;
            }

            $correlationId = $request->attributes->get('sis.correlation_id');
            $problem = (new ProblemRenderer)->render($exception, is_string($correlationId) ? $correlationId : null);

            return new JsonResponse($problem['body'], $problem['status'], ['Content-Type' => 'application/problem+json']);
        });
    }
}
