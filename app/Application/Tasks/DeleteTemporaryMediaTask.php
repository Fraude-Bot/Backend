<?php

namespace App\Application\Tasks;

use App\Application\Media\TemporaryImageStorageInterface;
use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Filesystem\Filesystem;
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
        foreach (['raw', 'public'] as $name) {
            $this->deleteTemporaryDirectories(Storage::disk($name));
        }
    }

    private function deleteTemporaryDirectories(Filesystem $disk): void
    {
        foreach ([
            TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
            TemporaryImageStorageInterface::PROOF_DIRECTORY,
        ] as $directory) {
            $files = $disk->allFiles($directory);

            if ($files === []) {
                continue;
            }

            if ($disk->delete($files) === false) {
                throw new RuntimeException('The temporary media could not be deleted.');
            }
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
        $match = TemporaryImageStorageInterface::CACHE_KEY_PREFIX.'*';

        if ($connection instanceof PhpRedisConnection) {
            $prefix = $connection->_prefix('').$store->getPrefix();
            $cursor = version_compare((string) phpversion('redis'), '6.1.0', '>=') ? null : '0';

            return $this->scanPrefixedKeys(
                $prefix,
                $cursor,
                fn (mixed $cursor): mixed => $connection->scan($cursor, [
                    'match' => $prefix.$match,
                    'count' => 1000,
                ]),
            );
        }

        if ($connection instanceof PredisConnection) {
            $prefix = $this->predisConnectionPrefix($connection).$store->getPrefix();

            return $this->scanPrefixedKeys(
                $prefix,
                '0',
                fn (mixed $cursor): mixed => $connection->__call('scan', [$cursor, [
                    'match' => $prefix.$match,
                    'count' => 1000,
                ]]),
            );
        }

        throw new RuntimeException('The cache store cannot list temporary image keys.');
    }

    private function predisConnectionPrefix(PredisConnection $connection): string
    {
        $client = $connection->client();

        if (! is_object($client) || ! method_exists($client, 'getOptions')) {
            return '';
        }

        $options = $client->getOptions();

        if (! is_object($options) || ! isset($options->prefix)) {
            return '';
        }

        return (string) $options->prefix;
    }

    /**
     * @param  Closure(mixed): mixed  $scan
     * @return list<string>
     */
    private function scanPrefixedKeys(string $prefix, mixed $initialCursor, Closure $scan): array
    {
        $keys = [];
        $cursor = $initialCursor;

        do {
            $scanResult = $scan($cursor);

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
        } while (((string) $cursor) !== (string) $initialCursor);

        return $keys;
    }
}
