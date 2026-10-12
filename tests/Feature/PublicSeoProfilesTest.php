<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSeoProfilesTest extends TestCase
{
    public function test_lists_active_scammers_and_organizations(): void
    {
        $organization = Organization::factory()->create([
            'updated_at' => '2026-08-24 15:30:00',
        ]);
        $scammer = Scammer::factory()->create([
            'updated_at' => '2026-08-24 00:00:00',
        ]);

        $response = $this->getJson('/api/public/seo/profiles');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $scammer->id,
                    'type' => 'scammer',
                    'updatedAt' => '2026-08-24T00:00:00Z',
                ],
                [
                    'id' => $organization->id,
                    'type' => 'organization',
                    'updatedAt' => '2026-08-24T15:30:00Z',
                ],
            ],
            'total' => 2,
            'count' => 2,
        ]);
    }

    public function test_omits_inactive_and_soft_deleted_profiles(): void
    {
        $activeScammer = Scammer::factory()->create([
            'updated_at' => '2026-08-24 00:00:00',
        ]);
        Scammer::factory()->inactive()->create();
        Scammer::factory()->create()->delete();

        $activeOrganization = Organization::factory()->create([
            'updated_at' => '2026-08-24 15:30:00',
        ]);
        Organization::factory()->inactive()->create();
        Organization::factory()->create()->delete();

        $response = $this->getJson('/api/public/seo/profiles');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $activeScammer->id,
                    'type' => 'scammer',
                    'updatedAt' => '2026-08-24T00:00:00Z',
                ],
                [
                    'id' => $activeOrganization->id,
                    'type' => 'organization',
                    'updatedAt' => '2026-08-24T15:30:00Z',
                ],
            ],
            'total' => 2,
            'count' => 2,
        ]);
    }

    public function test_returns_an_empty_index(): void
    {
        $response = $this->getJson('/api/public/seo/profiles');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [],
            'total' => 0,
            'count' => 0,
        ]);
    }

    public function test_serves_a_cached_response_until_public_cache_is_invalidated(): void
    {
        $scammer = Scammer::factory()->create([
            'updated_at' => '2026-08-24 00:00:00',
        ]);

        $this->getJson('/api/public/seo/profiles')
            ->assertOk()
            ->assertJsonPath('data.0.updatedAt', '2026-08-24T00:00:00Z');

        DB::table('scammers')->where('id', $scammer->id)->update([
            'updated_at' => '2026-09-01 12:00:00',
        ]);

        $this->getJson('/api/public/seo/profiles')
            ->assertOk()
            ->assertJsonPath('data.0.updatedAt', '2026-08-24T00:00:00Z');

        SearchCache::invalidate();

        $this->getJson('/api/public/seo/profiles')
            ->assertOk()
            ->assertJsonPath('data.0.updatedAt', '2026-09-01T12:00:00Z');
    }
}
