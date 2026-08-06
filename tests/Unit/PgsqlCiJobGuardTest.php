<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The pgsql CI job relies on process env overriding phpunit.xml. If someone
 * flips phpunit.xml to force sqlite, or removes the pgsql job, cutover stalls.
 */
class PgsqlCiJobGuardTest extends TestCase
{
    #[Test]
    public function phpunit_xml_still_defaults_to_sqlite_memory(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/phpunit.xml');
        $this->assertNotFalse($xml);
        $this->assertMatchesRegularExpression(
            '/<env\s+name="DB_CONNECTION"\s+value="sqlite"\s*\/>/',
            $xml,
        );
        $this->assertMatchesRegularExpression(
            '/<env\s+name="DB_DATABASE"\s+value=":memory:"\s*\/>/',
            $xml,
        );
        $this->assertStringNotContainsString('force="true"', $xml);
    }

    #[Test]
    public function ci_defines_a_pgsql_test_suite_job(): void
    {
        $ci = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml');
        $this->assertNotFalse($ci);
        $this->assertStringContainsString('name: Test suite (pgsql)', $ci);
        $this->assertStringContainsString('DB_CONNECTION: pgsql', $ci);
        $this->assertStringContainsString('php artisan migrate --force', $ci);
    }
}
