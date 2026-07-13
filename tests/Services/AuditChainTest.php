<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Services;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Services\IntegrityService;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Enums\SimClass;

/**
 * The audit hash chain is only tamper-evident if something actually verifies it. These tests drive real
 * writes through the full stack to grow a genuine chain, then prove that an intact chain passes and a
 * hand-corrupted row (a rewrite the append-only trigger cannot catch on SQLite) is detected.
 */
final class AuditChainTest extends TestCase
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

    private function integrity(): IntegrityService
    {
        return $this->app->make(IntegrityService::class);
    }

    /** A handful of sequential real writes, so the genesis row and its successors form one linked chain. */
    private function growChain(): void
    {
        $first = Sis::reserve(SimClass::CLIENT, reason: 'first');
        Sis::commission($first);

        $second = Sis::reserve(SimClass::PERSON, reason: 'second');
        Sis::commission($second);
    }

    public function test_a_genuine_chain_of_sequential_writes_verifies_clean(): void
    {
        $this->growChain();

        $this->assertGreaterThan(1, SisAudit::query()->count(), 'expected several audit rows to verify.');
        $this->assertSame([], $this->integrity()->verifyAuditChain());
    }

    public function test_a_tampered_hash_is_detected(): void
    {
        $this->growChain();

        // Rewrite one committed row's hash — exactly the tampering the append-only trigger cannot catch on
        // SQLite, and the reason the chain is verified rather than trusted.
        $victim = SisAudit::query()->orderBy('id')->skip(1)->first();
        $this->assertNotNull($victim);
        DB::table('sis_audit')->where('id', $victim->id)->update(['hash' => str_repeat('0', 64)]);

        $broken = $this->integrity()->verifyAuditChain();

        $this->assertNotSame([], $broken);
        $this->assertStringContainsString('#' . $victim->id, implode(' ', $broken));
    }

    public function test_a_forked_prev_hash_is_detected(): void
    {
        $this->growChain();

        // Two rows sharing a predecessor is what a concurrent unserialised write would produce: point one
        // row's prev_hash at the genesis row's prev (null → some other value) to simulate the fork.
        $victim = SisAudit::query()->orderBy('id')->skip(2)->first();
        $this->assertNotNull($victim);
        DB::table('sis_audit')->where('id', $victim->id)->update(['prev_hash' => str_repeat('a', 64)]);

        $broken = $this->integrity()->verifyAuditChain();

        $this->assertNotSame([], $broken);
        $this->assertStringContainsString('#' . $victim->id, implode(' ', $broken));
    }

    public function test_verification_is_skipped_when_hash_chaining_is_off(): void
    {
        config(['sis.audit.hash_chain' => false]);
        $this->growChain();

        // No chain is maintained, so there is nothing to verify — and no false alarm from unhashed rows.
        $this->assertSame([], $this->integrity()->verifyAuditChain());
    }
}
