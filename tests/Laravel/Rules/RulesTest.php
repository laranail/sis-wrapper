<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\SIS\Rules\NotReservedAlias;
use Simtabi\Laranail\SIS\Rules\ScopeMatchesClass;
use Simtabi\Laranail\SIS\Rules\ValidAliasShape;
use Simtabi\Laranail\SIS\Rules\ValidIdentifier;
use Simtabi\Laranail\SIS\Rules\ValidIdentifierOfClass;
use Simtabi\Laranail\SIS\Rules\ValidLifecycleTransition;
use Simtabi\Laranail\SIS\Rules\ValidSemver;
use Simtabi\Laranail\SIS\Rules\ValidSubtype;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * The core-delegating rules are usable standalone — no framework, no app, no database. This exercises them
 * directly, the way a consumer uses them in their own validation.
 */
final class RulesTest extends TestCase
{
    private function fails(ValidationRule $rule, mixed $value): bool
    {
        $failed = false;
        $rule->validate('field', $value, function () use (&$failed): void {
            $failed = true;
        });

        return $failed;
    }

    public function test_valid_identifier(): void
    {
        self::assertFalse($this->fails(new ValidIdentifier, 'SIM-PRS-100001-FA'));
        self::assertTrue($this->fails(new ValidIdentifier, 'not-an-id'));
        self::assertTrue($this->fails(new ValidIdentifier, 'SIM-INV-ADQI-000001-VY')); // transposed alias
    }

    public function test_valid_identifier_of_class(): void
    {
        self::assertFalse($this->fails(new ValidIdentifierOfClass(IdClass::Invoice), 'SIM-INV-ADIQ-000001-VY'));
        self::assertTrue($this->fails(new ValidIdentifierOfClass(IdClass::Invoice), 'SIM-PRS-100001-FA'));
    }

    public function test_alias_rules(): void
    {
        self::assertFalse($this->fails(new ValidAliasShape, 'ADIQ'));
        self::assertTrue($this->fails(new ValidAliasShape, 'A'));
        self::assertTrue($this->fails(new ValidAliasShape, '1ABCD'));
        self::assertTrue($this->fails(new NotReservedAlias, 'SIMT'));
        self::assertFalse($this->fails(new NotReservedAlias, 'ADIQ'));
    }

    public function test_lifecycle_transition(): void
    {
        self::assertFalse($this->fails(new ValidLifecycleTransition(LifecycleState::Reserved), 'commissioned'));
        self::assertTrue($this->fails(new ValidLifecycleTransition(LifecycleState::Reserved), 'suspended'));
    }

    public function test_semver(): void
    {
        self::assertFalse($this->fails(new ValidSemver, 'MALISA-1.0.0'));
        self::assertTrue($this->fails(new ValidSemver, 'nope'));
    }

    public function test_subtype(): void
    {
        self::assertFalse($this->fails(new ValidSubtype(IdClass::Asset), 'LAP'));
        self::assertTrue($this->fails(new ValidSubtype(IdClass::Asset), 'FOO'));
        self::assertTrue($this->fails(new ValidSubtype(IdClass::Client), 'LAP')); // Client has no vocabulary
    }

    public function test_scope_matches_class(): void
    {
        self::assertTrue($this->fails(new ScopeMatchesClass(IdClass::Invoice), ''));       // Form S needs a scope
        self::assertFalse($this->fails(new ScopeMatchesClass(IdClass::Invoice), 'ADIQ'));
        self::assertTrue($this->fails(new ScopeMatchesClass(IdClass::Person), 'ADIQ'));    // Form G takes none
        self::assertFalse($this->fails(new ScopeMatchesClass(IdClass::Person), ''));
    }
}
