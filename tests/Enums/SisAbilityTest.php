<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Enums;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Enums\SisAbility;

/**
 * `SisAbility` carries its human labels and operator-facing descriptions through `laranail/enumerator`:
 * each case has `#[Label]` and `#[Description]` attributes and the `HasEnumeratorBehavior` trait exposes
 * `label()`, `description()`, `labels()`, and `options()`.
 */
final class SisAbilityTest extends TestCase
{
    public function test_label_reads_the_attribute(): void
    {
        self::assertSame('Reserve identifier', SisAbility::Reserve->label());
        self::assertSame('Commission identifier', SisAbility::Commission->label());
        self::assertSame('View register', SisAbility::ViewRegister->label());
        self::assertSame('Manage webhooks', SisAbility::ManageWebhooks->label());
    }

    public function test_description_reads_the_attribute_and_every_case_has_one(): void
    {
        self::assertStringContainsString('burning a serial', (string) SisAbility::Reserve->description());
        self::assertSame('Create, rotate, and remove webhook endpoints.', SisAbility::ManageWebhooks->description());

        foreach (SisAbility::cases() as $ability) {
            self::assertNotNull($ability->description(), "{$ability->value} has no description");
        }
    }

    public function test_labels_maps_every_backing_value_to_its_label(): void
    {
        $labels = SisAbility::labels();

        self::assertCount(count(SisAbility::cases()), $labels);
        self::assertSame('Reserve identifier', $labels['sis.identifier.reserve']);
        self::assertArrayHasKey('sis.webhooks.manage', $labels);
    }

    public function test_options_are_select_ready_and_accept_a_placeholder(): void
    {
        $options = SisAbility::options();

        self::assertSame(SisAbility::labels(), $options);

        $withPlaceholder = SisAbility::options('Choose an ability');

        self::assertSame('Choose an ability', $withPlaceholder['']);
        self::assertArrayHasKey('sis.identifier.commission', $withPlaceholder);
    }
}
