<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\Edge;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\OrganizationResource;
use App\Http\Resources\Public\OrganizationScammerResource;
use App\Http\Resources\Public\PaymentMethodResource;
use App\Http\Resources\Public\ReportResource;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Report;
use App\Models\Scammer;
use Illuminate\Support\Facades\Storage;
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

    public function test_find_organization_payment_methods_by_id(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethods = PaymentMethod::factory()->count(3)->create();
        $organization->paymentMethods()->attach($paymentMethods->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => PaymentMethodResource::collection($paymentMethods)->resolve(),
            'total' => $paymentMethods->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
        $response->assertJsonPath('data.0.created_at', $paymentMethods->first()->created_at->format('Y-m-d'));
    }

    public function test_find_organization_payment_methods_by_id_with_type_query_param(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethods = PaymentMethod::factory()->createMany([
            [
                'reference' => '012345678901234567',
                'type' => PaymentMethodType::CLABE,
                'is_active' => true,
            ],
            [
                'reference' => '4111111111111111',
                'type' => PaymentMethodType::CARD_NUMBER,
                'is_active' => true,
            ],
            [
                'reference' => '0xabc123',
                'type' => PaymentMethodType::WALLET,
                'is_active' => true,
            ],
        ]);
        $organization->paymentMethods()->attach($paymentMethods->pluck('id'));

        $page = 1;
        $count = 10;
        $type = 'clabe';
        $expected = [
            'data' => PaymentMethodResource::collection($paymentMethods->where('type', PaymentMethodType::CLABE))->resolve(),
            'total' => $paymentMethods->where('type', PaymentMethodType::CLABE)->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/organizations/{$organization->id}/payment-methods?p={$page}&c={$count}&type={$type}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_payment_method_total_reflects_all_matching_rows(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethods = PaymentMethod::factory()->count(15)->create();
        $organization->paymentMethods()->attach($paymentMethods);

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_payment_method_pagination_is_bounded(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_payment_method_changes_invalidate_cached_public_organization_payment_methods(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['reference' => 'before-reference']);
        $organization->paymentMethods()->attach($paymentMethod);

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods")
            ->assertJsonPath('data.0.reference', 'before-reference');

        $paymentMethod->update(['reference' => 'after-reference']);

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods")
            ->assertJsonPath('data.0.reference', 'after-reference');
    }

    public function test_find_organization_payment_methods_by_missing_id_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization payment methods not found']);
    }

    public function test_find_organization_payment_methods_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_payment_methods_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/organizations/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_payment_methods_by_id_with_invalid_organization_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/invalid-organization-id/payment-methods?p={$page}&c={$count}");

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

    public function test_find_organization_scammers_by_id(): void
    {
        $organization = Organization::factory()->create();
        $later = Scammer::factory()->create([
            'name' => 'Zed',
            'created_at' => '2026-03-02 10:00:00',
        ]);
        $earlier = Scammer::factory()->create([
            'name' => 'Ada',
            'created_at' => '2026-01-15 10:00:00',
        ]);
        $organization->scammers()->attach([$later->id, $earlier->id]);

        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/{$organization->id}/scammers?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson([
            'data' => OrganizationScammerResource::collection([$earlier, $later])->resolve(),
            'total' => 2,
            'page' => $page,
            'count' => $count,
        ]);
        $response->assertJsonPath('data.0.created_at', '2026-01-15');
        $response->assertJsonPath('data.1.name', 'Zed');
    }

    public function test_organization_scammers_total_reflects_all_matching_rows(): void
    {
        $organization = Organization::factory()->create();
        $scammers = Scammer::factory()->count(15)->create();
        $organization->scammers()->attach($scammers);

        $this->getJson("/api/public/organizations/{$organization->id}/scammers?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_organization_scammer_pagination_is_bounded(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/scammers?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_scammer_changes_invalidate_cached_public_organization_scammers(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create(['name' => 'Before']);
        $organization->scammers()->attach($scammer);

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertJsonPath('data.0.name', 'Before');

        $scammer->update(['name' => 'After']);

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertJsonPath('data.0.name', 'After');
    }

    public function test_inactive_and_deleted_organization_scammers_are_omitted(): void
    {
        $organization = Organization::factory()->create();
        $active = Scammer::factory()->create(['name' => 'Active scammer']);
        $inactive = Scammer::factory()->create(['name' => 'Inactive scammer', 'is_active' => false]);
        $deleted = Scammer::factory()->create(['name' => 'Deleted scammer']);
        $organization->scammers()->attach([$active->id, $inactive->id, $deleted->id]);
        $deleted->delete();

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active scammer')
            ->assertJsonPath('total', 1);
    }

    public function test_find_organization_scammers_by_id_with_inactive_organization_returns404(): void
    {
        $organization = Organization::factory()->create(['is_active' => false]);
        $scammer = Scammer::factory()->create();
        $organization->scammers()->attach($scammer);

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Organization scammers not found']);
    }

    public function test_find_organization_scammers_by_id_with_missing_organization_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/scammers?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Organization scammers not found']);
    }

    public function test_find_organization_scammers_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/organizations/1/scammers?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_scammers_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/organizations/1/scammers?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid organization ID, page or count']);
    }

    public function test_find_organization_scammers_by_id_with_invalid_organization_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/organizations/invalid-organization-id/scammers?p={$page}&c={$count}");

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

    public function test_create_contact_for_an_active_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/contacts")
            ->assertOk()
            ->assertJsonPath('total', 0);

        $response = $this->postJson("/api/public/organizations/{$organization->id}/contacts", [
            'reference' => 'seller@example.com',
        ]);

        $contact = Contact::query()->first();
        $this->assertNotNull($contact);
        $this->assertSame(PlatformType::OTHER, $contact->platform);
        $this->assertTrue($contact->is_active);
        $this->assertTrue($organization->contacts()->whereKey($contact->id)->exists());

        $response->assertCreated();
        $response->assertExactJson(ContactResource::make($contact)->resolve());

        $this->getJson("/api/public/organizations/{$organization->id}/contacts")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.reference', 'seller@example.com');
    }

    public function test_create_contact_infers_a_cellphone_and_normalizes_it(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson("/api/public/organizations/{$organization->id}/contacts", [
            'reference' => '+52 55 1111 2222',
        ]);

        $contact = Contact::query()->first();
        $this->assertNotNull($contact);
        $this->assertSame(PlatformType::CELLPHONE, $contact->platform);
        $this->assertSame('525511112222', $contact->reference);

        $response->assertCreated();
        $response->assertExactJson(ContactResource::make($contact)->resolve());
    }

    public function test_create_contact_reuses_an_existing_contact_and_reattaches(): void
    {
        $organization = Organization::factory()->create();
        $contact = Contact::factory()->create([
            'platform' => PlatformType::OTHER,
            'reference' => 'seller@example.com',
        ]);
        $organization->contacts()->attach($contact);

        $payload = [
            'reference' => 'seller@example.com',
        ];

        $this->postJson("/api/public/organizations/{$organization->id}/contacts", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->postJson("/api/public/organizations/{$organization->id}/contacts", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(1, $organization->contacts()->count());
    }

    public function test_create_contact_restores_a_trashed_contact(): void
    {
        $organization = Organization::factory()->create();
        $contact = Contact::factory()->create([
            'platform' => PlatformType::OTHER,
            'reference' => 'seller@example.com',
        ]);
        $contact->delete();

        $this->postJson("/api/public/organizations/{$organization->id}/contacts", [
            'reference' => 'seller@example.com',
        ])->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->assertNull($contact->fresh()->deleted_at);
        $this->assertTrue($organization->contacts()->whereKey($contact->id)->exists());
        $this->assertSame(1, Contact::withTrashed()->count());
    }

    public function test_create_contact_returns_404_when_organization_is_missing_inactive_or_trashed(): void
    {
        $payload = [
            'reference' => 'seller@example.com',
        ];

        $this->postJson('/api/public/organizations/0/contacts', $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $inactive = Organization::factory()->inactive()->create();

        $this->postJson("/api/public/organizations/{$inactive->id}/contacts", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $trashed = Organization::factory()->create();
        $trashed->delete();

        $this->postJson("/api/public/organizations/{$trashed->id}/contacts", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->assertSame(0, Contact::query()->count());
    }

    public function test_create_contact_returns_400_for_an_invalid_organization_id(): void
    {
        $this->postJson('/api/public/organizations/not-an-id/contacts', [
            'reference' => 'seller@example.com',
        ])->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid organization ID']);
    }

    public function test_create_contact_validates_reference(): void
    {
        $organization = Organization::factory()->create();
        $url = "/api/public/organizations/{$organization->id}/contacts";

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['reference']]]);

        $this->postJson($url, [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['platform']]]);

        $this->postJson($url, [
            'reference' => '   ',
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.reference.0', 'The reference field is required.');

        $this->assertSame(0, Contact::query()->count());
    }

    public function test_create_contact_ignores_is_active(): void
    {
        $organization = Organization::factory()->create();

        $this->postJson("/api/public/organizations/{$organization->id}/contacts", [
            'reference' => 'seller@example.com',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('is_active', true);

        $this->assertTrue(Contact::query()->first()->is_active);
    }

    public function test_create_payment_method_for_an_active_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods")
            ->assertOk()
            ->assertJsonPath('total', 0);

        $response = $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => '012345678901234567',
        ]);

        $paymentMethod = PaymentMethod::query()->first();
        $this->assertNotNull($paymentMethod);
        $this->assertSame(PaymentMethodType::CLABE, $paymentMethod->type);
        $this->assertSame('012345678901234567', $paymentMethod->reference);
        $this->assertTrue($paymentMethod->is_active);
        $this->assertTrue($organization->paymentMethods()->whereKey($paymentMethod->id)->exists());

        $response->assertCreated();
        $response->assertExactJson(PaymentMethodResource::make($paymentMethod)->resolve());

        $this->getJson("/api/public/organizations/{$organization->id}/payment-methods")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $paymentMethod->id)
            ->assertJsonPath('data.0.reference', '012345678901234567');
    }

    public function test_create_payment_method_trims_the_reference_and_infers_clabe(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => ' 012345678901234567 ',
        ]);

        $paymentMethod = PaymentMethod::query()->first();
        $this->assertNotNull($paymentMethod);
        $this->assertSame(PaymentMethodType::CLABE, $paymentMethod->type);
        $this->assertSame('012345678901234567', $paymentMethod->reference);

        $response->assertCreated();
        $response->assertExactJson(PaymentMethodResource::make($paymentMethod)->resolve());
    }

    public function test_create_payment_method_reuses_an_existing_row_and_attaches_it(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::CLABE,
            'reference' => '012345678901234567',
        ]);

        $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => '012345678901234567',
        ])->assertCreated()
            ->assertJsonPath('id', $paymentMethod->id);

        $this->assertSame(1, PaymentMethod::query()->count());
        $this->assertTrue($organization->paymentMethods()->whereKey($paymentMethod->id)->exists());
    }

    public function test_create_payment_method_restores_a_trashed_payment_method(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::CLABE,
            'reference' => '012345678901234567',
        ]);
        $paymentMethod->delete();

        $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => '012345678901234567',
        ])->assertCreated()
            ->assertJsonPath('id', $paymentMethod->id);

        $this->assertNull($paymentMethod->fresh()->deleted_at);
        $this->assertTrue($organization->paymentMethods()->whereKey($paymentMethod->id)->exists());
        $this->assertSame(1, PaymentMethod::withTrashed()->count());
    }

    public function test_create_payment_method_returns_422_when_already_linked(): void
    {
        $organization = Organization::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::CLABE,
            'reference' => '012345678901234567',
        ]);
        $organization->paymentMethods()->attach($paymentMethod);

        $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => '012345678901234567',
        ])->assertUnprocessable()
            ->assertExactJson(['error' => 'Payment method already exists for this organization']);

        $this->assertSame(1, PaymentMethod::query()->count());
        $this->assertSame(1, $organization->paymentMethods()->count());
    }

    public function test_create_payment_method_returns_404_when_organization_is_missing_inactive_or_trashed(): void
    {
        $payload = [
            'reference' => '012345678901234567',
        ];

        $this->postJson('/api/public/organizations/0/payment-methods', $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $inactive = Organization::factory()->inactive()->create();

        $this->postJson("/api/public/organizations/{$inactive->id}/payment-methods", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $trashed = Organization::factory()->create();
        $trashed->delete();

        $this->postJson("/api/public/organizations/{$trashed->id}/payment-methods", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->assertSame(0, PaymentMethod::query()->count());
    }

    public function test_create_payment_method_returns_400_for_an_invalid_organization_id(): void
    {
        $this->postJson('/api/public/organizations/not-an-id/payment-methods', [
            'reference' => '012345678901234567',
        ])->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid organization ID']);
    }

    public function test_create_payment_method_validates_reference(): void
    {
        $organization = Organization::factory()->create();
        $url = "/api/public/organizations/{$organization->id}/payment-methods";

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['reference']]]);

        $this->postJson($url, [
            'type' => 'clabe',
            'reference' => '012345678901234567',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['type']]]);

        $this->postJson($url, [
            'reference' => '   ',
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.reference.0', 'The reference field is required.');

        $this->assertSame(0, PaymentMethod::query()->count());
    }

    public function test_create_payment_method_ignores_is_active(): void
    {
        $organization = Organization::factory()->create();

        $this->postJson("/api/public/organizations/{$organization->id}/payment-methods", [
            'reference' => '012345678901234567',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('is_active', true);

        $this->assertTrue(PaymentMethod::query()->first()->is_active);
    }

    public function test_create_scammer_for_an_active_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertOk()
            ->assertJsonPath('total', 0);

        $response = $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => '  Jane Doe  ',
        ]);

        $scammer = Scammer::query()->first();
        $this->assertNotNull($scammer);
        $this->assertSame('Jane Doe', $scammer->name);
        $this->assertTrue($scammer->is_active);
        $this->assertNull($scammer->profile_picture_path);
        $this->assertTrue($organization->scammers()->whereKey($scammer->id)->exists());

        $response->assertCreated();
        $response->assertExactJson(OrganizationScammerResource::make($scammer)->resolve());

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $scammer->id)
            ->assertJsonPath('data.0.name', 'Jane Doe');
    }

    public function test_create_scammer_reuses_an_existing_scammer_and_reattaches(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create([
            'name' => 'Jane Doe',
        ]);
        $organization->scammers()->attach($scammer);

        $payload = [
            'name' => '  jane doe  ',
        ];

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $scammer->id)
            ->assertJsonPath('name', 'Jane Doe');

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $this->assertSame(1, Scammer::query()->count());
        $this->assertSame(1, $organization->scammers()->count());
    }

    public function test_create_scammer_restores_a_trashed_scammer(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create([
            'name' => 'Jane Doe',
        ]);
        $scammer->delete();

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
        ])->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $this->assertNull($scammer->fresh()->deleted_at);
        $this->assertTrue($organization->scammers()->whereKey($scammer->id)->exists());
        $this->assertSame(1, Scammer::withTrashed()->count());
    }

    public function test_create_scammer_reuses_an_inactive_scammer(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->inactive()->create([
            'name' => 'Jane Doe',
        ]);

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
        ])->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $this->assertFalse($scammer->fresh()->is_active);
        $this->assertTrue($organization->scammers()->whereKey($scammer->id)->exists());

        $this->getJson("/api/public/organizations/{$organization->id}/scammers")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_create_scammer_returns_404_when_organization_is_missing_inactive_or_trashed(): void
    {
        $payload = [
            'name' => 'Jane Doe',
        ];

        $this->postJson('/api/public/organizations/0/scammers', $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $inactive = Organization::factory()->inactive()->create();

        $this->postJson("/api/public/organizations/{$inactive->id}/scammers", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $trashed = Organization::factory()->create();
        $trashed->delete();

        $this->postJson("/api/public/organizations/{$trashed->id}/scammers", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Organization not found']);

        $this->assertSame(0, Scammer::query()->count());
    }

    public function test_create_scammer_returns_400_for_an_invalid_organization_id(): void
    {
        $this->postJson('/api/public/organizations/not-an-id/scammers', [
            'name' => 'Jane Doe',
        ])->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid organization ID']);
    }

    public function test_create_scammer_validates_name(): void
    {
        $organization = Organization::factory()->create();
        $url = "/api/public/organizations/{$organization->id}/scammers";

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['name']]]);

        $this->postJson($url, [
            'name' => '   ',
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.name.0', 'The name field is required.');

        $this->postJson($url, [
            'name' => str_repeat('a', 101),
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);

        $this->assertSame(0, Scammer::query()->count());
    }

    public function test_create_scammer_ignores_is_active(): void
    {
        $organization = Organization::factory()->create();

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'is_active' => false,
        ])->assertCreated();

        $this->assertTrue(Scammer::query()->first()->is_active);
    }

    public function test_create_scammer_stores_a_profile_picture(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $picture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'profile_picture_path' => $picture,
        ])->assertCreated();

        $scammer = Scammer::query()->first();
        $this->assertNotNull($scammer);
        $this->assertStringStartsWith('reports/scammers/profiles/', $scammer->profile_picture_path);
        $this->assertSame('avatar', Storage::disk('public')->get($scammer->profile_picture_path));
        $this->assertTrue($organization->scammers()->whereKey($scammer->id)->exists());
    }

    public function test_create_scammer_accepts_a_null_profile_picture_path(): void
    {
        $organization = Organization::factory()->create();

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'profile_picture_path' => null,
        ])->assertCreated();

        $this->assertNull(Scammer::query()->first()->profile_picture_path);
    }

    public function test_create_scammer_keeps_an_existing_profile_picture(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/scammers/profiles/kept.jpg', 'kept');
        $organization = Organization::factory()->create();
        $existing = Scammer::factory()->create([
            'name' => 'Jane Doe',
            'profile_picture_path' => 'reports/scammers/profiles/kept.jpg',
        ]);
        $unused = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'unused.jpg', 'unused');

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => '  jane doe  ',
            'profile_picture_path' => $unused,
        ])->assertCreated()
            ->assertJsonPath('id', $existing->id);

        $this->assertSame('reports/scammers/profiles/kept.jpg', $existing->fresh()->profile_picture_path);
        $this->assertSame('kept', Storage::disk('public')->get('reports/scammers/profiles/kept.jpg'));
        $this->assertSame(
            ['reports/scammers/profiles/kept.jpg'],
            collect(Storage::disk('public')->allFiles())
                ->filter(fn (string $path): bool => str_starts_with($path, 'reports/scammers/profiles/'))
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_create_scammer_restores_a_trashed_scammer_and_keeps_its_picture(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/scammers/profiles/kept.jpg', 'kept');
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create([
            'name' => 'Jane Doe',
            'profile_picture_path' => 'reports/scammers/profiles/kept.jpg',
        ]);
        $scammer->delete();
        $unused = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'unused.jpg', 'unused');

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'profile_picture_path' => $unused,
        ])->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $restored = $scammer->fresh();
        $this->assertNull($restored->deleted_at);
        $this->assertSame('reports/scammers/profiles/kept.jpg', $restored->profile_picture_path);
        $this->assertSame(
            ['reports/scammers/profiles/kept.jpg'],
            collect(Storage::disk('public')->allFiles())
                ->filter(fn (string $path): bool => str_starts_with($path, 'reports/scammers/profiles/'))
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_create_scammer_rejects_a_profile_picture_outside_the_temporary_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');
        $organization = Organization::factory()->create();

        $response = $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'profile_picture_path' => $this->storagePath('other/avatar.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('profile_picture_path', $response->json('error.details'));
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, $organization->scammers()->count());
        $this->assertSame(['other/avatar.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_create_scammer_attaches_contacts_and_payment_methods(): void
    {
        $organization = Organization::factory()->create();
        $trashedContact = Contact::factory()->create([
            'platform' => PlatformType::CELLPHONE,
            'reference' => '525511112222',
            'is_active' => false,
        ]);
        $trashedContact->delete();
        $existingPayment = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::CLABE,
            'reference' => '032180000118359719',
        ]);

        $response = $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'contacts' => [
                ['reference' => '+52 55 1111 2222', 'is_active' => false],
                ['reference' => 'caja@example.com'],
            ],
            'payment_methods' => [
                ['reference' => '032 180 000118359719', 'is_active' => false],
                ['reference' => '4152 3412 5132 1531'],
            ],
        ]);

        $scammer = Scammer::query()->first();
        $this->assertNotNull($scammer);

        $response->assertCreated();
        $response->assertExactJson(OrganizationScammerResource::make($scammer)->resolve());

        $contacts = $scammer->contacts()->orderBy('contacts.id')->get();
        $this->assertCount(2, $contacts);
        $this->assertSame($trashedContact->id, $contacts[0]->id);
        $this->assertNull($contacts[0]->deleted_at);
        $this->assertFalse($contacts[0]->is_active);
        $this->assertSame(PlatformType::CELLPHONE, $contacts[0]->platform);
        $this->assertSame('525511112222', $contacts[0]->reference);
        $this->assertSame(PlatformType::OTHER, $contacts[1]->platform);
        $this->assertSame('caja@example.com', $contacts[1]->reference);
        $this->assertTrue($contacts[1]->is_active);

        $paymentMethods = $scammer->paymentMethods()->orderBy('payment_methods.id')->get();
        $this->assertCount(2, $paymentMethods);
        $this->assertSame($existingPayment->id, $paymentMethods[0]->id);
        $this->assertSame(PaymentMethodType::CLABE, $paymentMethods[0]->type);
        $this->assertSame('032180000118359719', $paymentMethods[0]->reference);
        $this->assertSame(PaymentMethodType::CARD_NUMBER, $paymentMethods[1]->type);
        $this->assertSame('4152341251321531', $paymentMethods[1]->reference);
        $this->assertTrue($paymentMethods[1]->is_active);

        $this->assertSame(0, $organization->contacts()->count());
        $this->assertSame(0, $organization->paymentMethods()->count());

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'jane doe',
            'contacts' => [
                ['reference' => '+52 55 1111 2222'],
                ['reference' => 'caja@example.com'],
            ],
            'payment_methods' => [
                ['reference' => '032180000118359719'],
                ['reference' => '4152341251321531'],
            ],
        ])->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $this->assertSame(1, Scammer::query()->count());
        $this->assertSame(1, $organization->scammers()->count());
        $this->assertSame(2, $scammer->contacts()->count());
        $this->assertSame(2, $scammer->paymentMethods()->count());
        $this->assertSame(2, Contact::withTrashed()->count());
        $this->assertSame(2, PaymentMethod::query()->count());
    }

    public function test_create_scammer_attaches_contacts_when_reusing_a_trashed_scammer(): void
    {
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create([
            'name' => 'Jane Doe',
        ]);
        $scammer->delete();

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'contacts' => [
                ['reference' => 'caja@example.com'],
            ],
            'payment_methods' => [],
        ])->assertCreated()
            ->assertJsonPath('id', $scammer->id);

        $restored = $scammer->fresh();
        $this->assertNull($restored->deleted_at);
        $this->assertSame(['caja@example.com'], $restored->contacts()->pluck('reference')->all());
        $this->assertSame(0, $restored->paymentMethods()->count());
        $this->assertSame(0, $organization->contacts()->count());
        $this->assertTrue($organization->scammers()->whereKey($scammer->id)->exists());
    }

    public function test_create_scammer_validates_contacts_and_payment_methods(): void
    {
        $organization = Organization::factory()->create();
        $url = "/api/public/organizations/{$organization->id}/scammers";
        $payload = [
            'name' => 'Jane Doe',
        ];

        $missingReference = $this->postJson($url, [
            ...$payload,
            'contacts' => [['name' => 'Caja']],
            'payment_methods' => [[]],
        ]);
        $missingReference->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('contacts.0.reference', $missingReference->json('error.details'));
        $this->assertArrayHasKey('payment_methods.0.reference', $missingReference->json('error.details'));

        $prohibited = $this->postJson($url, [
            ...$payload,
            'contacts' => [
                ['platform' => 'email', 'reference' => 'caja@example.com'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '032180000118359719'],
            ],
        ]);
        $prohibited->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('contacts.0.platform', $prohibited->json('error.details'));
        $this->assertArrayHasKey('payment_methods.0.type', $prohibited->json('error.details'));

        $this->postJson($url, [
            ...$payload,
            'contacts' => [
                ['reference' => str_repeat('a', 256)],
            ],
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['contacts.0.reference']]]);

        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Contact::query()->count());
        $this->assertSame(0, PaymentMethod::query()->count());
        $this->assertSame(0, $organization->scammers()->count());
    }

    public function test_create_scammer_does_not_store_contacts_when_the_picture_is_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');
        $organization = Organization::factory()->create();

        $this->postJson("/api/public/organizations/{$organization->id}/scammers", [
            'name' => 'Jane Doe',
            'profile_picture_path' => $this->storagePath('other/avatar.jpg'),
            'contacts' => [
                ['reference' => 'caja@example.com'],
            ],
            'payment_methods' => [
                ['reference' => '032180000118359719'],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Contact::query()->count());
        $this->assertSame(0, PaymentMethod::query()->count());
    }

    private function publishTemporary(string $directory, string $filename, string $contents): string
    {
        $relative = $directory.'/'.$filename;
        Storage::disk('public')->put($relative, $contents);

        return $this->storagePath($relative);
    }

    private function storagePath(string $relative): string
    {
        $urlPath = parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH);

        return rtrim(is_string($urlPath) ? $urlPath : '', '/').'/'.$relative;
    }
}
