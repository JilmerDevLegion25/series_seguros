<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

final class AssignRequestIdTest extends TestCase
{
    public function test_request_id_is_generated_server_side(): void
    {
        $clientProvided = (string) Str::uuid();

        $response = $this->withHeader('X-Request-Id', $clientProvided)->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');

        $generated = $response->headers->get('X-Request-Id');

        $this->assertNotSame($clientProvided, $generated);
        $this->assertTrue(Str::isUuid((string) $generated));
    }
}
