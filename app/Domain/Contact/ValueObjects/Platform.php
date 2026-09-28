<?php

namespace App\Domain\Contact\ValueObjects;

use App\Domain\Contact\Enums\PlatformType;
use App\Infrastructure\Facebook\FacebookServiceInterface;
use App\Infrastructure\Instagram\InstagramServiceInterface;
use App\Infrastructure\TikTok\TikTokServiceInterface;
use App\Infrastructure\Youtube\YoutubeServiceInterface;

class Platform
{
    public function __construct(private PlatformType $type) {}

    public function extractURL(string $url): string
    {
        return match ($this->type) {
            PlatformType::FACEBOOK   => app(FacebookServiceInterface::class)->getProfile($url),
            PlatformType::TIKTOK     => app(TikTokServiceInterface::class)->getProfile($url),
            PlatformType::INSTAGRAM  => app(InstagramServiceInterface::class)->getProfile($url),
            PlatformType::YOUTUBE    => app(YoutubeServiceInterface::class)->getChannel($url),
            PlatformType::WHATSAPP   => $url,
            PlatformType::EMAIL      => $url,
            PlatformType::CELLPHONE  => $url,
            PlatformType::TELEGRAM   => $url,
            PlatformType::URL        => $url,
            PlatformType::OTHER      => $url,
        };
    }
}
