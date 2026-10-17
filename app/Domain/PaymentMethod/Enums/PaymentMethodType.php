<?php

namespace App\Domain\PaymentMethod\Enums;

use function is_int;
use function is_string;
use function ctype_digit;
use function preg_match;
use function preg_replace;
use function strlen;
use function strcasecmp;
use function trim;

enum PaymentMethodType: int
{
    case CARD_NUMBER = 1;
    case CLABE = 2;
    case ACCOUNT_NUMBER = 3;
    case WALLET = 4;
    case OTHER = 5;

    public static function tryFromInput(mixed $value): ?self
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::tryFrom((int) $value);
        }

        if (!is_string($value)) {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    public static function fromReference(string $reference): self
    {
        $reference = trim($reference);

        if (self::isCryptoWallet($reference)) {
            return self::WALLET;
        }

        $digits = preg_replace('/\D/', '', $reference) ?? '';

        return match (strlen($digits)) {
            16 => self::CARD_NUMBER,
            18 => self::CLABE,
            10 => self::ACCOUNT_NUMBER,
            default => self::OTHER,
        };
    }

    private static function isCryptoWallet(string $value): bool
    {
        return (bool) preg_match(
            '/^(?:0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34}|(?:bc1|tb1|BC1|TB1)[a-zA-Z0-9]{25,87}|T[1-9A-HJ-NP-Za-km-z]{33})$/',
            $value,
        );
    }
}
