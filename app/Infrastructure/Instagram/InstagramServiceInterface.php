<?php

namespace App\Infrastructure\Instagram;

interface InstagramServiceInterface
{
    public function getProfile(string $url): string;
}