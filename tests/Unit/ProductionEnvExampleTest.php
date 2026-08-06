<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A naive first deploy from .env.example (APP_DEBUG=true, log mailer) leaks
 * stack traces with DB paths. Production must start from
 * .env.production.example instead — this guard fails the build if that file
 * drifts back toward "local defaults".
 */
class ProductionEnvExampleTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = dirname(__DIR__, 2).'/.env.production.example';
    }

    #[Test]
    public function production_example_exists(): void
    {
        $this->assertFileExists($this->path);
    }

    #[Test]
    public function production_example_disables_debug_and_encrypts_sessions(): void
    {
        $env = file_get_contents($this->path);
        $this->assertNotFalse($env);

        $this->assertMatchesRegularExpression('/^APP_ENV=production$/m', $env);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $env);
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^APP_DEBUG=true$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^MAIL_MAILER=log$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^QUEUE_CONNECTION=database$/m', $env);
    }
}
