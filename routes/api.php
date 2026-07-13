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

Route::middleware(CorrelationId::class)->group(function (): void {
    // Stateless (pure core, no register).
    Route::post('validate', ValidateController::class);
    Route::get('alias-candidates', AliasCandidatesController::class);
    Route::get('classes', ClassesController::class);
    Route::post('versions/compare', CompareVersionsController::class);
    Route::get('health', HealthController::class);

    // Stateful reads (from the read model). Identifiers contain no slashes, so the default segment match
    // (which includes hyphens) is correct.
    Route::get('identifiers/{identifier}', [IdentifierController::class, 'show']);
    Route::get('identifiers/{identifier}/chain', ChainController::class);
    Route::get('identifiers/{identifier}/audit', AuditController::class);
    Route::get('aliases/{alias}', ResolveAliasController::class);
    Route::get('subjects', ResolveSubjectController::class);

    // Stateful writes require an Idempotency-Key — a retry replays instead of acting twice.
    Route::middleware(RequireIdempotencyKey::class)->group(function (): void {
        Route::post('identifiers', [IdentifierController::class, 'store']);
        Route::post('identifiers/{identifier}/commission', CommissionController::class);
        Route::post('identifiers/{identifier}/transition', TransitionController::class);
        Route::post('identifiers/{identifier}/supersede', SupersedeController::class);
        Route::post('identifiers/{identifier}/subject', AttachSubjectController::class);
    });
});
