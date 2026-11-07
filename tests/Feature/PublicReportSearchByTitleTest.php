<?php

namespace Tests\Feature;

use App\Http\Resources\Public\ReportResource;
use App\Models\Report;
use Tests\TestCase;

class PublicReportSearchByTitleTest extends TestCase
{
    public function test_search_by_title_returns_matching_reports(): void
    {
        $needle = 'AlphaTitleSearchToken';
        Report::factory()->create(['title' => "First {$needle} report", 'is_active' => true]);
        Report::factory()->create(['title' => 'Unrelated title', 'is_active' => true]);
        Report::factory()->create(['title' => "Another {$needle} here", 'is_active' => true]);

        $reports = Report::query()
            ->where('is_active', true)
            ->where('title', 'like', "%{$needle}%")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => $needle,
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => ReportResource::collection($reports)->resolve(),
                'total' => 2,
                'page' => 1,
                'count' => 10,
            ]);
    }

    public function test_empty_query_returns_empty_data(): void
    {
        Report::factory()->create(['title' => 'Some title', 'is_active' => true]);

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => '',
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'total' => 0,
                'page' => 1,
                'count' => 10,
            ]);
    }

    public function test_pagination_splits_results(): void
    {
        $needle = 'BetaTitleSearchToken';
        Report::factory()->create(['title' => "A {$needle} one", 'is_active' => true]);
        Report::factory()->create(['title' => "B {$needle} two", 'is_active' => true]);
        Report::factory()->create(['title' => "C {$needle} three", 'is_active' => true]);

        $page1 = Report::query()
            ->where('is_active', true)
            ->where('title', 'like', "%{$needle}%")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->forPage(1, 2)
            ->get();

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => $needle,
            'p' => 1,
            'c' => 2,
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => ReportResource::collection($page1)->resolve(),
                'total' => 3,
                'page' => 1,
                'count' => 2,
            ]);

        $page2 = Report::query()
            ->where('is_active', true)
            ->where('title', 'like', "%{$needle}%")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->forPage(2, 2)
            ->get();

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => $needle,
            'p' => 2,
            'c' => 2,
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => ReportResource::collection($page2)->resolve(),
                'total' => 3,
                'page' => 2,
                'count' => 2,
            ]);
    }

    public function test_excludes_inactive_and_soft_deleted_reports(): void
    {
        $needle = 'GammaTitleSearchToken';
        Report::factory()->create([
            'title' => "Active {$needle}",
            'is_active' => true,
        ]);
        Report::factory()->create([
            'title' => "Inactive {$needle}",
            'is_active' => false,
        ]);
        $trashed = Report::factory()->create([
            'title' => "Trashed {$needle}",
            'is_active' => true,
        ]);
        $trashed->delete();

        $active = Report::query()
            ->where('is_active', true)
            ->where('title', 'like', "%{$needle}%")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => $needle,
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => ReportResource::collection($active)->resolve(),
                'total' => 1,
                'page' => 1,
                'count' => 10,
            ]);
    }

    public function test_invalid_page_or_count_returns_400(): void
    {
        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => 'test',
            'p' => 0,
            'c' => 10,
        ]))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid page or count');

        $this->getJson('/api/public/reports/search-by-title?'.http_build_query([
            'q' => 'test',
            'p' => 1,
            'c' => 101,
        ]))
            ->assertStatus(400);
    }
}
