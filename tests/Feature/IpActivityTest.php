<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Models\IpActivity;
use App\Models\Scammer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IpActivityTest extends TestCase
{
    private const string IP = '203.0.113.10';

    public function test_updating_a_scammer_profile_logs_the_ip_and_target(): void
    {
        Storage::fake('public');
        $scammer = Scammer::factory()->create();
        $picture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');

        $this->fromIp()->patchJson("/api/public/scammers/{$scammer->id}/profiles", [
            'profile_picture' => $picture,
        ])->assertOk();

        $this->assertSame(1, IpActivity::query()->count());
        $this->assertDatabaseHas('ip_activities', [
            'action' => 'scammer.profile.updated',
            'subject_type' => 'scammer',
            'subject_id' => $scammer->id,
            'ip_address' => self::IP,
            'actor_id' => null,
        ]);
    }

    public function test_creating_a_scammer_report_logs_the_ip_and_report(): void
    {
        $response = $this->fromIp()->postJson('/api/public/reports/scammers', [
            'title' => 'Prize call',
            'description' => 'They asked for a transfer.',
            'email' => 'reporter@example.com',
            'scammer' => ['name' => 'Juan Perez'],
            'contacts' => [
                ['reference' => 'seller@example.com'],
            ],
            'products' => ['Crypto'],
        ]);

        $response->assertCreated();
        $reportId = $response->json('id');
        $this->assertIsInt($reportId);

        $this->assertSame(1, IpActivity::query()->count());
        $this->assertDatabaseHas('ip_activities', [
            'action' => 'report.created',
            'subject_type' => 'report',
            'subject_id' => $reportId,
            'ip_address' => self::IP,
            'actor_id' => null,
        ]);
    }

    public function test_failed_profile_and_report_writes_are_not_logged(): void
    {
        Storage::fake('public');
        $picture = $this->publishTemporary(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 'avatar.jpg', 'avatar');
        $scammer = Scammer::factory()->create();

        $this->fromIp()->patchJson('/api/public/scammers/999999/profiles', [
            'profile_picture' => $picture,
        ])->assertNotFound();

        $this->fromIp()->patchJson("/api/public/scammers/{$scammer->id}/profiles", [])
            ->assertUnprocessable();

        $this->fromIp()->postJson('/api/public/reports/scammers', [])
            ->assertUnprocessable();

        $this->assertSame(0, IpActivity::query()->count());
    }

    public function test_admin_contact_create_logs_the_actor(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['admin:write']);
        $scammer = Scammer::factory()->create();

        $this->fromIp()->postJson("/api/admin/scammers/{$scammer->id}/contacts", [
            'reference' => 'desk@example.com',
        ])->assertCreated();

        $this->assertSame(1, IpActivity::query()->count());
        $this->assertDatabaseHas('ip_activities', [
            'action' => 'scammer.contact.created',
            'subject_type' => 'scammer',
            'subject_id' => $scammer->id,
            'ip_address' => self::IP,
            'actor_id' => $user->id,
        ]);
    }

    private function fromIp(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
    }

    private function publishTemporary(string $directory, string $filename, string $contents): string
    {
        $relative = $directory.'/'.$filename;
        Storage::disk('public')->put($relative, $contents);

        $urlPath = parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH);

        return rtrim(is_string($urlPath) ? $urlPath : '', '/').'/'.$relative;
    }
}
