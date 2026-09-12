<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateNetworkAccessTest extends TestCase
{
    public function test_local_origin_gets_private_network_headers(): void
    {
        $response = $this->get('/login', ['Origin' => 'http://layratepi.local']);

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Private-Network', 'true');
        $response->assertHeader('Access-Control-Allow-Origin', 'http://layratepi.local');
    }

    public function test_private_network_preflight_is_answered(): void
    {
        $response = $this->call('OPTIONS', '/environment/live-data?range=24h', [], [], [], [
            'HTTP_ORIGIN' => 'http://layratepi.local',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK' => 'true',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Private-Network', 'true');
        $response->assertHeader('Access-Control-Allow-Origin', 'http://layratepi.local');
    }

    public function test_public_internet_origin_gets_no_private_network_headers(): void
    {
        $response = $this->get('/login', ['Origin' => 'https://example.com']);

        $response->assertOk();
        $response->assertHeaderMissing('Access-Control-Allow-Private-Network');
    }

    public function test_request_without_origin_is_untouched(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeaderMissing('Access-Control-Allow-Private-Network');
    }
}
