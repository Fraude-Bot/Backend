<?php

namespace App\Domain\Contact\Enums;

enum PlatformType: int
{
    case WHATSAPP = 1;
    case FACEBOOK = 2;
    case YOUTUBE = 3;
    case TIKTOK = 4;
    case EMAIL = 5;
    case CELLPHONE = 6;
    case TELEGRAM = 7;
    case INSTAGRAM = 8;
    case URL = 9;
    case OTHER = 10;

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }

    public static function tryFromInput(mixed $value): ?self
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::tryFrom((int) $value);
        }

        return is_string($value) ? self::tryFromName($value) : null;
    }

    public static function fromReference(string $reference): self
    {
        $reference = trim($reference);
        $platform = self::platformForHost($reference);

        if ($platform instanceof self) {
            return $platform;
        }

        if (self::isPhoneShaped($reference)) {
            return self::CELLPHONE;
        }

        return self::OTHER;
    }

    private static function platformForHost(string $reference): ?self
    {
        $host = self::hostFrom($reference);

        if ($host === null) {
            return null;
        }

        foreach (self::hosts() as $domain => $platform) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $platform;
            }
        }

        return null;
    }

    /**
     * @return array<string, self>
     */
    private static function hosts(): array
    {
        return [
            'instagram.com' => self::INSTAGRAM,
            'facebook.com' => self::FACEBOOK,
            'fb.com' => self::FACEBOOK,
            'fb.me' => self::FACEBOOK,
            'youtube.com' => self::YOUTUBE,
            'youtu.be' => self::YOUTUBE,
            'tiktok.com' => self::TIKTOK,
            'whatsapp.com' => self::WHATSAPP,
            'wa.me' => self::WHATSAPP,
            't.me' => self::TELEGRAM,
            'telegram.me' => self::TELEGRAM,
            'telegram.org' => self::TELEGRAM,
        ];
    }

    private static function hostFrom(string $reference): ?string
    {
        if (! preg_match('/[a-z]/i', $reference)) {
            return null;
        }

        $candidate = $reference;

        if (str_starts_with($candidate, '//')) {
            $candidate = 'https:'.$candidate;
        } elseif (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        return strtolower($host);
    }

    private static function isPhoneShaped(string $reference): bool
    {
        if ($reference === '' || ! preg_match('/^[0-9+\-()\s.]+$/', $reference)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $reference) ?? '';
        $length = strlen($digits);

        return $length >= 1 && $length <= 15;
    }
}
