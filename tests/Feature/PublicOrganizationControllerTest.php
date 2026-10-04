<?php

namespace Tests\Feature;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\Edge;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\OrganizationResource;
use App\Http\Resources\Public\ReportResource;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Report;
use App\Models\Scammer;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicOrganizationControllerTest extends TestCase
{
    public function test_find_organization_by_id(): void
    {
        /**
         * @var Organization $organization
         */
        $organization = Organization::factory()->create();

        $response = $this->getJson("/api/public/organizations/{$organization->id}");

        $response->assertStatus(200);
        $response->assertExactJson(OrganizationResource::make($organization)->resolve());
    }

    public function test_find_organization_by_invalid_id_returns400(): void
    {
        $response = $this->getJson('/api/public/organizations/9999999999999999999999999999999999999999');

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID']);
    }

    public function test_find_organization_by_non_existent_id_returns404(): void
    {
        $response = $this->getJson('/api/public/organizations/0');

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization not found']);
    }

    public function test_find_organization_calendar_by_id_and_year(): void
    {
        /**
         * @var Organization $organization
         */
        $organization = Organization::factory()->create();

        $reports = Report::factory()->count(3)->create([
            'created_at' => '2026-01-15 12:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ]);

        $organization->reports()->attach($reports->pluck('id'));

        $calendar = collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => $month === 1 ? 3 : 0]);

        $response = $this->getJson("/api/public/organizations/{$organization->id}/calendar/2026");

        $response->assertStatus(200);
        $response->assertExactJson($calendar->toArray());
    }

    public function test_find_organization_calendar_by_invalid_id_returns400(): void
    {
        $response = $this->getJson('/api/public/organizations/9999999999999999999999999999999999999999/calendar/2026');

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID or year']);
    }

    public function test_find_organization_calendar_by_invalid_year_returns400(): void
    {
        $response = $this->getJson('/api/public/organizations/1/calendar/not-a-year');

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID or year']);
    }

    public function test_find_organization_calendar_by_non_existent_id_returns404(): void
    {
        $response = $this->getJson('/api/public/organizations/0/calendar/2026');

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization not found']);
    }

    public function test_find_organization_contacts_by_id(): void
    {
        $organization = Organization::factory()->create();

        $contacts = Contact::factory()->count(3)->create();

        $organization->contacts()->attach($contacts->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => ContactResource::collection($contacts)->resolve(),
            'total' => $contacts->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/contacts?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_findo_organization_contacts_by_id_with_platform_query_param(): void
    {
        $organization = Organization::factory()->create();
        $contacts = Contact::factory()->createMany([
            [
                'reference' => 'john-doe',
                'platform' => PlatformType::INSTAGRAM,
                'is_active' => true,
            ],
            [
                'reference' => 'jane-doe',
                'platform' => PlatformType::FACEBOOK,
                'is_active' => true,
            ],
            [
                'reference' => 'jim-doe',
                'platform' => PlatformType::TELEGRAM,
                'is_active' => true,
            ],
        ]);
        $organization->contacts()->attach($contacts->pluck('id'));

        $page = 1;
        $count = 10;
        $platform = 'instagram';
        $expected = [
            'data' => ContactResource::collection($contacts->where('platform', PlatformType::INSTAGRAM))->resolve(),
            'total' => $contacts->where('platform', PlatformType::INSTAGRAM)->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/contacts?p={$page}&c={$count}&platform={$platform}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_find_organization_contacts_by_id_with_invalid_page_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization contacts not found']);
    }

    public function test_find_organization_contacts_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_contacts_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/organizations/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_contacts_by_id_with_invalid_organization_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/invalid-organization-id/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_reports_by_id(): void
    {
        $organization = Organization::factory()->create();
        $reports = Report::factory()->count(3)->create();
        $organization->reports()->attach($reports->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => ReportResource::collection($reports)->resolve(),
            'total' => $reports->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/reports?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
        $response->assertJsonPath('data.0.created_at', $reports->first()->created_at->format('Y-m-d'));
        $response->assertJsonPath('data.0.short_description', Str::limit($reports->first()->description, 125, '...'));
    }

    public function test_organization_reports_total_reflects_all_matching_rows(): void
    {
        $organization = Organization::factory()->create();
        $reports = Report::factory()->count(15)->create();
        $organization->reports()->attach($reports);

        $this->getJson("/api/public/organizations/{$organization->id}/reports?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_organization_report_pagination_is_bounded(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/reports?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_report_changes_invalidate_cached_public_organization_reports(): void
    {
        $organization = Organization::factory()->create();
        $report = Report::factory()->create(['title' => 'Before']);
        $organization->reports()->attach($report);

        $this->getJson("/api/public/organizations/{$organization->id}/reports")
            ->assertJsonPath('data.0.title', 'Before');

        $report->update(['title' => 'After']);

        $this->getJson("/api/public/organizations/{$organization->id}/reports")
            ->assertJsonPath('data.0.title', 'After');
    }

    public function test_inactive_organization_reports_are_omitted(): void
    {
        $organization = Organization::factory()->create();
        $active = Report::factory()->create(['is_active' => true, 'title' => 'Active report']);
        $inactive = Report::factory()->create(['is_active' => false, 'title' => 'Inactive report']);
        $organization->reports()->attach([$active->id, $inactive->id]);

        $this->getJson("/api/public/organizations/{$organization->id}/reports")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active report')
            ->assertJsonPath('total', 1);
    }

    public function test_find_organization_reports_by_id_with_inactive_organization_returns404(): void
    {
        $organization = Organization::factory()->create(['is_active' => false]);
        $report = Report::factory()->create();
        $organization->reports()->attach($report);

        $this->getJson("/api/public/organizations/{$organization->id}/reports")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Organization reports not found']);
    }

    public function test_find_organization_reports_by_id_with_invalid_page_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/reports?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization reports not found']);
    }

    public function test_find_organization_reports_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_reports_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/organizations/1/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_reports_by_id_with_invalid_organization_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/invalid-organization-id/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_map_by_id(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create();
        $contact = Contact::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $organization->scammers()->attach($scammer);
        $organization->contacts()->attach($contact);
        $organization->paymentMethods()->attach($paymentMethod);

        $centerNode = $organization->toNode()->centered();
        $scammerNode = $scammer->toNode();
        $contactNode = $contact->toNode();
        $paymentMethodNode = $paymentMethod->toNode();

        $expected = [
            'nodes' => [
                $centerNode->toArray(),
                $scammerNode->toArray(),
                $contactNode->toArray(),
                $paymentMethodNode->toArray(),
            ],
            'edges' => [
                Edge::contact(1, $contactNode, $centerNode)->toArray(),
                Edge::payment(2, $paymentMethodNode, $centerNode)->toArray(),
                Edge::linked(3, $centerNode, $scammerNode)->toArray(),
            ],
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/map");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_find_organization_map_by_invalid_id_returns400(): void
    {
        $response = $this->getJson('/api/public/organizations/9999999999999999999999999999999999999999/map');

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID']);
    }

    public function test_find_organization_map_by_non_existent_id_returns404(): void
    {
        $response = $this->getJson('/api/public/organizations/1/map');

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization map not found']);
    }

    public function test_suggest_organization_names_by_partial_case_insensitive_query(): void
    {
        Organization::factory()->create(['name' => 'Acme Payments']);
        Organization::factory()->create(['name' => 'Other Org']);

        $response = $this->getJson('/api/public/organizations/suggest?q=%20%20acme%20pay%20%20');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme Payments']);
    }

    public function test_suggest_organization_names_includes_inactive_and_soft_deleted(): void
    {
        Organization::factory()->create(['name' => 'Acme Active']);
        Organization::factory()->inactive()->create(['name' => 'Acme Inactive']);
        $deleted = Organization::factory()->create(['name' => 'Acme Deleted']);
        $deleted->delete();

        $response = $this->getJson('/api/public/organizations/suggest?q=Acme');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme Active', 'Acme Deleted', 'Acme Inactive']);
    }

    public function test_suggest_organization_names_returns_empty_array_when_query_cannot_match(): void
    {
        Organization::factory()->create(['name' => 'Acme Payments']);

        $this->getJson('/api/public/organizations/suggest')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/organizations/suggest?q=A')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/organizations/suggest?q=Nope')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/organizations/suggest?q='.str_repeat('a', 101))
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_suggest_organization_names_is_capped_at_five_and_ordered_by_name(): void
    {
        foreach (['Acme F', 'Acme A', 'Acme C', 'Acme E', 'Acme B', 'Acme D'] as $name) {
            Organization::factory()->create(['name' => $name]);
        }

        $response = $this->getJson('/api/public/organizations/suggest?q=Acme');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme A', 'Acme B', 'Acme C', 'Acme D', 'Acme E']);
    }
}
