<?php

namespace App\Application\Tasks;

use App\Application\Media\TemporaryImageStorageInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteTemporaryMediaTask
{
    public function __invoke(): void
    {
        $this->deleteTemporaryFiles();
        $this->deleteTemporaryCacheKeys();
    }

    private function deleteTemporaryFiles(): void
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles(TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY);

        echo json_encode($files);

        if ($files === []) {
            return;
        }

        if ($disk->delete($files) === false) {
            throw new RuntimeException('The temporary profile pictures could not be deleted.');
        }
    }

    private function deleteTemporaryCacheKeys(): void
    {
        foreach ($this->temporaryImageCacheKeys() as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @return list<string>
     */
    private function temporaryImageCacheKeys(): array
    {
        $store = Cache::getStore();

        $keys = match (true) {
            $store instanceof ArrayStore => array_keys($store->all(unserialize: false)),
            $store instanceof RedisStore => $this->redisCacheKeys($store),
            default => throw new RuntimeException('The cache store cannot list temporary image keys.'),
        };

        return array_values(array_filter(
            $keys,
            fn (string $key) => str_starts_with($key, TemporaryImageStorageInterface::CACHE_KEY_PREFIX),
        ));
    }

    /**
     * @return list<string>
     */
    private function redisCacheKeys(RedisStore $store): array
    {
        $connection = $store->connection();

        $connectionPrefix = match (true) {
            $connection instanceof PhpRedisConnection => $connection->_prefix(''),
            $connection instanceof PredisConnection => (string) ($connection->getOptions()->prefix ?: ''),
            default => '',
        };

        $prefix = $connectionPrefix.$store->getPrefix();

        $defaultCursorValue = $connection instanceof PhpRedisConnection && version_compare((string) phpversion('redis'), '6.1.0', '>=')
            ? null
            : '0';

        $keys = [];
        $cursor = $defaultCursorValue;

        do {
            $scanResult = $connection->scan($cursor, [
                'match' => $prefix.TemporaryImageStorageInterface::CACHE_KEY_PREFIX.'*',
                'count' => 1000,
            ]);

            if (! is_array($scanResult)) {
                break;
            }

            [$cursor, $found] = $scanResult;

            if (! is_array($found)) {
                break;
            }

            foreach ($found as $key) {
                $keys[] = substr((string) $key, strlen($prefix));
            }
        } while (((string) $cursor) !== (string) $defaultCursorValue);

        return $keys;
    }
}
