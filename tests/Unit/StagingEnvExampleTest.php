<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StagingEnvExampleTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = dirname(__DIR__, 2).'/.env.staging.example';
    }

    #[Test]
    public function staging_example_mirrors_production_safety(): void
    {
        $this->assertFileExists($this->path);
        $env = file_get_contents($this->path);
        $this->assertNotFalse($env);

        $this->assertMatchesRegularExpression('/^APP_ENV=staging$/m', $env);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $env);
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $env);
        $this->assertMatchesRegularExpression('/^DB_CONNECTION=pgsql$/m', $env);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=redis$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^MAIL_MAILER=log$/m', $env);
    }
}
