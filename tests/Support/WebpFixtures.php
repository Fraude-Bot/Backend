<?php

namespace Tests\Support;

final class WebpFixtures
{
    /** 1×1 lossy WebP (valid image/webp without GD imagewebp). */
    public static function minimalBytes(): string
    {
        $decoded = base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=', true);

        if (! is_string($decoded) || $decoded === '') {
            throw new \RuntimeException('The WebP fixture could not be decoded.');
        }

        return $decoded;
    }
}
