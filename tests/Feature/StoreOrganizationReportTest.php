<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Report;
use App\Models\ReportProof;
use App\Models\Scammer;
use App\Models\User;
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
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'proofs' => $proofs,
            'organization' => [
                'name' => 'Tienda Falsa',
            ],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
                ['platform' => PlatformType::EMAIL->value, 'reference' => 'seller@example.com'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
                ['type' => PaymentMethodType::CARD_NUMBER->value, 'reference' => '4111 1111 1111 1111'],
            ],
            'products' => ['Crypto'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('scammer_ids', []);
        $this->assertSame(0, Scammer::query()->count());

        $product = Product::query()->where('name', 'Crypto')->first();
        $this->assertNotNull($product);
        $response->assertJsonPath('product_ids', [$product->id]);

        $organization = Organization::query()->first();
        $report = Report::query()->first();

        $this->assertNotNull($organization);
        $this->assertNotNull($report);
        $this->assertSame($report->id, $response->json('id'));
        $this->assertSame($organization->id, $response->json('organization_id'));
        $user = User::query()->where('email', 'reporter@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, $report->user_id);
        $this->assertTrue($report->is_active);
        $this->assertSame('Fake store took my money', $report->title);
        $this->assertSame('They never shipped the order.', $report->description);
        $this->assertTrue($organization->is_active);
        $this->assertSame('Tienda Falsa', $organization->name);
        $this->assertTrue($organization->reports()->whereKey($report->id)->exists());
        $this->assertTrue($report->products()->whereKey($product->id)->exists());

        $this->assertStringStartsWith('reports/organizations/profiles/', $organization->profile_picture_path);
        $this->assertSame('avatar', Storage::disk('public')->get($organization->profile_picture_path));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'));

        $storedProofs = ReportProof::query()->where('report_id', $report->id)->orderBy('id')->get();
        $this->assertCount(2, $storedProofs);
        $this->assertSame($storedProofs->pluck('id')->all(), $response->json('report_proof_ids'));
        $this->assertSame(['one', 'two'], [
            Storage::disk('public')->get($storedProofs[0]->path),
            Storage::disk('public')->get($storedProofs[1]->path),
        ]);
        $this->assertStringStartsWith('reports/organizations/proofs/', $storedProofs[0]->path);
        $this->assertStringStartsWith('reports/organizations/proofs/', $storedProofs[1]->path);
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

        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Called me again',
            'email' => 'reporter@example.com',
            'description' => 'They called again.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Another shop'],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '+52 155 1234 5678'],
            ],
            'payment_methods' => [
                ['type' => 'CLABE', 'reference' => '032 180 0001 1835 9719'],
            ],
            'products' => ['Crypto'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('contact_ids', [$contact->id]);
        $response->assertJsonPath('payment_method_ids', [$paymentMethod->id]);
        $response->assertJsonPath('report_proof_ids', []);

        $product = Product::query()->where('name', 'Crypto')->first();
        $this->assertNotNull($product);
        $response->assertJsonPath('product_ids', [$product->id]);

        $this->assertSame(1, Contact::withTrashed()->count());
        $this->assertSame(1, PaymentMethod::withTrashed()->count());
        $this->assertNull($contact->fresh()->deleted_at);
        $user = User::query()->where('email', 'reporter@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, Report::query()->first()->user_id);

        $organization = Organization::query()->first();
        $this->assertStringStartsWith('reports/organizations/profiles/', $organization->profile_picture_path);
        $this->assertTrue($organization->contacts()->whereKey($contact->id)->exists());
        $this->assertTrue($organization->paymentMethods()->whereKey($paymentMethod->id)->exists());
    }

    public function test_creates_products_reuses_an_existing_name_and_links_each_name_once(): void
    {
        $existing = Product::query()->create(['name' => 'Crypto']);
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Sold me crypto',
            'email' => 'reporter@example.com',
            'description' => 'They sold a token.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['  Crypto  ', 'crypto', 'Banking', 'Banking'],
        ]);

        $response->assertCreated();

        $banking = Product::query()->where('name', 'Banking')->first();
        $report = Report::query()->first();

        $this->assertNotNull($banking);
        $this->assertNotNull($report);
        $this->assertSame(2, Product::query()->count());
        $this->assertSame('Crypto', $existing->fresh()->name);
        $response->assertJsonPath('product_ids', [$existing->id, $banking->id]);
        $this->assertEqualsCanonicalizing(
            [$existing->id, $banking->id],
            $report->products()->pluck('products.id')->all(),
        );
    }

    public function test_rejects_an_unknown_platform(): void
    {
        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'email' => 'reporter@example.com',
            'description' => 'A description',
            'profile_picture' => '/storage/tmp/reports/profile/avatar.jpg',
            'organization' => ['name' => 'Shop'],
            'contacts' => [
                ['platform' => 'myspace', 'reference' => 'seller'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
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
            'email' => 'reporter@example.com',
            'description' => 'A description',
            'organization' => ['name' => 'Shop'],
            'profile_picture' => $this->storagePath('other/avatar.jpg'),
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
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
            'email' => 'reporter@example.com',
            'description' => 'A description',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Shop'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '12345'],
            ],
            'products' => ['Crypto'],
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
            'email' => 'reporter@example.com',
            'description' => 'A description',
            'organization' => ['name' => 'Shop'],
            'profile_picture' => $profile,
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
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

    public function test_accepts_an_empty_scammer_list(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'scammers' => [],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('scammer_ids', []);
        $this->assertSame(0, Scammer::query()->count());
    }

    public function test_links_optional_scammers_by_name_without_attaching_the_report(): void
    {
        $existing = Scammer::factory()->create([
            'name' => 'Juan Perez',
            'profile_picture_path' => 'reports/scammers/avatars/kept.jpg',
            'is_active' => false,
        ]);
        $existing->delete();

        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'scammers' => [
                ['name' => '  juan perez  '],
                [
                    'name' => 'Ana Lopez',
                    'contacts' => [
                        ['name' => 'Ana', 'platform' => 'cellphone', 'reference' => '+52 55 9999 8888'],
                    ],
                    'payment_methods' => [
                        ['type' => 'clabe', 'reference' => '032180000118359719'],
                    ],
                ],
                [
                    'name' => 'ana lopez',
                    'contacts' => [
                        ['name' => 'Ana Mail', 'platform' => 'email', 'reference' => 'ana@example.com'],
                    ],
                ],
                [
                    'name' => 'Solo Nombre',
                    'contacts' => [],
                    'payment_methods' => [],
                ],
            ],
        ]);

        $response->assertCreated();

        $ana = Scammer::query()->where('name', 'Ana Lopez')->first();
        $solo = Scammer::query()->where('name', 'Solo Nombre')->first();
        $organization = Organization::query()->first();
        $report = Report::query()->first();

        $this->assertNotNull($ana);
        $this->assertNotNull($solo);
        $this->assertNotNull($organization);
        $this->assertNotNull($report);
        $this->assertSame(3, Scammer::withTrashed()->count());
        $response->assertJsonPath('scammer_ids', [$existing->id, $ana->id, $solo->id]);

        $restored = $existing->fresh();
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertFalse($restored->is_active);
        $this->assertSame('Juan Perez', $restored->name);
        $this->assertSame('reports/scammers/avatars/kept.jpg', $restored->profile_picture_path);
        $this->assertSame(0, $restored->contacts()->count());
        $this->assertSame(0, $restored->paymentMethods()->count());

        $this->assertTrue($ana->is_active);
        $this->assertNull($ana->profile_picture_path);
        $this->assertTrue($solo->is_active);
        $this->assertSame(0, $solo->contacts()->count());
        $this->assertSame(0, $solo->paymentMethods()->count());

        $this->assertEqualsCanonicalizing(
            [$existing->id, $ana->id, $solo->id],
            $organization->scammers()->pluck('scammers.id')->all(),
        );
        $this->assertTrue($organization->reports()->whereKey($report->id)->exists());

        foreach ([$restored, $ana, $solo] as $scammer) {
            $this->assertFalse($scammer->reports()->whereKey($report->id)->exists());
        }

        $anaContacts = $ana->contacts()->orderBy('contacts.id')->get();
        $this->assertCount(2, $anaContacts);
        $this->assertSame('525599998888', $anaContacts[0]->reference);
        $this->assertSame('ana@example.com', $anaContacts[1]->reference);

        $anaPayments = $ana->paymentMethods()->get();
        $this->assertCount(1, $anaPayments);
        $this->assertSame('032180000118359719', $anaPayments[0]->reference);

        $organizationContactIds = $organization->contacts()->pluck('contacts.id')->all();
        $this->assertSame($organizationContactIds, $response->json('contact_ids'));
        $this->assertEmpty(array_intersect($organizationContactIds, $anaContacts->modelKeys()));

        $organizationPaymentIds = $organization->paymentMethods()->pluck('payment_methods.id')->all();
        $this->assertSame($organizationPaymentIds, $response->json('payment_method_ids'));
        $this->assertEmpty(array_intersect($organizationPaymentIds, $anaPayments->modelKeys()));
        $this->assertFalse($organization->contacts()->whereKey($anaContacts->modelKeys())->exists());
        $this->assertFalse($organization->paymentMethods()->whereKey($anaPayments->modelKeys())->exists());
    }

    public function test_rejects_an_incomplete_nested_scammer(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $payload = [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
        ];

        $missingFields = $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'scammers' => [
                [],
                ['name' => 'Ana Lopez', 'contacts' => [['name' => 'Ana']]],
            ],
        ]);

        $missingFields->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $details = $missingFields->json('error.details');
        $this->assertArrayHasKey('scammers.0.name', $details);
        $this->assertArrayHasKey('scammers.1.contacts.0.platform', $details);
        $this->assertArrayHasKey('scammers.1.contacts.0.reference', $details);
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, Scammer::query()->count());

        $invalidClabe = $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'scammers' => [
                [
                    'name' => 'Ana Lopez',
                    'payment_methods' => [
                        ['type' => 'clabe', 'reference' => '12345'],
                    ],
                ],
            ],
        ]);

        $invalidClabe->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('scammers.0.payment_methods.0.reference', $invalidClabe->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, Scammer::query()->count());
    }

    public function test_reuses_an_existing_organization_and_still_files_the_report(): void
    {
        $firstPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $first = $this->postJson('/api/public/reports/organizations', [
            'title' => 'First report',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $firstPicture,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
        ]);

        $first->assertCreated();

        $organization = Organization::query()->first();
        $this->assertNotNull($organization);
        $originalPicture = $organization->profile_picture_path;

        $secondPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'other.jpg', 'other');
        $second = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Second report',
            'email' => 'reporter@example.com',
            'description' => 'They asked for another transfer.',
            'profile_picture' => $secondPicture,
            'organization' => ['name' => 'tienda falsa'],
            'contacts' => [
                ['name' => 'Other seller', 'platform' => 'email', 'reference' => 'other@example.com'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '032180000118359719'],
            ],
            'products' => ['Banking'],
        ]);

        $second->assertCreated();
        $second->assertJsonPath('organization_id', $organization->id);

        $reused = $organization->fresh();
        $this->assertNotNull($reused);
        $this->assertSame(1, Organization::withTrashed()->count());
        $this->assertSame('Tienda Falsa', $reused->name);
        $this->assertTrue($reused->is_active);
        $this->assertSame($originalPicture, $reused->profile_picture_path);
        $this->assertSame('avatar', Storage::disk('public')->get($reused->profile_picture_path));

        $avatarFiles = collect(Storage::disk('public')->allFiles())
            ->filter(fn (string $path): bool => str_starts_with($path, 'reports/organizations/profiles/'))
            ->values();
        $this->assertSame([$originalPicture], $avatarFiles->all());

        $reports = Report::query()->orderBy('id')->get();
        $this->assertCount(2, $reports);
        $this->assertEqualsCanonicalizing(
            $reports->modelKeys(),
            $reused->reports()->pluck('reports.id')->all(),
        );
        $this->assertCount(2, $reused->contacts);
        $this->assertCount(2, $reused->paymentMethods);
    }

    public function test_stores_a_new_scammer_picture_and_keeps_an_existing_one(): void
    {
        Storage::disk('public')->put('reports/scammers/profiles/kept.jpg', 'kept');
        $existing = Scammer::factory()->create([
            'name' => 'Juan Perez',
            'profile_picture_path' => 'reports/scammers/profiles/kept.jpg',
        ]);

        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $newPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'new-scammer.jpg', 'new-scammer');
        $unusedPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'unused.jpg', 'unused');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'scammers' => [
                ['name' => 'Ana Lopez', 'profile_picture' => $newPicture],
                ['name' => 'juan perez', 'profile_picture' => $unusedPicture],
            ],
        ]);

        $response->assertCreated();

        $ana = Scammer::query()->where('name', 'Ana Lopez')->first();
        $reused = $existing->fresh();

        $this->assertNotNull($ana);
        $this->assertNotNull($reused);
        $this->assertStringStartsWith('reports/scammers/profiles/', $ana->profile_picture_path);
        $this->assertSame('new-scammer', Storage::disk('public')->get($ana->profile_picture_path));
        $this->assertSame('reports/scammers/profiles/kept.jpg', $reused->profile_picture_path);
        $this->assertSame('kept', Storage::disk('public')->get($reused->profile_picture_path));

        $profileFiles = collect(Storage::disk('public')->allFiles())
            ->filter(fn (string $path): bool => str_starts_with($path, 'reports/scammers/profiles/'))
            ->sort()
            ->values()
            ->all();
        $expected = collect(['reports/scammers/profiles/kept.jpg', $ana->profile_picture_path])->sort()->values()->all();
        $this->assertSame($expected, $profileFiles);
    }

    public function test_rejects_a_scammer_picture_outside_the_temporary_directory(): void
    {
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '5511112222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012345678901234567'],
            ],
            'products' => ['Crypto'],
            'scammers' => [
                [
                    'name' => 'Ana Lopez',
                    'profile_picture' => $this->storagePath('other/avatar.jpg'),
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('scammers.0.profile_picture', $response->json('error.details'));
        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, Scammer::query()->count());

        $files = Storage::disk('public')->allFiles();
        $expected = [
            TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg',
            'other/avatar.jpg',
        ];
        sort($files);
        sort($expected);
        $this->assertSame($expected, $files);
    }

    public function test_accepts_contacts_or_payment_methods_but_not_neither(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $payload = [
            'title' => 'Fake store took my money',
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'profile_picture' => $profile,
            'organization' => ['name' => 'Tienda Falsa'],
            'products' => ['Crypto'],
        ];

        $neither = $this->postJson('/api/public/reports/organizations', $payload);
        $neither->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $details = $neither->json('error.details');
        $this->assertArrayHasKey('contacts', $details);
        $this->assertArrayHasKey('payment_methods', $details);

        $empty = $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'contacts' => [],
            'payment_methods' => [],
        ]);
        $empty->assertStatus(422);
        $this->assertArrayHasKey('contacts', $empty->json('error.details'));
        $this->assertArrayHasKey('payment_methods', $empty->json('error.details'));
        $this->assertSame(0, Organization::query()->count());

        $contactsOnly = $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '5511112222'],
            ],
        ]);
        $contactsOnly->assertCreated();
        $contactsOnly->assertJsonPath('payment_method_ids', []);
        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(0, PaymentMethod::query()->count());
        $this->assertSame(1, Organization::query()->first()->contacts()->count());

        $paymentsOnly = $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'organization' => ['name' => 'Otra Tienda'],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012345678901234567'],
            ],
        ]);
        $paymentsOnly->assertCreated();
        $paymentsOnly->assertJsonPath('contact_ids', []);
        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(1, PaymentMethod::query()->count());
        $paymentOrganization = Organization::query()->where('name', 'Otra Tienda')->first();
        $this->assertNotNull($paymentOrganization);
        $this->assertSame(0, $paymentOrganization->contacts()->count());
        $this->assertSame(1, $paymentOrganization->paymentMethods()->count());
    }

    public function test_requires_every_field_except_proofs_and_profile_picture(): void
    {
        $response = $this->postJson('/api/public/reports/organizations', [
            'title' => 'A title',
            'organization' => ['name' => 'Shop'],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $details = $response->json('error.details');

        foreach (['email', 'description', 'contacts', 'payment_methods', 'products'] as $field) {
            $this->assertArrayHasKey($field, $details);
        }

        $this->assertArrayNotHasKey('proofs', $details);
        $this->assertArrayNotHasKey('scammers', $details);
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_reuses_an_existing_reporter_for_the_same_email(): void
    {
        $payload = [
            'email' => 'reporter@example.com',
            'description' => 'They never shipped the order.',
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '5511112222'],
            ],
            'products' => ['Crypto'],
        ];

        $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'title' => 'First report',
            'organization' => ['name' => 'Tienda Falsa'],
        ])->assertCreated();

        $this->postJson('/api/public/reports/organizations', [
            ...$payload,
            'title' => 'Second report',
            'organization' => ['name' => 'Otra Tienda'],
        ])->assertCreated();

        $this->assertSame(1, User::query()->count());
        $userId = User::query()->value('id');
        $this->assertSame(
            [$userId, $userId],
            Report::query()->orderBy('id')->pluck('user_id')->all(),
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
