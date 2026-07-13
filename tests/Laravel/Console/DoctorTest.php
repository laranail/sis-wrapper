<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Console;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;

final class DoctorTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    public function test_doctor_reports_healthy_on_a_fresh_register(): void
    {
        $this->artisan('sis:doctor')->assertExitCode(0);
    }

    public function test_the_canonical_namespaced_name_dispatches(): void
    {
        // The laranail convention name carries the `::` empty segment Symfony would normally reject; the
        // SupportsNamespacedNames trait writes it past the validator, and it must still dispatch.
        $this->artisan('laranail::sis-wrapper.doctor')->assertExitCode(0);
    }

    public function test_doctor_reports_the_headless_panel_status(): void
    {
        // No admin panel is installed in the test environment, so the informational check reports headless.
        $this->artisan('sis:doctor')
            ->expectsOutputToContain('headless')
            ->assertExitCode(0);
    }

    public function test_doctor_fails_on_a_corrupt_identifier(): void
    {
        // Insert a row whose check characters do not verify (XX is not the real check for this core). SQLite
        // has no CHECK/trigger, so a corrupt row can exist — which is exactly what the doctor must catch.
        DB::table('sis_register')->insert([
            'identifier' => 'SIM-PRS-100001-XX',
            'class' => 'PRS',
            'serial' => 100001,
            'spec_edition' => 'SIS/1',
            'state' => 'commissioned',
            'description' => '',
            'commissioned_at' => Date::now(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);

        $this->artisan('sis:doctor')->assertExitCode(1);
    }
}
