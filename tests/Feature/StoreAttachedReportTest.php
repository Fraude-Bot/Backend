<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Models\IpActivity;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Report;
use App\Models\ReportProof;
use App\Models\Scammer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreAttachedReportTest extends TestCase
{
    private const string IP = '203.0.113.40';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_files_a_report_against_an_existing_scammer(): void
    {
        $scammer = Scammer::factory()->create([
            'name' => 'Juan Perez',
            'profile_picture_path' => null,
        ]);
        $proofs = [
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'one.jpg', 'one'),
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'two.png', 'two'),
        ];

        $response = $this->fromIp()->postJson("/api/public/scammers/{$scammer->id}/reports", [
            'title' => '  Called me about a prize  ',
            'description' => 'They asked for a transfer to release the prize.',
            'email' => ' reporter@example.com ',
            'products' => ['crypto wallet', 'Banking'],
            'proofs' => $proofs,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('scammer_id', $scammer->id);

        $report = Report::query()->first();
        $user = User::query()->where('email', 'reporter@example.com')->first();
        $products = Product::query()->orderBy('name')->get();

        $this->assertNotNull($report);
        $this->assertNotNull($user);
        $this->assertSame($report->id, $response->json('id'));
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('reporter', $user->role);
        $this->assertTrue($report->is_active);
        $this->assertSame('Called me about a prize', $report->title);
        $this->assertSame('They asked for a transfer to release the prize.', $report->description);
        $this->assertTrue($scammer->reports()->whereKey($report->id)->exists());
        $this->assertSame(1, Scammer::query()->count());
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame('Juan Perez', $scammer->fresh()->name);
        $this->assertNull($scammer->fresh()->profile_picture_path);
        $this->assertSame(['Banking', 'Crypto Wallet'], $products->pluck('name')->all());
        $this->assertSame($products->pluck('id')->sort()->values()->all(), collect($response->json('product_ids'))->sort()->values()->all());
        $this->assertEqualsCanonicalizing($products->modelKeys(), $report->products()->pluck('products.id')->all());

        $storedProofs = ReportProof::query()->where('report_id', $report->id)->orderBy('id')->get();
        $this->assertCount(2, $storedProofs);
        $this->assertSame($storedProofs->pluck('id')->all(), $response->json('report_proof_ids'));
        $this->assertSame(['one', 'two'], [
            Storage::disk('public')->get($storedProofs[0]->path),
            Storage::disk('public')->get($storedProofs[1]->path),
        ]);
        $this->assertStringStartsWith('reports/scammers/proofs/', $storedProofs[0]->path);
        $this->assertStringStartsWith('reports/scammers/proofs/', $storedProofs[1]->path);
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/one.jpg'));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/two.png'));

        $this->assertSame(1, IpActivity::query()->count());
        $this->assertDatabaseHas('ip_activities', [
            'action' => 'report.created',
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'ip_address' => self::IP,
            'actor_id' => null,
        ]);
    }

    public function test_files_a_report_against_an_existing_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Tienda Falsa']);
        $proof = $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'receipt.jpg', 'receipt');

        $response = $this->fromIp()->postJson("/api/public/organizations/{$organization->id}/reports", [
            'title' => 'Fake store took my money',
            'description' => 'They never shipped the order.',
            'email' => 'reporter@example.com',
            'products' => ['Crypto'],
            'proofs' => [$proof],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('organization_id', $organization->id);

        $report = Report::query()->first();
        $this->assertNotNull($report);
        $this->assertSame($report->id, $response->json('id'));
        $this->assertTrue($organization->reports()->whereKey($report->id)->exists());
        $this->assertSame(1, Organization::query()->count());
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame('Tienda Falsa', $organization->fresh()->name);

        $storedProof = ReportProof::query()->where('report_id', $report->id)->first();
        $this->assertNotNull($storedProof);
        $this->assertSame([$storedProof->id], $response->json('report_proof_ids'));
        $this->assertStringStartsWith('reports/organizations/proofs/', $storedProof->path);
        $this->assertSame('receipt', Storage::disk('public')->get($storedProof->path));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/receipt.jpg'));

        $this->assertDatabaseHas('ip_activities', [
            'action' => 'report.created',
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'ip_address' => self::IP,
        ]);
    }

    public function test_reuses_an_existing_reporter_and_product_when_proofs_are_omitted(): void
    {
        $user = User::factory()->create(['email' => 'reporter@example.com', 'role' => 'reporter']);
        $product = Product::factory()->create(['name' => 'Crypto']);
        $scammer = Scammer::factory()->create();
        $organization = Organization::factory()->create();

        $scammerResponse = $this->postJson("/api/public/scammers/{$scammer->id}/reports", [
            'title' => 'Second call',
            'description' => 'They called again.',
            'email' => 'reporter@example.com',
            'products' => ['crypto', 'CRYPTO'],
        ]);
        $organizationResponse = $this->postJson("/api/public/organizations/{$organization->id}/reports", [
            'title' => 'Store follow-up',
            'description' => 'Still no shipment.',
            'email' => 'reporter@example.com',
            'products' => ['Crypto'],
            'proofs' => [],
        ]);

        $scammerResponse->assertCreated();
        $organizationResponse->assertCreated();
        $scammerResponse->assertJsonPath('product_ids', [$product->id]);
        $scammerResponse->assertJsonPath('report_proof_ids', []);
        $organizationResponse->assertJsonPath('product_ids', [$product->id]);
        $organizationResponse->assertJsonPath('report_proof_ids', []);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(
            [$user->id, $user->id],
            Report::query()->orderBy('id')->pluck('user_id')->all(),
        );
        $this->assertSame(0, ReportProof::query()->count());
    }

    public function test_rejects_invalid_bodies_and_unpublished_proofs(): void
    {
        $scammer = Scammer::factory()->create();
        $validProof = $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'one.jpg', 'one');

        $missing = $this->postJson("/api/public/scammers/{$scammer->id}/reports", []);
        $missing->assertUnprocessable();
        $missing->assertJsonPath('error.code', 'validation_failed');
        foreach (['title', 'description', 'email', 'products'] as $field) {
            $this->assertArrayHasKey($field, $missing->json('error.details'));
        }

        $organization = Organization::factory()->create();
        $emptyProducts = $this->postJson("/api/public/organizations/{$organization->id}/reports", $this->payload([
            'products' => [],
            'email' => 'not-an-email',
            'title' => str_repeat('a', 51),
        ]));
        $emptyProducts->assertUnprocessable();
        $details = $emptyProducts->json('error.details');
        $this->assertArrayHasKey('products', $details);
        $this->assertArrayHasKey('email', $details);
        $this->assertArrayHasKey('title', $details);

        $badProof = $this->postJson("/api/public/scammers/{$scammer->id}/reports", $this->payload([
            'proofs' => [$validProof, '/storage/tmp/reports/proofs/missing.jpg'],
        ]));
        $badProof->assertUnprocessable();
        $this->assertArrayHasKey('proofs.1', $badProof->json('error.details'));
        $this->assertSame(0, Report::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('reports/scammers/proofs'));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/one.jpg'));
        $this->assertSame(0, IpActivity::query()->count());
    }

    public function test_rejects_a_missing_inactive_or_invalid_scammer(): void
    {
        $inactive = Scammer::factory()->inactive()->create();
        $deleted = Scammer::factory()->create();
        $deleted->delete();

        $this->postJson('/api/public/scammers/nope/reports', $this->payload())
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid scammer ID']);

        $this->postJson('/api/public/scammers/999999/reports', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $this->postJson("/api/public/scammers/{$inactive->id}/reports", $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $this->postJson("/api/public/scammers/{$deleted->id}/reports", $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $this->assertSame(0, Report::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, IpActivity::query()->count());
    }

    public function test_rejects_a_missing_inactive_or_invalid_organization(): void
    {
        $inactive = Organization::factory()->inactive()->create();
        $deleted = Organization::factory()->create();
        $deleted->delete();

        $this->postJson('/api/public/organizations/nope/reports', $this->payload())
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid organization ID']);

        $this->postJson('/api/public/organizations/999999/reports', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->postJson("/api/public/organizations/{$inactive->id}/reports", $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->postJson("/api/public/organizations/{$deleted->id}/reports", $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->assertSame(0, Report::query()->count());
        $this->assertSame(0, IpActivity::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer.',
            'email' => 'reporter@example.com',
            'products' => ['Crypto'],
            ...$overrides,
        ];
    }

    private function fromIp(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
    }

    private function publishTemporary(string $directory, string $filename, string $contents): string
    {
        $relative = $directory.'/'.$filename;
        Storage::disk('public')->put($relative, $contents);

        $urlPath = parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH);

        return rtrim(is_string($urlPath) ? $urlPath : '', '/').'/'.$relative;
    }
}
