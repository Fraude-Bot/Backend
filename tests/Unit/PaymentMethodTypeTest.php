<?php

namespace Tests\Unit;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use PHPUnit\Framework\TestCase;

class PaymentMethodTypeTest extends TestCase
{
    public function test_digit_lengths_infer_card_clabe_and_account(): void
    {
        $this->assertSame(PaymentMethodType::CARD_NUMBER, PaymentMethodType::fromReference('4152313732125521'));
        $this->assertSame(PaymentMethodType::CARD_NUMBER, PaymentMethodType::fromReference('4152 3137 3212 5521'));
        $this->assertSame(PaymentMethodType::CLABE, PaymentMethodType::fromReference('012345678901234567'));
        $this->assertSame(PaymentMethodType::CLABE, PaymentMethodType::fromReference('0123 4567 8901 2345 67'));
        $this->assertSame(PaymentMethodType::ACCOUNT_NUMBER, PaymentMethodType::fromReference('1234567890'));
    }

    public function test_crypto_addresses_are_wallets(): void
    {
        $this->assertSame(
            PaymentMethodType::WALLET,
            PaymentMethodType::fromReference('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0'),
        );
        $this->assertSame(
            PaymentMethodType::WALLET,
            PaymentMethodType::fromReference('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'),
        );
        $this->assertSame(
            PaymentMethodType::WALLET,
            PaymentMethodType::fromReference('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'),
        );
        $this->assertSame(
            PaymentMethodType::WALLET,
            PaymentMethodType::fromReference('T9yD14Nj9j7xAB4dbGeiX9h8unkKHxuWwb'),
        );
    }

    public function test_anything_else_is_other(): void
    {
        $this->assertSame(PaymentMethodType::OTHER, PaymentMethodType::fromReference('cash'));
        $this->assertSame(PaymentMethodType::OTHER, PaymentMethodType::fromReference('12345'));
        $this->assertSame(PaymentMethodType::OTHER, PaymentMethodType::fromReference(''));
    }
}
