<?php

namespace Tests\Unit;

use App\Infrastructure\Media\AntiNSFWAnalyzer;
use PHPUnit\Framework\TestCase;

class AntiNSFWAnalyzerTest extends TestCase
{
    public function test_accepts_every_image(): void
    {
        $this->assertTrue((new AntiNSFWAnalyzer)->passes('image-bytes'));
    }
}
