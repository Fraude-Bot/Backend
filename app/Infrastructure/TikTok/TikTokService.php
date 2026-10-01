<?php
namespace App\Infrastructure\TikTok;

class TikTokService implements TikTokServiceInterface
{
    public function getProfile(string $url): string
    {
       return trim(parse_url($url, PHP_URL_PATH), '/');
    }

}