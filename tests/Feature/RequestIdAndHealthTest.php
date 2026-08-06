<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_response_carries_a_request_id(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_incoming_request_id_is_honoured(): void
    {
        $id = 'test-correlation-id-001';

        $response = $this->withHeader('X-Request-Id', $id)->get('/up');

        $response->assertOk();
        $this->assertSame($id, $response->headers->get('X-Request-Id'));
    }
}
