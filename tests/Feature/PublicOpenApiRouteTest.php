<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicOpenApiRouteTest extends TestCase
{
    public function test_public_openapi_returns_the_bundled_document(): void
    {
        $response = $this->getJson('/api/public/openapi');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonPath('openapi', '3.1.0');
        $response->assertJsonPath('info.title', 'Fraudebot API');
        $response->assertJsonPath('components.schemas.Contact.type', 'object');
        $this->assertSame(
            'getOpenApiDocument',
            $response->json('paths')['/public/openapi']['get']['operationId'],
        );
        $this->assertArrayNotHasKey('$ref', $response->json('components.schemas.Contact'));
        $this->assertStringNotContainsString('./schemas/', (string) $response->getContent());
    }
}
