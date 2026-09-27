<?php

namespace Tests\Feature;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Application\Tasks\DeleteTemporaryMediaTask;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteTemporaryMediaTaskTest extends TestCase
{
    public function test_deletes_only_temporary_profile_pictures_and_their_cache_keys(): void
    {
        Storage::fake('public');

        $directory = TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY;
        $proofs = TemporaryImageStorageInterface::PROOF_DIRECTORY;
        Storage::disk('public')->put($directory.'/one.jpg', 'one');
        Storage::disk('public')->put($directory.'/two.png', 'two');
        Storage::disk('public')->put($proofs.'/proof.jpg', 'proof');
        Storage::disk('public')->put('tmp/pictures/reports/other.jpg', 'keep');

        $prefix = TemporaryImageStorageInterface::CACHE_KEY_PREFIX;
        Cache::forever($prefix.'one', 'http://localhost/one.jpg');
        Cache::forever($prefix.'two', 'http://localhost/two.jpg');
        Cache::forever('unrelated-cache-key', 'keep');

        app(DeleteTemporaryMediaTask::class)();

        Storage::disk('public')->assertMissing($directory.'/one.jpg');
        Storage::disk('public')->assertMissing($directory.'/two.png');
        Storage::disk('public')->assertMissing($proofs.'/proof.jpg');
        Storage::disk('public')->assertExists('tmp/pictures/reports/other.jpg');
        $this->assertNull(Cache::get($prefix.'one'));
        $this->assertNull(Cache::get($prefix.'two'));
        $this->assertSame('keep', Cache::get('unrelated-cache-key'));
    }

    public function test_succeeds_when_there_is_nothing_to_delete(): void
    {
        Storage::fake('public');

        app(DeleteTemporaryMediaTask::class)();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_is_scheduled_every_sunday(): void
    {
        Artisan::call('schedule:list', ['--json' => true]);

        $events = json_decode(Artisan::output(), true);

        $this->assertContains([
            'expression' => '0 0 * * 0',
            'command' => DeleteTemporaryMediaTask::class,
        ], array_map(fn (array $event) => [
            'expression' => $event['expression'],
            'command' => $event['command'],
        ], $events));
    }
}
