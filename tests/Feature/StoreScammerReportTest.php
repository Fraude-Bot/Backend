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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreScammerReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_creates_a_scammer_report_with_contacts_payment_methods_and_images(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $proofs = [
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'one.jpg', 'one'),
            $this->publishTemporary(TemporaryImageStorageInterface::PROOF_DIRECTORY, 'two.png', 'two'),
        ];

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'proofs' => $proofs,
            'scammer' => [
                'name' => 'Juan Perez',
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
        $response->assertJsonPath('organization_ids', []);
        $this->assertSame(0, Organization::query()->count());

        $product = Product::query()->where('name', 'Crypto')->first();
        $this->assertNotNull($product);
        $response->assertJsonPath('product_ids', [$product->id]);

        $scammer = Scammer::query()->first();
        $report = Report::query()->first();

        $this->assertNotNull($scammer);
        $this->assertNotNull($report);
        $this->assertSame($report->id, $response->json('id'));
        $this->assertSame($scammer->id, $response->json('scammer_id'));
        $this->assertNull($report->user_id);
        $this->assertTrue($report->is_active);
        $this->assertSame('Called me about a prize', $report->title);
        $this->assertSame('They asked for a transfer to release the prize.', $report->description);
        $this->assertTrue($scammer->is_active);
        $this->assertSame('Juan Perez', $scammer->name);
        $this->assertTrue($scammer->reports()->whereKey($report->id)->exists());
        $this->assertTrue($report->products()->whereKey($product->id)->exists());

        $this->assertStringStartsWith('reports/scammers/profiles/', $scammer->profile_picture_path);
        $this->assertSame('avatar', Storage::disk('public')->get($scammer->profile_picture_path));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'));

        $storedProofs = ReportProof::query()->where('report_id', $report->id)->orderBy('id')->get();
        $this->assertCount(2, $storedProofs);
        $this->assertSame($storedProofs->pluck('id')->all(), $response->json('report_proof_ids'));
        $this->assertSame(['one', 'two'], [
            Storage::disk('public')->get($storedProofs[0]->path),
            Storage::disk('public')->get($storedProofs[1]->path),
        ]);
        $this->assertStringStartsWith('reports/scammers/proofs/', $storedProofs[0]->path);
        $this->assertStringStartsWith('reports/scammers/proofs/', $storedProofs[1]->path);
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/one.jpg'));
        $this->assertTrue(Storage::disk('public')->exists(TemporaryImageStorageInterface::PROOF_DIRECTORY.'/two.png'));

        $contacts = Contact::query()->orderBy('id')->get();
        $this->assertCount(2, $contacts);
        $this->assertSame($contacts->pluck('id')->all(), $response->json('contact_ids'));
        $this->assertSame(PlatformType::CELLPHONE, $contacts[0]->platform);
        $this->assertSame('525511112222', $contacts[0]->reference);
        $this->assertSame('seller@example.com', $contacts[1]->reference);
        $this->assertEqualsCanonicalizing($contacts->modelKeys(), $scammer->contacts()->pluck('contacts.id')->all());

        $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
        $this->assertCount(2, $paymentMethods);
        $this->assertSame($paymentMethods->pluck('id')->all(), $response->json('payment_method_ids'));
        $this->assertSame('012345678901234567', $paymentMethods[0]->reference);
        $this->assertSame('4111111111111111', $paymentMethods[1]->reference);
        $this->assertEqualsCanonicalizing($paymentMethods->modelKeys(), $scammer->paymentMethods()->pluck('payment_methods.id')->all());
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

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me again',
            'description' => 'They called again.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Another person'],
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
        $this->assertNull(Report::query()->first()->user_id);

        $scammer = Scammer::query()->first();
        $this->assertStringStartsWith('reports/scammers/profiles/', $scammer->profile_picture_path);
        $this->assertTrue($scammer->contacts()->whereKey($contact->id)->exists());
        $this->assertTrue($scammer->paymentMethods()->whereKey($paymentMethod->id)->exists());
    }

    public function test_creates_products_reuses_an_existing_name_and_links_each_name_once(): void
    {
        $existing = Product::query()->create(['name' => 'Crypto']);
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Sold me crypto',
            'description' => 'They sold a token.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
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
        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'A title',
            'description' => 'A description',
            'profile_picture' => '/storage/tmp/reports/profile/avatar.jpg',
            'scammer' => ['name' => 'Person'],
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
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Report::query()->count());
    }

    public function test_rejects_a_profile_picture_outside_the_temporary_directory(): void
    {
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'A title',
            'description' => 'A description',
            'scammer' => ['name' => 'Person'],
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
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(['other/avatar.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_rejects_an_invalid_clabe(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'A title',
            'description' => 'A description',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Person'],
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
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, PaymentMethod::query()->count());
        $this->assertSame(
            [TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'],
            Storage::disk('public')->allFiles(),
        );
    }

    public function test_removes_copied_images_when_a_later_proof_is_rejected(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'A title',
            'description' => 'A description',
            'scammer' => ['name' => 'Person'],
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
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(
            [TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg'],
            Storage::disk('public')->allFiles(),
        );
    }

    public function test_accepts_an_empty_organization_list(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'organizations' => [],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('organization_ids', []);
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_links_optional_organizations_by_name_without_filing_a_report_against_them(): void
    {
        $existing = Organization::factory()->create([
            'name' => 'Tienda Falsa',
            'profile_picture_path' => 'reports/organizations/avatars/kept.jpg',
            'is_active' => false,
        ]);
        $existing->delete();

        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'organizations' => [
                ['name' => '  tienda falsa  '],
                [
                    'name' => 'Banco Fantasma',
                    'contacts' => [
                        ['name' => 'Caja', 'platform' => 'cellphone', 'reference' => '+52 55 9999 8888'],
                    ],
                    'payment_methods' => [
                        ['type' => 'clabe', 'reference' => '032180000118359719'],
                    ],
                ],
                [
                    'name' => 'banco fantasma',
                    'contacts' => [
                        ['name' => 'Caja Mail', 'platform' => 'email', 'reference' => 'caja@example.com'],
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

        $bank = Organization::query()->where('name', 'Banco Fantasma')->first();
        $solo = Organization::query()->where('name', 'Solo Nombre')->first();
        $scammer = Scammer::query()->first();
        $report = Report::query()->first();

        $this->assertNotNull($bank);
        $this->assertNotNull($solo);
        $this->assertNotNull($scammer);
        $this->assertNotNull($report);
        $this->assertSame(3, Organization::withTrashed()->count());
        $this->assertSame(1, Report::query()->count());
        $response->assertJsonPath('organization_ids', [$existing->id, $bank->id, $solo->id]);

        $restored = $existing->fresh();
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertFalse($restored->is_active);
        $this->assertSame('Tienda Falsa', $restored->name);
        $this->assertSame('reports/organizations/avatars/kept.jpg', $restored->profile_picture_path);
        $this->assertSame(0, $restored->contacts()->count());
        $this->assertSame(0, $restored->paymentMethods()->count());
        $this->assertSame(0, $restored->reports()->count());

        $this->assertTrue($bank->is_active);
        $this->assertNull($bank->profile_picture_path);
        $this->assertTrue($solo->is_active);
        $this->assertSame(0, $solo->contacts()->count());
        $this->assertSame(0, $solo->paymentMethods()->count());

        $this->assertEqualsCanonicalizing(
            [$existing->id, $bank->id, $solo->id],
            $scammer->organizations()->pluck('organizations.id')->all(),
        );
        $this->assertTrue($scammer->reports()->whereKey($report->id)->exists());

        foreach ([$restored, $bank, $solo] as $organization) {
            $this->assertFalse($organization->reports()->whereKey($report->id)->exists());
        }

        $bankContacts = $bank->contacts()->orderBy('contacts.id')->get();
        $this->assertCount(2, $bankContacts);
        $this->assertSame('525599998888', $bankContacts[0]->reference);
        $this->assertSame('caja@example.com', $bankContacts[1]->reference);

        $bankPayments = $bank->paymentMethods()->get();
        $this->assertCount(1, $bankPayments);
        $this->assertSame('032180000118359719', $bankPayments[0]->reference);

        $scammerContactIds = $scammer->contacts()->pluck('contacts.id')->all();
        $this->assertSame($scammerContactIds, $response->json('contact_ids'));
        $this->assertEmpty(array_intersect($scammerContactIds, $bankContacts->modelKeys()));

        $scammerPaymentIds = $scammer->paymentMethods()->pluck('payment_methods.id')->all();
        $this->assertSame($scammerPaymentIds, $response->json('payment_method_ids'));
        $this->assertEmpty(array_intersect($scammerPaymentIds, $bankPayments->modelKeys()));
        $this->assertFalse($scammer->contacts()->whereKey($bankContacts->modelKeys())->exists());
        $this->assertFalse($scammer->paymentMethods()->whereKey($bankPayments->modelKeys())->exists());
    }

    public function test_rejects_an_incomplete_nested_organization(): void
    {
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $payload = [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['name' => 'Seller', 'platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
        ];

        $missingFields = $this->postJson('/api/public/reports/scammers', [
            ...$payload,
            'organizations' => [
                [],
                ['name' => 'Tienda Falsa', 'contacts' => [['name' => 'Caja']]],
            ],
        ]);

        $missingFields->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $details = $missingFields->json('error.details');
        $this->assertArrayHasKey('organizations.0.name', $details);
        $this->assertArrayHasKey('organizations.1.contacts.0.platform', $details);
        $this->assertArrayHasKey('organizations.1.contacts.0.reference', $details);
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Organization::query()->count());

        $invalidClabe = $this->postJson('/api/public/reports/scammers', [
            ...$payload,
            'organizations' => [
                [
                    'name' => 'Tienda Falsa',
                    'payment_methods' => [
                        ['type' => 'clabe', 'reference' => '12345'],
                    ],
                ],
            ],
        ]);

        $invalidClabe->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('organizations.0.payment_methods.0.reference', $invalidClabe->json('error.details'));
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_stores_a_new_organization_picture_and_keeps_an_existing_one(): void
    {
        Storage::disk('public')->put('reports/organizations/profiles/kept.jpg', 'kept');
        $existing = Organization::factory()->create([
            'name' => 'Tienda Falsa',
            'profile_picture_path' => 'reports/organizations/profiles/kept.jpg',
        ]);

        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $newPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'new-org.jpg', 'new-org');
        $unusedPicture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'unused.jpg', 'unused');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'organizations' => [
                ['name' => 'Banco Fantasma', 'profile_picture_path' => $newPicture],
                ['name' => 'tienda falsa', 'profile_picture_path' => $unusedPicture],
            ],
        ]);

        $response->assertCreated();

        $bank = Organization::query()->where('name', 'Banco Fantasma')->first();
        $reused = $existing->fresh();

        $this->assertNotNull($bank);
        $this->assertNotNull($reused);
        $this->assertStringStartsWith('reports/organizations/profiles/', $bank->profile_picture_path);
        $this->assertSame('new-org', Storage::disk('public')->get($bank->profile_picture_path));
        $this->assertSame('reports/organizations/profiles/kept.jpg', $reused->profile_picture_path);
        $this->assertSame('kept', Storage::disk('public')->get($reused->profile_picture_path));

        $profileFiles = collect(Storage::disk('public')->allFiles())
            ->filter(fn (string $path): bool => str_starts_with($path, 'reports/organizations/profiles/'))
            ->sort()
            ->values()
            ->all();
        $expected = collect(['reports/organizations/profiles/kept.jpg', $bank->profile_picture_path])->sort()->values()->all();
        $this->assertSame($expected, $profileFiles);
    }

    public function test_rejects_an_organization_picture_outside_the_temporary_directory(): void
    {
        Storage::disk('public')->put('other/avatar.jpg', 'avatar');
        $profile = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'Called me about a prize',
            'description' => 'They asked for a transfer to release the prize.',
            'profile_picture' => $profile,
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['platform' => 'cellphone', 'reference' => '+52 55 1111 2222'],
            ],
            'payment_methods' => [
                ['type' => 'clabe', 'reference' => '012 345 678 901 234 567'],
            ],
            'products' => ['Crypto'],
            'organizations' => [
                [
                    'name' => 'Banco Fantasma',
                    'profile_picture_path' => $this->storagePath('other/avatar.jpg'),
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('organizations.0.profile_picture_path', $response->json('error.details'));
        $this->assertSame(0, Scammer::query()->count());
        $this->assertSame(0, Organization::query()->count());

        $files = Storage::disk('public')->allFiles();
        $expected = [
            TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY.'/avatar.jpg',
            'other/avatar.jpg',
        ];
        sort($files);
        sort($expected);
        $this->assertSame($expected, $files);
    }

    public function test_requires_every_field_except_proofs(): void
    {
        $response = $this->postJson('/api/public/reports/scammers', [
            'title' => 'A title',
            'scammer' => ['name' => 'Person'],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $details = $response->json('error.details');

        foreach (['description', 'profile_picture', 'contacts', 'payment_methods', 'products'] as $field) {
            $this->assertArrayHasKey($field, $details);
        }

        $this->assertArrayNotHasKey('proofs', $details);
        $this->assertArrayNotHasKey('organizations', $details);
        $this->assertSame(0, Scammer::query()->count());
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
