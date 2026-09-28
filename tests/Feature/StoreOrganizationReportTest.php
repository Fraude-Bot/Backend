<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Report;
use App\Models\ReportProof;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreOrganizationReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_creates_an_organization_report_with_contacts_payment_methods_and_images(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $proofs = [
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'one.jpg', 'one'),
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'two.png', 'two'),
        ];

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Fake store took my money',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'proofs' => $proofs,
            'organization' => [
                'name' => 'Tienda Falsa',
            ],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
                ['name' => 'Mail', 'platform' => PlatformType::EMAIL->value, 'reference' => 'seller@example.com'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
                ['type' => PaymentMethodType::CARD_NUMBER->value, 'reference' => '4111 1111 1111 1111'],
            ],
        ]);

        $response->assertCreated();

        $organization = Organization::query()->first();
        $report = Report::query()->first();

        $this->assertNotNull($organization);
        $this->assertNotNull($report);
        $this->assertSame($report->id, $response->json('id'));
        $this->assertSame($organization->id, $response->json('organization_id'));
        $this->assertNull($report->user_id);
        $this->assertTrue($report->is_active);
        $this->assertSame('Fake store took my money', $report->title);
        $this->assertSame('They never shipped the order.', $report->description);
        $this->assertTrue($organization->is_active);
        $this->assertSame('Tienda Falsa', $organization->name);
        $this->assertTrue($organization->reports()->whereKey($report->id)->exists());

        $this->assertStringStartsWith('reports/organizations/avatars/', $organization->profile_picture_path);
        $this->assertSame('avatar', Storage::disk('public')->get($organization->profile_picture_path));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'));

        $storedProofs = ReportProof::query()->where('report_id', $report->id)->orderBy('id')->get();
        $this->assertCount(2, $storedProofs);
        $this->assertSame($storedProofs->pluck('id')->all(), $response->json('report_proof_ids'));
        $this->assertSame(['one', 'two'], [
            Storage::disk('public')->get($storedProofs[0]->path),
            Storage::disk('public')->get($storedProofs[1]->path),
        ]);
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/one.jpg'));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/two.png'));

        $contacts = Contact::query()->orderBy('id')->get();
        $this->assertCount(2, $contacts);
        $this->assertSame($contacts->pluck('id')->all(), $response->json('contact_ids'));
        $this->assertSame(PlatformType::CELLPHONE, $contacts[0]->platform);
        $this->assertSame('525511112222', $contacts[0]->reference);
        $this->assertSame('seller@example.com', $contacts[1]->reference);
        $this->assertEqualsCanonicalizing($contacts->modelKeys(), $organization->contacts()->pluck('contacts.id')->all());

        $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
        $this->assertCount(2, $paymentMethods);
        $this->assertSame($paymentMethods->pluck('id')->all(), $response->json('payment_method_ids'));
        $this->assertSame('012345678901234567', $paymentMethods[0]->reference);
        $this->assertSame('4111111111111111', $paymentMethods[1]->reference);
        $this->assertEqualsCanonicalizing($paymentMethods->modelKeys(), $organization->paymentMethods()->pluck('payment_methods.id')->all());
    }

    public function test_reuses_an_existing_payment_method_and_restores_a_trashed_contact(): void
    {
        $contact = Contact::create([
            'name' => 'Original',
            'platform' => PlatformType::CELLPHONE,
            'reference' => '5215512345678',
            'is_active' => true,
        ]);
        $contact->delete();

        $paymentMethod = PaymentMethod::create([
            'type' => PaymentMethodType::CLABE,
            'reference' => '032180000118359719',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Called me again',
            'organization' => ['name' => 'Another shop'],
            'contacts' => [
                ['name' => 'Different name', 'platform' => 'cellphone', 'reference' => '+52 155 1234 5678'],
            ],
            'payment_methods' => [
                ['type' => 'CLABE', 'reference' => '032 180 0001 1835 9719'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('contact_ids', [$contact->id]);
        $response->assertJsonPath('payment_method_ids', [$paymentMethod->id]);
        $response->assertJsonPath('report_proof_ids', []);

        $this->assertSame(1, Contact::withTrashed()->count());
        $this->assertSame(1, PaymentMethod::withTrashed()->count());
        $this->assertNull($contact->fresh()->deleted_at);
        $this->assertSame('Original', $contact->fresh()->name);
        $this->assertNull(Report::query()->first()->user_id);

        $organization = Organization::query()->first();
        $this->assertNull($organization->profile_picture_path);
        $this->assertTrue($organization->contacts()->whereKey($contact->id)->exists());
        $this->assertTrue($organization->paymentMethods()->whereKey($paymentMethod->id)->exists());
    }

    public function test_rejects_an_unknown_platform(): void
    {
        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'organization' => ['name' => 'Shop'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'myspace', 'reference' => 'seller'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('contacts.0.platform', $response->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, Report::query()->count());
    }

    public function test_rejects_a_profile_picture_outside_the_temporary_directory(): void
    {
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'organization' => ['name' => 'Shop'],
            'profile_picture' => $this->storagePath('other/avatar.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('profile_picture', $response->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(['other/avatar.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_rejects_an_invalid_clabe(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Shop'],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '12345'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('payment_methods.0.reference', $response->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, PaymentMethod::query()->count());
        $this->assertSame(
            [TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'],
            Storage::disk('public')->allFiles(),
        );
    }

    public function test_removes_copied_images_when_a_later_proof_is_rejected(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'organization' => ['name' => 'Shop'],
            'profile_picture' => $profile,
            'proofs' => [$this->storagePath(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg')],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('proofs.0', $response->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(
            [TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'],
            Storage::disk('public')->allFiles(),
        );
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
