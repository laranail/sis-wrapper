<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\SIS\Http\Controllers\AliasCandidatesController;
use Simtabi\Laranail\SIS\Http\Controllers\AttachSubjectController;
use Simtabi\Laranail\SIS\Http\Controllers\AuditController;
use Simtabi\Laranail\SIS\Http\Controllers\ChainController;
use Simtabi\Laranail\SIS\Http\Controllers\ClassesController;
use Simtabi\Laranail\SIS\Http\Controllers\CommissionController;
use Simtabi\Laranail\SIS\Http\Controllers\CompareVersionsController;
use Simtabi\Laranail\SIS\Http\Controllers\HealthController;
use Simtabi\Laranail\SIS\Http\Controllers\IdentifierController;
use Simtabi\Laranail\SIS\Http\Controllers\ResolveAliasController;
use Simtabi\Laranail\SIS\Http\Controllers\ResolveSubjectController;
use Simtabi\Laranail\SIS\Http\Controllers\SupersedeController;
use Simtabi\Laranail\SIS\Http\Controllers\TransitionController;
use Simtabi\Laranail\SIS\Http\Controllers\ValidateController;
use Simtabi\Laranail\SIS\Http\Middleware\CorrelationId;
use Simtabi\Laranail\SIS\Http\Middleware\RequireIdempotencyKey;

// Every route is named; SisServiceProvider's group prefixes the names with `sis.`, and the shared
// `identifiers.` segment is carried by the read/write groups' own `->name('identifiers.')` prefix — so a
// route named `show` inside them resolves as `sis.identifiers.show`. Reference them with
// route('sis.identifiers.show', $identifier) rather than hard-coding the (configurable) URL prefix.
Route::middleware(CorrelationId::class)->group(function (): void {
    // Stateless (pure core, no register).
    Route::post('validate', ValidateController::class)->name('validate');
    Route::get('alias-candidates', AliasCandidatesController::class)->name('alias-candidates');
    Route::get('classes', ClassesController::class)->name('classes');
    Route::post('versions/compare', CompareVersionsController::class)->name('versions.compare');
    Route::get('health', HealthController::class)->name('health');

    // Stateful reads (from the read model). Identifiers contain no slashes, so the default segment match
    // (which includes hyphens) is correct.
    Route::name('identifiers.')->group(function (): void {
        Route::get('identifiers/{identifier}', [IdentifierController::class, 'show'])->name('show');
        Route::get('identifiers/{identifier}/chain', ChainController::class)->name('chain');
        Route::get('identifiers/{identifier}/audit', AuditController::class)->name('audit');
    });
    Route::get('aliases/{alias}', ResolveAliasController::class)->name('aliases.resolve');
    Route::get('subjects', ResolveSubjectController::class)->name('subjects.resolve');

    // Stateful writes require an Idempotency-Key — a retry replays instead of acting twice.
    Route::middleware(RequireIdempotencyKey::class)->name('identifiers.')->group(function (): void {
        Route::post('identifiers', [IdentifierController::class, 'store'])->name('store');
        Route::post('identifiers/{identifier}/commission', CommissionController::class)->name('commission');
        Route::post('identifiers/{identifier}/transition', TransitionController::class)->name('transition');
        Route::post('identifiers/{identifier}/supersede', SupersedeController::class)->name('supersede');
        Route::post('identifiers/{identifier}/subject', AttachSubjectController::class)->name('subject');
    });
});
