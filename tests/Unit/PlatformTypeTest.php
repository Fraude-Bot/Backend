<?php

namespace Tests\Unit;

use App\Domain\Contact\Enums\PlatformType;
use PHPUnit\Framework\TestCase;

class PlatformTypeTest extends TestCase
{
    public function test_known_hosts_infer_their_platform(): void
    {
        $cases = [
            'https://www.instagram.com/user' => PlatformType::INSTAGRAM,
            'instagram.com/user' => PlatformType::INSTAGRAM,
            'HTTPS://M.FACEBOOK.COM/page' => PlatformType::FACEBOOK,
            'fb.com/page' => PlatformType::FACEBOOK,
            'https://fb.me/page' => PlatformType::FACEBOOK,
            'www.youtube.com/watch?v=1' => PlatformType::YOUTUBE,
            'youtu.be/abc' => PlatformType::YOUTUBE,
            'https://www.tiktok.com/@user' => PlatformType::TIKTOK,
            'https://api.whatsapp.com/send?phone=521234567890' => PlatformType::WHATSAPP,
            'wa.me/521234567890' => PlatformType::WHATSAPP,
            'https://t.me/user' => PlatformType::TELEGRAM,
            'telegram.me/user' => PlatformType::TELEGRAM,
            'https://web.telegram.org/a/' => PlatformType::TELEGRAM,
        ];

        foreach ($cases as $reference => $platform) {
            $this->assertSame($platform, PlatformType::fromReference($reference), $reference);
        }
    }

    public function test_phone_shaped_references_are_cellphones(): void
    {
        $this->assertSame(PlatformType::CELLPHONE, PlatformType::fromReference('+52 55 1234 5678'));
        $this->assertSame(PlatformType::CELLPHONE, PlatformType::fromReference('5551234567'));
        $this->assertSame(PlatformType::CELLPHONE, PlatformType::fromReference('(55) 1234-5678'));
    }

    public function test_anything_else_is_other(): void
    {
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference('person@example.com'));
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference('https://example.com/profile'));
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference('notinstagram.com/user'));
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference('1234567890123456'));
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference(''));
        $this->assertSame(PlatformType::OTHER, PlatformType::fromReference('+++'));
    }
}
