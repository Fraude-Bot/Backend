<?php

namespace App\Application\Media;

interface ImageAnalyzerInterface
{
    public function passes(string $contents): bool;
}
