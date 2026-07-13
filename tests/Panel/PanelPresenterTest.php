<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Panel;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\Laranail\SIS\Panel\PanelSupport;
use Simtabi\Laranail\SIS\Panel\RegisterPanelPresenter;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;

/** The headless panel bridge: legal-and-permitted action sets, a display row, and dependency-free detection. */
final class PanelPresenterTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
    }

    public function test_a_commissioned_record_offers_the_commissioned_lifecycle_actions(): void
    {
        $identifier = Sis::reserve(SimClass::CLIENT, reason: 'test');
        Sis::commission($identifier);
        $record = $this->app->make(SisReadModel::class)->find($identifier);
        self::assertNotNull($record);

        $actions = $this->presenter()->permittedActions($record, Actor::of('console', 'admin'));

        self::assertEqualsCanonicalizing(
            [SisAbility::Suspend, SisAbility::Decommission, SisAbility::Supersede],
            $actions,
        );
    }

    public function test_a_reserved_record_offers_the_reserved_actions(): void
    {
        $identifier = Sis::reserve(SimClass::CLIENT, reason: 'test');
        $record = $this->app->make(SisReadModel::class)->find($identifier);
        self::assertNotNull($record);

        $actions = $this->presenter()->permittedActions($record, Actor::of('console', 'admin'));

        self::assertEqualsCanonicalizing(
            [SisAbility::Commission, SisAbility::AttachSubject, SisAbility::Release],
            $actions,
        );

        $labelled = $this->presenter()->permittedActionLabels($record, Actor::of('console', 'admin'));

        self::assertContains(
            ['ability' => 'sis.identifier.commission', 'label' => 'Commission identifier'],
            $labelled,
        );
    }

    public function test_authorization_narrows_the_legal_actions(): void
    {
        $identifier = Sis::reserve(SimClass::CLIENT, reason: 'test');
        Sis::commission($identifier);
        $record = $this->app->make(SisReadModel::class)->find($identifier);
        self::assertNotNull($record);

        // A resolver that permits only Suspend must leave only Suspend, even though the state machine allows more.
        $presenter = new RegisterPanelPresenter(
            $this->app->make(SisReadModel::class),
            new class implements PermissionResolver
            {
                public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
                {
                    return $ability === SisAbility::Suspend;
                }
            },
        );

        self::assertSame([SisAbility::Suspend], $presenter->permittedActions($record, Actor::of('console', 'admin')));
    }

    public function test_present_exposes_a_stable_display_row(): void
    {
        $identifier = Sis::reserve(SimClass::CLIENT, reason: 'test');
        Sis::commission($identifier, app(SisEngine::class)->alias('ADIQ'));
        $record = $this->app->make(SisReadModel::class)->find($identifier);
        self::assertNotNull($record);

        $row = $this->presenter()->present($record);

        self::assertSame((string) $identifier, $row['identifier']);
        self::assertSame('CLT', $row['class']);
        self::assertSame('commissioned', $row['state']);
        self::assertSame('ADIQ', $row['alias']);
        self::assertArrayHasKey('class_label', $row);
    }

    public function test_detection_is_empty_when_no_panel_is_installed(): void
    {
        self::assertSame([], PanelSupport::detected());
        self::assertFalse(PanelSupport::filamentAvailable());
        self::assertFalse(PanelSupport::novaAvailable());
    }

    private function presenter(): RegisterPanelPresenter
    {
        return $this->app->make(RegisterPanelPresenter::class);
    }
}
