<?php

namespace App\Infrastructure\Media;

use App\Application\Media\ImageAnalyzerInterface;

class AntiNSFWAnalyzer implements ImageAnalyzerInterface
{
    public function passes(string $contents): bool
    {
        return true;
    }
}
