<?php

namespace Tests\Feature;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    public function test_admin_routes_require_authentication(): void
    {
        $this->postJson('/api/admin/scammers', ['name' => 'Blocked'])
            ->assertUnauthorized();
    }

    public function test_admin_routes_require_role_and_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'reporter']), ['admin:write']);

        $this->postJson('/api/admin/scammers', ['name' => 'Blocked'])
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), []);

        $this->postJson('/api/admin/scammers', ['name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_store_ignores_nested_contacts_and_payment_methods(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/scammers', [
            'name' => 'Atomic Scammer',
            'contacts' => [[
                'platform' => PlatformType::EMAIL->value,
                'reference' => 'valid@example.com',
            ]],
            'paymentMethods' => [[
                'type' => 1,
                'reference' => '4111111111111111',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('scammers', ['name' => 'Atomic Scammer']);
        $this->assertDatabaseMissing('contacts', ['reference' => 'valid@example.com']);
        $this->assertDatabaseMissing('payment_methods', ['reference' => '4111111111111111']);
    }

    public function test_restore_routes_bind_the_deleted_entity_identifier(): void
    {
        $this->actingAsAdmin();
        $scammer = Scammer::factory()->create();
        $scammer->delete();

        $this->postJson("/api/admin/scammers/{$scammer->id}/restore")
            ->assertOk()
            ->assertJsonPath('id', $scammer->id);

        $this->assertNotSoftDeleted('scammers', ['id' => $scammer->id]);
    }

    public function test_contact_update_must_belong_to_nested_scammer(): void
    {
        $this->actingAsAdmin();
        $owner = Scammer::factory()->create();
        $other = Scammer::factory()->create();
        $contact = Contact::factory()->create();
        $owner->contacts()->attach($contact);

        $this->putJson("/api/admin/scammers/{$other->id}/contacts/{$contact->id}", [
            'reference' => 'unauthorized@example.com',
        ])->assertNotFound();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id, 'reference' => 'unauthorized@example.com']);
    }

    public function test_organization_contact_is_created_and_attached(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $this->postJson("/api/admin/organizations/{$organization->id}/contacts", [
            'reference' => 'desk@example.com',
        ])->assertCreated()
            ->assertJsonPath('platform', 'Other')
            ->assertJsonPath('reference', 'desk@example.com');

        $contact = Contact::query()->where('reference', 'desk@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertDatabaseHas('organizations_contacts', [
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_payment_method_routes_use_the_plural_path(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create();

        $this->postJson("/api/admin/organizations/{$organization->id}/payment-methods", [
            'reference' => '4111111111111111',
        ])->assertCreated();

        $organizationPaymentMethod = PaymentMethod::query()->where('reference', '4111111111111111')->first();
        $this->assertNotNull($organizationPaymentMethod);
        $this->assertDatabaseHas('organizations_payment_methods', [
            'organization_id' => $organization->id,
            'payment_method_id' => $organizationPaymentMethod->id,
        ]);

        $this->postJson("/api/admin/scammers/{$scammer->id}/payment-methods", [
            'reference' => '012345678901234567',
        ])->assertCreated();

        $scammerPaymentMethod = PaymentMethod::query()->where('reference', '012345678901234567')->first();
        $this->assertNotNull($scammerPaymentMethod);
        $this->assertDatabaseHas('scammers_payment_methods', [
            'scammer_id' => $scammer->id,
            'payment_method_id' => $scammerPaymentMethod->id,
        ]);
    }

    public function test_organization_scammer_link_reads_the_scammer_id_from_the_body(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create();

        $this->postJson("/api/admin/organizations/{$organization->id}/scammers", [
            'scammer_id' => $scammer->id,
        ])->assertCreated();

        $this->assertDatabaseHas('scammers_organizations', [
            'organization_id' => $organization->id,
            'scammer_id' => $scammer->id,
        ]);

        $this->postJson("/api/admin/organizations/{$organization->id}/scammers", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['scammer_id']]]);
    }

    public function test_replaced_admin_link_paths_are_not_registered(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();
        $scammer = Scammer::factory()->create();

        $this->postJson("/api/admin/organizations/{$organization->id}/payment", [
            'type' => PaymentMethodType::CARD_NUMBER->value,
            'reference' => '4222222222222222',
        ])->assertNotFound();

        $this->postJson("/api/admin/scammers/{$scammer->id}/payment", [
            'type' => PaymentMethodType::CARD_NUMBER->value,
            'reference' => '4333333333333333',
        ])->assertNotFound();

        $this->postJson("/api/admin/organizations/{$organization->id}/scammer/{$scammer->id}")
            ->assertNotFound();
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['admin:write']);

        return $user;
    }
}
