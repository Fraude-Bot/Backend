<?php

namespace Tests\Feature;

use App\Infrastructure\Media\AntiNSFWAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('raw');
        Storage::fake('quarantine');
    }

    public function test_stores_jpg_and_png_profile_pictures_for_scammers_and_organizations(): void
    {
        foreach ([
            ['profile.jpg', self::jpegBytes()],
            ['profile.png', self::pngBytes()],
        ] as [$filename, $contents]) {
            $response = $this->post('/api/public/media/profiles', [
                'image' => UploadedFile::fake()->createWithContent($filename, $contents),
            ]);

            $response->assertCreated();

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $url = $response->json('path');
            $prefix = config('filesystems.disks.public.url').'/tmp/reports/profiles/';

            $this->assertStringStartsWith($prefix, $url);
            $this->assertStringEndsWith('.'.$extension, $url);

            $relative = substr($url, strlen(config('filesystems.disks.public.url').'/'));
            $this->assertTrue(Storage::disk('public')->exists($relative));

            $size = getimagesizefromstring(Storage::disk('public')->get($relative));
            $this->assertSame(640, $size[0]);
            $this->assertSame(640, $size[1]);
        }

        $this->assertSame([], Storage::disk('raw')->allFiles());
    }

    public function test_rejects_a_non_image(): void
    {
        $this->post('/api/public/media/profiles', [
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('raw')->allFiles());
    }

    public function test_rejects_a_profile_picture_when_moderation_fails(): void
    {
        $this->app->instance(AntiNSFWAnalyzer::class, new class extends AntiNSFWAnalyzer
        {
            public function passes(string $contents): bool
            {
                return false;
            }
        });

        $contents = self::jpegBytes();

        $this->post('/api/public/media/profiles', [
            'image' => UploadedFile::fake()->createWithContent('profile.jpg', $contents),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'image_rejected')
            ->assertJsonMissingPath('path');

        $quarantined = Storage::disk('quarantine')->allFiles();

        $this->assertCount(1, $quarantined);
        $this->assertStringStartsWith('tmp/reports/profiles/', $quarantined[0]);
        $this->assertSame($contents, Storage::disk('quarantine')->get($quarantined[0]));
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('raw')->allFiles());
    }

    public function test_stores_the_profile_picture_again_when_the_cached_file_is_missing(): void
    {
        $contents = self::jpegBytes();
        $upload = fn () => $this->post('/api/public/media/profiles', [
            'image' => UploadedFile::fake()->createWithContent('profile.jpg', $contents),
        ]);

        $first = $upload();
        $first->assertCreated();

        $prefix = config('filesystems.disks.public.url').'/';
        $firstRelative = substr($first->json('path'), strlen($prefix));
        Storage::disk('public')->delete($firstRelative);

        $second = $upload();
        $second->assertCreated();

        $secondRelative = substr($second->json('path'), strlen($prefix));
        $this->assertNotSame($firstRelative, $secondRelative);
        $this->assertTrue(Storage::disk('public')->exists($secondRelative));
        $this->assertSame([], Storage::disk('raw')->allFiles());
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
}
