<?php

namespace Tests\Unit;

use App\OpenApi\OpenApiDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OpenApiDocumentTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/openapi-document-'.uniqid('', true);
        mkdir($this->root.'/schemas', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_inlines_external_schema_refs(): void
    {
        file_put_contents($this->root.'/openapi.yaml', <<<'YAML'
openapi: "3.1.0"
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    Scammer:
      $ref: "./schemas/Scammer.yaml"
YAML);

        file_put_contents($this->root.'/schemas/Scammer.yaml', <<<'YAML'
type: object
description: A reported scammer or organization.
properties:
  id:
    type: string
YAML);

        $document = (new OpenApiDocument($this->root.'/openapi.yaml', $this->root))->bundled();

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame('object', $document['components']['schemas']['Scammer']['type']);
        $this->assertSame(
            'A reported scammer or organization.',
            $document['components']['schemas']['Scammer']['description'],
        );
        $this->assertArrayNotHasKey('$ref', $document['components']['schemas']['Scammer']);
    }

    public function test_project_spec_bundles_shared_schemas(): void
    {
        $root = dirname(__DIR__, 2);
        $document = (new OpenApiDocument($root.'/openapi.yaml', $root))->bundled();

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame('Fraudebot API', $document['info']['title']);
        $this->assertSame('getScammer', $document['paths']['/public/scammers/{id}']['get']['operationId']);
        $this->assertSame('listScammerContacts', $document['paths']['/public/scammers/{id}/contacts']['get']['operationId']);
        $this->assertSame('listScammerReports', $document['paths']['/public/scammers/{id}/reports']['get']['operationId']);
        $this->assertSame('listOrganizationReports', $document['paths']['/public/organizations/{id}/reports']['get']['operationId']);
        $this->assertSame('suggestOrganizationNames', $document['paths']['/public/organizations/suggest']['get']['operationId']);
        $this->assertSame('object', $document['components']['schemas']['Contact']['type']);
        $this->assertArrayNotHasKey('$ref', $document['components']['schemas']['Contact']);
        $this->assertArrayNotHasKey('$ref', $document['components']['schemas']['PaginatedContacts']['properties']['data']['items']);
        $this->assertSame('object', $document['components']['schemas']['Report']['type']);
        $this->assertArrayNotHasKey('$ref', $document['components']['schemas']['Report']);
        $this->assertArrayNotHasKey('$ref', $document['components']['schemas']['PaginatedReports']['properties']['data']['items']);
        $this->assertArrayNotHasKey('/public/reports', $document['paths']);
        $this->assertSame(
            'storeOrganizationReport',
            $document['paths']['/public/reports/organizations']['post']['operationId'],
        );
    }

    public function test_rejects_refs_outside_the_allowed_root(): void
    {
        $outsideFile = dirname($this->root).'/openapi-outside-'.uniqid('', true).'.yaml';
        file_put_contents($outsideFile, "type: object\n");
        $relative = '../'.basename($outsideFile);

        file_put_contents($this->root.'/openapi.yaml', <<<YAML
openapi: "3.1.0"
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    Scammer:
      \$ref: "{$relative}"
YAML);

        try {
            (new OpenApiDocument($this->root.'/openapi.yaml', $this->root))->bundled();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('OpenAPI $ref escapes the project root', $exception->getMessage());
        } finally {
            if (is_file($outsideFile)) {
                unlink($outsideFile);
            }
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($target)) {
                $this->deleteDirectory($target);

                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
