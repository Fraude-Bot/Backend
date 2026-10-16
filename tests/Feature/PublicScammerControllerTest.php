<?php

namespace Tests\Feature;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\PaymentMethodResource;
use App\Http\Resources\Public\ReportResource;
use App\Http\Resources\Public\ScammerResource;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Report;
use App\Models\Scammer;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicScammerControllerTest extends TestCase
{
    public function test_find_scammer_by_id(): void
    {
        /**
         * @var Scammer $scammer
         */
        $scammer = Scammer::factory()->create();

        $response = $this->getJson("/api/public/scammers/{$scammer->id}");

        $response->assertStatus(200);
        $response->assertExactJson(ScammerResource::make($scammer)->resolve());
    }

    public function test_find_scammer_by_invalid_id_returns400(): void
    {
        $response = $this->getJson('/api/public/scammers/9999999999999999999999999999999999999999');

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID']);
    }

    public function test_find_scammer_by_non_existent_id_returns404(): void
    {
        $response = $this->getJson('/api/public/scammers/0');

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Scammer not found']);
    }

    public function test_find_scammer_contacts_by_id(): void
    {
        $scammer = Scammer::factory()->create();

        $contacts = Contact::factory()->count(3)->create();

        $scammer->contacts()->attach($contacts->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => ContactResource::collection($contacts)->resolve(),
            'total' => $contacts->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/scammers/{$scammer->id}/contacts?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
        $response->assertJsonPath('data.0.created_at', $contacts->first()->created_at->format('Y-m-d'));
    }

    public function test_find_scammer_contacts_by_id_with_platform_query_param(): void
    {
        $scammer = Scammer::factory()->create();
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
        $scammer->contacts()->attach($contacts->pluck('id'));

        $page = 1;
        $count = 10;
        $platform = 'instagram';
        $expected = [
            'data' => ContactResource::collection($contacts->where('platform', PlatformType::INSTAGRAM))->resolve(),
            'total' => $contacts->where('platform', PlatformType::INSTAGRAM)->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/scammers/{$scammer->id}/contacts?p={$page}&c={$count}&platform={$platform}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_contacts_total_reflects_all_matching_rows(): void
    {
        $scammer = Scammer::factory()->create();
        $contacts = Contact::factory()->count(15)->create();
        $scammer->contacts()->attach($contacts);

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_contact_pagination_is_bounded(): void
    {
        $scammer = Scammer::factory()->create();

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_contact_changes_invalidate_cached_public_contacts(): void
    {
        $scammer = Scammer::factory()->create();
        $contact = Contact::factory()->create(['reference' => 'before-handle']);
        $scammer->contacts()->attach($contact);

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts")
            ->assertJsonPath('data.0.reference', 'before-handle');

        $contact->update(['reference' => 'after-handle']);

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts")
            ->assertJsonPath('data.0.reference', 'after-handle');
    }

    public function test_find_scammer_contacts_by_id_with_invalid_page_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Scammer contacts not found']);
    }

    public function test_find_scammer_contacts_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_contacts_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/scammers/1/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_contacts_by_id_with_invalid_scammer_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/invalid-scammer-id/contacts?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_payment_methods_by_id(): void
    {
        $scammer = Scammer::factory()->create();
        $paymentMethods = PaymentMethod::factory()->count(3)->create();
        $scammer->paymentMethods()->attach($paymentMethods->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => PaymentMethodResource::collection($paymentMethods)->resolve(),
            'total' => $paymentMethods->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
        $response->assertJsonPath('data.0.created_at', $paymentMethods->first()->created_at->format('Y-m-d'));
    }

    public function test_find_scammer_payment_methods_by_id_with_type_query_param(): void
    {
        $scammer = Scammer::factory()->create();
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
        $scammer->paymentMethods()->attach($paymentMethods->pluck('id'));

        $page = 1;
        $count = 10;
        $type = 'clabe';
        $expected = [
            'data' => PaymentMethodResource::collection($paymentMethods->where('type', PaymentMethodType::CLABE))->resolve(),
            'total' => $paymentMethods->where('type', PaymentMethodType::CLABE)->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods?p={$page}&c={$count}&type={$type}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
    }

    public function test_payment_method_total_reflects_all_matching_rows(): void
    {
        $scammer = Scammer::factory()->create();
        $paymentMethods = PaymentMethod::factory()->count(15)->create();
        $scammer->paymentMethods()->attach($paymentMethods);

        $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_payment_method_pagination_is_bounded(): void
    {
        $scammer = Scammer::factory()->create();

        $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_payment_method_changes_invalidate_cached_public_payment_methods(): void
    {
        $scammer = Scammer::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['reference' => 'before-reference']);
        $scammer->paymentMethods()->attach($paymentMethod);

        $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods")
            ->assertJsonPath('data.0.reference', 'before-reference');

        $paymentMethod->update(['reference' => 'after-reference']);

        $this->getJson("/api/public/scammers/{$scammer->id}/payment-methods")
            ->assertJsonPath('data.0.reference', 'after-reference');
    }

    public function test_find_scammer_payment_methods_by_missing_id_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Scammer payment methods not found']);
    }

    public function test_find_scammer_payment_methods_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_payment_methods_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/scammers/1/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_payment_methods_by_id_with_invalid_scammer_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/invalid-scammer-id/payment-methods?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_reports_by_id(): void
    {
        $scammer = Scammer::factory()->create();
        $reports = Report::factory()->count(3)->create();
        $scammer->reports()->attach($reports->pluck('id'));

        $page = 1;
        $count = 10;
        $expected = [
            'data' => ReportResource::collection($reports)->resolve(),
            'total' => $reports->count(),
            'page' => $page,
            'count' => $count,
        ];

        $response = $this->getJson("/api/public/scammers/{$scammer->id}/reports?p={$page}&c={$count}");

        $response->assertStatus(200);
        $response->assertExactJson($expected);
        $response->assertJsonPath('data.0.created_at', $reports->first()->created_at->format('Y-m-d'));
        $response->assertJsonPath('data.0.short_description', Str::limit($reports->first()->description, 125, '...'));
    }

    public function test_reports_total_reflects_all_matching_rows(): void
    {
        $scammer = Scammer::factory()->create();
        $reports = Report::factory()->count(15)->create();
        $scammer->reports()->attach($reports);

        $this->getJson("/api/public/scammers/{$scammer->id}/reports?p=1&c=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_report_pagination_is_bounded(): void
    {
        $scammer = Scammer::factory()->create();

        $this->getJson("/api/public/scammers/{$scammer->id}/reports?p=0&c=101")
            ->assertBadRequest();
    }

    public function test_report_changes_invalidate_cached_public_reports(): void
    {
        $scammer = Scammer::factory()->create();
        $report = Report::factory()->create(['title' => 'Before']);
        $scammer->reports()->attach($report);

        $this->getJson("/api/public/scammers/{$scammer->id}/reports")
            ->assertJsonPath('data.0.title', 'Before');

        $report->update(['title' => 'After']);

        $this->getJson("/api/public/scammers/{$scammer->id}/reports")
            ->assertJsonPath('data.0.title', 'After');
    }

    public function test_inactive_reports_are_omitted(): void
    {
        $scammer = Scammer::factory()->create();
        $active = Report::factory()->create(['is_active' => true, 'title' => 'Active report']);
        $inactive = Report::factory()->create(['is_active' => false, 'title' => 'Inactive report']);
        $scammer->reports()->attach([$active->id, $inactive->id]);

        $this->getJson("/api/public/scammers/{$scammer->id}/reports")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active report')
            ->assertJsonPath('total', 1);
    }

    public function test_find_scammer_reports_by_id_with_inactive_scammer_returns404(): void
    {
        $scammer = Scammer::factory()->create(['is_active' => false]);
        $report = Report::factory()->create();
        $scammer->reports()->attach($report);

        $this->getJson("/api/public/scammers/{$scammer->id}/reports")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Scammer reports not found']);
    }

    public function test_find_scammer_reports_by_id_with_invalid_page_returns404(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/reports?p={$page}&c={$count}");

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Scammer reports not found']);
    }

    public function test_find_scammer_reports_by_id_with_invalid_page_query_param_returns400(): void
    {
        $page = 'invalid-page';
        $count = 10;

        $response = $this->getJson("/api/public/scammers/1/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_reports_by_id_with_invalid_count_query_param_returns400(): void
    {
        $page = 1;
        $count = 'invalid-count';

        $response = $this->getJson("/api/public/scammers/1/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_find_scammer_reports_by_id_with_invalid_scammer_id_param_returns400(): void
    {
        $page = 1;
        $count = 10;

        $response = $this->getJson("/api/public/scammers/invalid-scammer-id/reports?p={$page}&c={$count}");

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Invalid scammer ID, page or count']);
    }

    public function test_suggest_scammer_names_by_partial_case_insensitive_query(): void
    {
        Scammer::factory()->create(['name' => 'Acme Payments']);
        Scammer::factory()->create(['name' => 'Other Org']);

        $response = $this->getJson('/api/public/scammers/suggest?q=%20%20acme%20pay%20%20');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme Payments']);
    }

    public function test_suggest_scammer_names_includes_inactive_and_soft_deleted(): void
    {
        Scammer::factory()->create(['name' => 'Acme Active']);
        Scammer::factory()->inactive()->create(['name' => 'Acme Inactive']);
        $deleted = Scammer::factory()->create(['name' => 'Acme Deleted']);
        $deleted->delete();

        $response = $this->getJson('/api/public/scammers/suggest?q=Acme');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme Active', 'Acme Deleted', 'Acme Inactive']);
    }

    public function test_suggest_scammer_names_returns_empty_array_when_query_cannot_match(): void
    {
        Scammer::factory()->create(['name' => 'Acme Payments']);

        $this->getJson('/api/public/scammers/suggest')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/scammers/suggest?q=A')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/scammers/suggest?q=Nope')
            ->assertStatus(200)
            ->assertExactJson([]);

        $this->getJson('/api/public/scammers/suggest?q='.str_repeat('a', 101))
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_suggest_scammer_names_is_capped_at_five_and_ordered_by_name(): void
    {
        foreach (['Acme F', 'Acme A', 'Acme C', 'Acme E', 'Acme B', 'Acme D'] as $name) {
            Scammer::factory()->create(['name' => $name]);
        }

        $response = $this->getJson('/api/public/scammers/suggest?q=Acme');

        $response->assertStatus(200);
        $response->assertExactJson(['Acme A', 'Acme B', 'Acme C', 'Acme D', 'Acme E']);
    }

    public function test_create_contact_for_an_active_scammer(): void
    {
        $scammer = Scammer::factory()->create();

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts")
            ->assertOk()
            ->assertJsonPath('total', 0);

        $response = $this->postJson("/api/public/scammers/{$scammer->id}/contacts", [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ]);

        $contact = Contact::query()->first();
        $this->assertNotNull($contact);
        $this->assertSame(PlatformType::EMAIL, $contact->platform);
        $this->assertTrue($contact->is_active);
        $this->assertTrue($scammer->contacts()->whereKey($contact->id)->exists());

        $response->assertCreated();
        $response->assertExactJson(ContactResource::make($contact)->resolve());

        $this->getJson("/api/public/scammers/{$scammer->id}/contacts")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.reference', 'seller@example.com');
    }

    public function test_create_contact_accepts_an_integer_platform_and_normalizes_a_cellphone(): void
    {
        $scammer = Scammer::factory()->create();

        $response = $this->postJson("/api/public/scammers/{$scammer->id}/contacts", [
            'platform' => PlatformType::CELLPHONE->value,
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
        $scammer = Scammer::factory()->create();
        $contact = Contact::factory()->create([
            'platform' => PlatformType::EMAIL,
            'reference' => 'seller@example.com',
        ]);
        $scammer->contacts()->attach($contact);

        $payload = [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ];

        $this->postJson("/api/public/scammers/{$scammer->id}/contacts", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->postJson("/api/public/scammers/{$scammer->id}/contacts", $payload)
            ->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(1, $scammer->contacts()->count());
    }

    public function test_create_contact_restores_a_trashed_contact(): void
    {
        $scammer = Scammer::factory()->create();
        $contact = Contact::factory()->create([
            'platform' => PlatformType::EMAIL,
            'reference' => 'seller@example.com',
        ]);
        $contact->delete();

        $this->postJson("/api/public/scammers/{$scammer->id}/contacts", [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ])->assertCreated()
            ->assertJsonPath('id', $contact->id);

        $this->assertNull($contact->fresh()->deleted_at);
        $this->assertTrue($scammer->contacts()->whereKey($contact->id)->exists());
        $this->assertSame(1, Contact::withTrashed()->count());
    }

    public function test_create_contact_returns_404_when_scammer_is_missing_inactive_or_trashed(): void
    {
        $payload = [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ];

        $this->postJson('/api/public/scammers/0/contacts', $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $inactive = Scammer::factory()->inactive()->create();

        $this->postJson("/api/public/scammers/{$inactive->id}/contacts", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $trashed = Scammer::factory()->create();
        $trashed->delete();

        $this->postJson("/api/public/scammers/{$trashed->id}/contacts", $payload)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Scammer not found']);

        $this->assertSame(0, Contact::query()->count());
    }

    public function test_create_contact_returns_400_for_an_invalid_scammer_id(): void
    {
        $this->postJson('/api/public/scammers/not-an-id/contacts', [
            'platform' => 'email',
            'reference' => 'seller@example.com',
        ])->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid scammer ID']);
    }

    public function test_create_contact_validates_platform_and_reference(): void
    {
        $scammer = Scammer::factory()->create();
        $url = "/api/public/scammers/{$scammer->id}/contacts";

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['platform', 'reference']]]);

        $this->postJson($url, [
            'platform' => 'carrier',
            'reference' => 'seller@example.com',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['platform']]]);

        $this->postJson($url, [
            'platform' => 'email',
            'reference' => '   ',
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.reference.0', 'The reference field is required.');

        $this->postJson($url, [
            'platform' => 'cellphone',
            'reference' => 'abc',
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.reference.0', 'Reference cannot be empty');

        $this->assertSame(0, Contact::query()->count());
    }

    public function test_create_contact_ignores_is_active(): void
    {
        $scammer = Scammer::factory()->create();

        $this->postJson("/api/public/scammers/{$scammer->id}/contacts", [
            'platform' => 'email',
            'reference' => 'seller@example.com',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('is_active', true);

        $this->assertTrue(Contact::query()->first()->is_active);
    }
}
