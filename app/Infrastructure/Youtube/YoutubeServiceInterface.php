<?php

namespace App\Infrastructure\Youtube;

interface YoutubeServiceInterface
{
    public function getChannel(string $url): string;
}