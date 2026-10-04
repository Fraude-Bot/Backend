<?php

namespace Tests\Feature;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Contact;
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

    public function test_nested_validation_is_atomic(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/scammers', [
            'name' => 'Atomic Scammer',
            'contacts' => [[
                'platform' => PlatformType::EMAIL->value,
                'reference' => 'valid@example.com',
            ]],
            'paymentMethods' => [[
                'type' => 999,
                'reference' => 'invalid',
            ]],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('scammers', ['name' => 'Atomic Scammer']);
        $this->assertDatabaseMissing('contacts', ['reference' => 'valid@example.com']);
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

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['admin:write']);

        return $user;
    }
}
