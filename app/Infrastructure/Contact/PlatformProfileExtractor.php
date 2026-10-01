<?php

namespace App\Infrastructure\Contact;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Contact\PlatformProfileExtractorInterface;
use App\Infrastructure\Facebook\FacebookServiceInterface;
use App\Infrastructure\Instagram\InstagramServiceInterface;
use App\Infrastructure\TikTok\TikTokServiceInterface;
use App\Infrastructure\Youtube\YoutubeServiceInterface;

class PlatformProfileExtractor implements PlatformProfileExtractorInterface
{
    public function __construct(
        private FacebookServiceInterface $facebook,
        private TikTokServiceInterface $tiktok,
        private InstagramServiceInterface $instagram,
        private YoutubeServiceInterface $youtube,
    ) {}

    public function extract(PlatformType $type, string $url): string
    {
        return match ($type) {
            PlatformType::FACEBOOK => $this->facebook->getProfile($url),
            PlatformType::TIKTOK => $this->tiktok->getProfile($url),
            PlatformType::INSTAGRAM => $this->instagram->getProfile($url),
            PlatformType::YOUTUBE => $this->youtube->getChannel($url),
            default => $url,
        };
    }
}
