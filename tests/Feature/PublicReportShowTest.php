<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Report;
use App\Models\ReportProof;
use Tests\TestCase;

class PublicReportShowTest extends TestCase
{
    public function test_returns_an_active_report_with_proofs_and_products(): void
    {
        $report = Report::factory()->create([
            'title' => 'Invoice scam',
            'description' => 'They asked me to pay a fake invoice.',
            'is_active' => true,
            'created_at' => '2026-09-01 15:30:00',
        ]);

        $watch = Product::factory()->create(['name' => 'Watch']);
        $phone = Product::factory()->create(['name' => 'Phone']);
        $report->products()->attach([$watch->id, $phone->id]);

        $removed = Product::factory()->create(['name' => 'Removed']);
        $report->products()->attach($removed->id);
        $report->products()->updateExistingPivot($removed->id, ['deleted_at' => now()]);

        ReportProof::query()->create([
            'report_id' => $report->id,
            'path' => 'reports/scammers/proofs/first.jpg',
        ]);
        ReportProof::query()->create([
            'report_id' => $report->id,
            'path' => 'reports/organizations/proofs/second.png',
        ]);

        $this->getJson("/api/public/reports/{$report->id}")
            ->assertOk()
            ->assertExactJson([
                'title' => 'Invoice scam',
                'description' => 'They asked me to pay a fake invoice.',
                'is_active' => true,
                'proofs' => [
                    'reports/scammers/proofs/first.jpg',
                    'reports/organizations/proofs/second.png',
                ],
                'products' => ['Phone', 'Watch'],
                'created_at' => '2026-09-01',
            ]);
    }

    public function test_returns_empty_proofs_and_products_when_the_report_has_none(): void
    {
        $report = Report::factory()->create([
            'title' => 'No extras',
            'description' => null,
            'is_active' => true,
            'created_at' => '2026-03-14 08:00:00',
        ]);

        $this->getJson("/api/public/reports/{$report->id}")
            ->assertOk()
            ->assertExactJson([
                'title' => 'No extras',
                'description' => null,
                'is_active' => true,
                'proofs' => [],
                'products' => [],
                'created_at' => '2026-03-14',
            ]);
    }

    public function test_rejects_an_invalid_report_id(): void
    {
        $this->getJson('/api/public/reports/9999999999999999999999999999999999999999')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid report ID']);

        $this->getJson('/api/public/reports/not-a-number')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid report ID']);
    }

    public function test_returns_not_found_for_a_missing_inactive_or_deleted_report(): void
    {
        $this->getJson('/api/public/reports/0')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Report not found']);

        $inactive = Report::factory()->create(['is_active' => false]);

        $this->getJson("/api/public/reports/{$inactive->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Report not found']);

        $deleted = Report::factory()->create(['is_active' => true]);
        $deleted->delete();

        $this->getJson("/api/public/reports/{$deleted->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Report not found']);
    }
}
