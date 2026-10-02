<?php

namespace App\Infrastructure\Facebook;

interface FacebookServiceInterface
{
    public function getProfile(string $url): string;
}