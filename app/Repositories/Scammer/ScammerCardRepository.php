<?php

namespace App\Repositories\Scammer;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\Scammer\Enums\ClueType;
use App\Domain\Scammer\ValueObjects\Clue;
use App\Models\Scammer;
use App\Repositories\Search\CardPreviewLoader;
use App\Repositories\Search\ClueSearchInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ScammerCardRepository implements ClueSearchInterface, ScammerCardRepositoryInterface
{
    public function matchQuery(Clue $clue): ?Builder
    {
        return match ($clue->getType()) {
            ClueType::EMAIL => $this->matchByEmail($clue->getValue()),
            ClueType::CARD_NUMBER => $this->matchByCardNumber($clue->getValue()),
            ClueType::CLABE => $this->matchByClabe($clue->getValue()),
            ClueType::ACCOUNT_NUMBER => $this->matchByAccountNumber($clue->getValue()),
            ClueType::PHONE => $this->matchByPhoneNumber($clue->getValue()),
            ClueType::URL => $this->matchByUrl($clue->getValue()),
            ClueType::WALLET => $this->matchByWallet($clue->getValue()),
            ClueType::NAME => $this->matchByName($clue->getValue()),
            ClueType::NOTHING => null,
        };
    }

    public function hydrate(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $scammers = Scammer::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->select(['id', 'name', 'is_active', 'created_at', 'updated_at'])
            ->withCount(['reports' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->keyBy('id');

        $organizationNames = CardPreviewLoader::organizationNamesByScammerId($ids);
        $productNames = CardPreviewLoader::productNamesByScammerId($ids);

        return $scammers->each(function (Scammer $scammer) use ($organizationNames, $productNames): void {
            $scammer->setAttribute('card_organization_names', $organizationNames->get($scammer->id, []));
            $scammer->setAttribute('card_product_names', $productNames->get($scammer->id, []));
        });
    }

    public function matchByName(string $name): ?Builder
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name);

        return Scammer::query()
            ->where('is_active', true)
            ->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
    }

    public function matchByCardNumber(string $cardNumber): ?Builder
    {
        return $this->matchPaymentMethod(PaymentMethodType::CARD_NUMBER, $cardNumber);
    }

    public function matchByClabe(string $clabe): ?Builder
    {
        return $this->matchPaymentMethod(PaymentMethodType::CLABE, $clabe);
    }

    public function matchByAccountNumber(string $accountNumber): ?Builder
    {
        return $this->matchPaymentMethod(PaymentMethodType::ACCOUNT_NUMBER, $accountNumber);
    }

    public function matchByEmail(string $email): ?Builder
    {
        return $this->matchContact(PlatformType::EMAIL, $email);
    }

    public function matchByPhoneNumber(string $phoneNumber): ?Builder
    {
        return $this->matchContact(PlatformType::CELLPHONE, $phoneNumber);
    }

    public function matchByUrl(string $url): ?Builder
    {
        return $this->matchContact(PlatformType::URL, $url);
    }

    public function matchByWallet(string $wallet): ?Builder
    {
        return $this->matchPaymentMethod(PaymentMethodType::WALLET, $wallet);
    }

    private function matchPaymentMethod(PaymentMethodType $type, string $reference): Builder
    {
        return Scammer::query()
            ->where('is_active', true)
            ->whereHas('paymentMethods', fn (Builder $query) => $query
                ->where('type', $type)
                ->where('reference', $reference)
                ->where('is_active', true));
    }

    private function matchContact(PlatformType $platform, string $reference): Builder
    {
        return Scammer::query()
            ->where('is_active', true)
            ->whereHas('contacts', fn (Builder $query) => $query
                ->where('platform', $platform)
                ->where('reference', $reference)
                ->where('is_active', true));
    }
}
