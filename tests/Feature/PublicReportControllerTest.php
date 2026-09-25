<?php

namespace Tests\Feature;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Resources\Public\ReportCardResource;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Report;
use App\Models\Scammer;
use App\Repositories\Organization\OrganizationCardRepositoryInterface;
use App\Repositories\Scammer\ScammerCardRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicReportControllerTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_stores_jpg_and_png_profile_pictures_for_scammers_and_organizations(): void
    {
        foreach ([
            ['scammer', 'profile.jpg', self::jpegBytes()],
            ['organization', 'profile.png', self::pngBytes()],
        ] as [$subject, $filename, $contents]) {
            $response = $this->post("/api/public/reports/{$subject}/picture/profile", [
                'image' => UploadedFile::fake()->createWithContent($filename, $contents),
            ]);

            $response->assertCreated();

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $url = $response->json('path');
            $prefix = config('filesystems.disks.public.url')."/tmp/reports/{$subject}/picture/profile/";

            $this->assertStringStartsWith($prefix, $url);
            $this->assertStringEndsWith('.'.$extension, $url);

            $relative = substr($url, strlen(config('filesystems.disks.public.url').'/'));
            $this->assertTrue(Storage::disk('public')->exists($relative));

            $size = getimagesizefromstring(Storage::disk('public')->get($relative));
            $this->assertSame(640, $size[0]);
            $this->assertSame(640, $size[1]);
        } 
    }

    public function test_rejects_a_non_image(): void
    {
        $this->post('/api/public/reports/scammer/picture/profile', [
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private static function jpegBytes(): string
    {
        return self::imageBytes(1200, 800, 'jpeg');
    }

    private static function pngBytes(): string
    {
        return self::imageBytes(400, 900, 'png');
    }

    private static function imageBytes(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 180, 40, 40));

        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
    
    public function test_report_search_by_clabe(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            '012345678901234567',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_card_number(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            '4152313732125521',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_account_number(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            '0123456789',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_email(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            'test@example.com',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_phone(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            '525512345678',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_wallet(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_url(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            'https://example.com',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_domain(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            'example.com',
            $this->expectedBoth($fixtures),
        );
    }

    public function test_report_search_by_empty_query(): void
    {
        $this->assertReportSearch('', []);
    }

    public function test_report_search_by_null_query(): void
    {
        $this->assertReportSearch(null, []);
    }

    public function test_report_search_by_general_query(): void
    {
        $fixtures = $this->seedDefaultSearchFixtures();

        $this->assertReportSearch(
            'John Doe',
            [$fixtures['scammer']],
        );
    }

    public function test_report_search_truncates_scammer_organizations_on_the_card(): void
    {
        $scammer = Scammer::factory()->create(['name' => 'Org Overflow Scammer']);
        $organizationIds = collect(range(1, 8))->map(
            fn (int $i) => Organization::factory()->create([
                'name' => sprintf('Alpha Org %d', $i),
            ])->id,
        );
        $scammer->organizations()->attach($organizationIds);

        $this->getJson('/api/public/reports?'.http_build_query([
            'q' => 'Org Overflow Scammer',
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.organizations', [
                'Alpha Org 1',
                'Alpha Org 2',
                'Alpha Org 3',
                'Alpha Org 4',
                'Alpha Org 5',
                '...',
            ]);
    }

    public function test_report_search_truncates_products_on_scammer_and_organization_cards(): void
    {
        $organization = Organization::factory()->create(['name' => 'Product Overflow Org']);
        $scammer = Scammer::factory()->create([
            'name' => 'Product Overflow Scammer',
            'updated_at' => now()->subDay(),
        ]);

        $report = Report::factory()->create(['is_active' => true]);
        $organization->reports()->attach($report);
        $scammer->reports()->attach($report);

        collect(range(1, 8))->each(function (int $i) use ($report): void {
            $product = Product::factory()->create([
                'name' => sprintf('Beta Product %d', $i),
            ]);
            $report->products()->attach($product);
        });

        $expectedProducts = [
            'Beta Product 1',
            'Beta Product 2',
            'Beta Product 3',
            'Beta Product 4',
            'Beta Product 5',
            '...',
        ];

        $this->getJson('/api/public/reports?'.http_build_query([
            'q' => 'Product Overflow Org',
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.products', $expectedProducts);

        $this->getJson('/api/public/reports?'.http_build_query([
            'q' => 'Product Overflow Scammer',
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.products', $expectedProducts);
    }

    public function test_report_search_does_not_truncate_five_preview_items(): void
    {
        $scammer = Scammer::factory()->create(['name' => 'Five Item Scammer']);
        $organizationIds = collect(range(1, 5))->map(
            fn (int $i) => Organization::factory()->create([
                'name' => sprintf('Gamma Org %d', $i),
            ])->id,
        );
        $scammer->organizations()->attach($organizationIds);

        $report = Report::factory()->create(['is_active' => true]);
        $scammer->reports()->attach($report);
        collect(range(1, 5))->each(function (int $i) use ($report): void {
            $product = Product::factory()->create([
                'name' => sprintf('Gamma Product %d', $i),
            ]);
            $report->products()->attach($product);
        });

        $this->getJson('/api/public/reports?'.http_build_query([
            'q' => 'Five Item Scammer',
            'p' => 1,
            'c' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.organizations', [
                'Gamma Org 1',
                'Gamma Org 2',
                'Gamma Org 3',
                'Gamma Org 4',
                'Gamma Org 5',
            ])
            ->assertJsonPath('data.0.products', [
                'Gamma Product 1',
                'Gamma Product 2',
                'Gamma Product 3',
                'Gamma Product 4',
                'Gamma Product 5',
            ]);
    }

    /**
     * @param  array<int, Scammer|Organization>  $expectedModels
     */
    private function assertReportSearch(
        ?string $query,
        array $expectedModels,
        int $page = 1,
        int $count = 10,
    ): void {
        $params = ['p' => $page, 'c' => $count];
        if ($query !== null) {
            $params['q'] = $query;
        }

        $response = $this->getJson('/api/public/reports?'.http_build_query($params));

        $response->assertOk();
        $response->assertExactJson([
            'data' => ReportCardResource::collection(collect($expectedModels))->resolve(),
            'total' => count($expectedModels),
            'page' => $page,
            'count' => $count,
        ]);
    }

    /**
     * @return array{scammer: Scammer, organization: Organization}
     */
    private function seedDefaultSearchFixtures(): array
    {
        $organization = Organization::factory()->create([
            'name' => 'Ecohuertas',
            'country' => 'MX',
            'updated_at' => now()->subDay(),
        ]);

        $scammer = Scammer::factory()->create([
            'name' => 'John Doe',
            'country' => 'MX',
        ]);

        $scammer->organizations()->attach($organization);

        $this->attachReportsWithProducts($scammer, 2);
        $this->attachReportsWithProducts($organization, 5);
        $this->attachSharedPaymentMethods($scammer, $organization);
        $this->attachSharedContacts($scammer, $organization);

        return [
            'scammer' => $this->loadSearchCard($scammer),
            'organization' => $this->loadSearchCard($organization),
        ];
    }

    /**
     * @param  array{scammer: Scammer, organization: Organization}  $fixtures
     * @return array<int, Scammer|Organization>
     */
    private function expectedBoth(array $fixtures): array
    {
        return $this->sortSearchCards([
            $fixtures['scammer'],
            $fixtures['organization'],
        ]);
    }

    private function attachReportsWithProducts(Scammer|Organization $entity, int $reportCount): void
    {
        $products = Product::factory()->count(3)->create();
        $reports = Report::factory()->count($reportCount)->create();

        foreach ($reports as $report) {
            $entity->reports()->attach($report);
            $report->products()->attach($products);
        }
    }

    private function attachSharedPaymentMethods(Scammer $scammer, Organization $organization): void
    {
        $references = [
            [PaymentMethodType::CLABE, '012345678901234567'],
            [PaymentMethodType::CARD_NUMBER, '4152313732125521'],
            [PaymentMethodType::ACCOUNT_NUMBER, '0123456789'],
            [PaymentMethodType::WALLET, '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'],
        ];

        foreach ($references as [$type, $reference]) {
            $paymentMethod = PaymentMethod::factory()->create([
                'type' => $type,
                'reference' => $reference,
            ]);

            $scammer->paymentMethods()->attach($paymentMethod);
            $organization->paymentMethods()->attach($paymentMethod);
        }
    }

    private function attachSharedContacts(Scammer $scammer, Organization $organization): void
    {
        $contacts = [
            [PlatformType::EMAIL, 'test@example.com'],
            [PlatformType::CELLPHONE, '525512345678'],
            [PlatformType::URL, 'https://example.com'],
            [PlatformType::URL, 'example.com'],
        ];

        foreach ($contacts as [$platform, $reference]) {
            $contact = Contact::factory()->create([
                'platform' => $platform,
                'reference' => $reference,
            ]);

            $scammer->contacts()->attach($contact);
            $organization->contacts()->attach($contact);
        }
    }

    private function loadSearchCard(Scammer|Organization $model): Scammer|Organization
    {
        if ($model instanceof Scammer) {
            /** @var Scammer $scammer */
            $scammer = app(ScammerCardRepositoryInterface::class)->hydrate([$model->id])->get($model->id);

            return $scammer;
        }

        /** @var Organization $organization */
        $organization = app(OrganizationCardRepositoryInterface::class)->hydrate([$model->id])->get($model->id);

        return $organization;
    }

    /**
     * @param  array<int, Scammer|Organization>  $models
     * @return array<int, Scammer|Organization>
     */
    private function sortSearchCards(array $models): array
    {
        return Collection::make($models)
            ->sort(function (Scammer|Organization $left, Scammer|Organization $right): int {
                $updatedAtCompare = $right->updated_at <=> $left->updated_at;

                return $updatedAtCompare !== 0 ? $updatedAtCompare : $right->id <=> $left->id;
            })
            ->values()
            ->all();
    }
}
