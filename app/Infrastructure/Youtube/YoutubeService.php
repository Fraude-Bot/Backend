<?php
namespace App\Infrastructure\Youtube;

class YoutubeService implements YoutubeServiceInterface
{
    public function getChannel(string $url): string
    {
        $path = trim(parse_url($url, PHP_URL_PATH), '/');
        
        $handle = explode('/', $path)[0];

        if (strpos($handle, '@') === 0) {
            return $handle;
        }

        return "@$handle";
    }
}