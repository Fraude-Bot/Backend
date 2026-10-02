<?php

namespace App\Application\Scammer;

use App\Domain\Contact\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScammerUsecase implements ScammerUsecaseInterface
{
    public function __construct(
        private ScammerRepositoryInterface $scammers,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
    ) {}

    public function show(int $id): ?Scammer
    {
        return $this->scammers->findScammerById($id);
    }

    public function calendar(int $id, int $year): ?Collection
    {
        return $this->scammers->findCalendarByScammerIdAndYear($id, $year);
    }

    public function contacts(int $id, int $page, int $count, ?string $platform): ?PaginatedResult
    {
        return $this->scammers->findPaginatedContactsById($id, $page, $count, $platform);
    }

    public function reports(int $id, int $page, int $count): ?PaginatedResult
    {
        return $this->scammers->findPaginatedReportsById($id, $page, $count);
    }

    public function map(int $id): ?MapResult
    {
        return $this->scammers->findMapById($id);
    }

    public function list(): Collection
    {
        return $this->scammers->list();
    }

    public function load(Scammer $scammer): Scammer
    {
        return $this->scammers->loadDetails($scammer);
    }

    public function store(array $data): Scammer
    {
        return DB::transaction(function () use ($data): Scammer {
            $scammer = $this->scammers->create([
                'name' => trim((string) $data['name']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['contacts'] ?? [] as $contactData) {
                if (! is_array($contactData)) {
                    continue;
                }

                $entity = new ContactEntity(
                    id: null,
                    name: (string) $contactData['name'],
                    platformType: PlatformType::from((int) $contactData['platform']),
                    reference: (string) $contactData['reference'],
                    isActive: (bool) ($contactData['is_active'] ?? true),
                );
                $values = $entity->toArray();
                $contact = $this->contacts->firstOrCreate(
                    $values['platform'],
                    $values['reference'],
                    $values['name'],
                    $values['is_active'],
                );
                $this->scammers->attachContact($scammer, $contact->id);
            }

            foreach ($data['paymentMethods'] ?? [] as $paymentMethodData) {
                if (! is_array($paymentMethodData)) {
                    continue;
                }

                $paymentMethod = $this->paymentMethods->firstOrCreate(
                    PaymentMethodType::from((int) $paymentMethodData['type']),
                    trim((string) $paymentMethodData['reference']),
                    (bool) ($paymentMethodData['is_active'] ?? true),
                );
                $this->scammers->attachPaymentMethod($scammer, $paymentMethod->id);
            }

            return $this->scammers->loadStored($scammer);
        });
    }

    public function update(Scammer $scammer, array $attributes): Scammer
    {
        return $this->scammers->update($scammer, $attributes);
    }

    public function delete(Scammer $scammer): void
    {
        $this->scammers->delete($scammer);
    }

    public function restore(int $id): Scammer
    {
        return $this->scammers->restore($id);
    }

    public function createContact(Scammer $scammer, array $data): Contact
    {
        return DB::transaction(function () use ($scammer, $data): Contact {
            $entity = new ContactEntity(
                id: null,
                name: (string) $data['name'],
                platformType: PlatformType::from((int) $data['platform']),
                reference: (string) $data['reference'],
                isActive: (bool) ($data['is_active'] ?? true),
            );
            $values = $entity->toArray();
            $contact = $this->contacts->firstOrCreate(
                $values['platform'],
                $values['reference'],
                $values['name'],
                $values['is_active'],
            );
            $this->scammers->attachContact($scammer, $contact->id);

            return $contact;
        });
    }

    public function updateContact(Scammer $scammer, Contact $contact, array $input, bool $platformProvided): ?Contact
    {
        if (! $this->scammers->hasContact($scammer, $contact)) {
            return null;
        }

        $platform = $contact->platform;

        if ($platformProvided) {
            $platform = PlatformType::from((int) $input['platform']);
        }

        $entity = new ContactEntity(
            id: $contact->id,
            name: array_key_exists('name', $input) ? (string) $input['name'] : $contact->name,
            platformType: $platform,
            reference: array_key_exists('reference', $input) ? (string) $input['reference'] : $contact->reference,
            isActive: array_key_exists('is_active', $input) ? (bool) $input['is_active'] : $contact->is_active,
        );

        return $this->contacts->update($contact, $entity->toArray());
    }

    public function hasPaymentMethod(Scammer $scammer, mixed $type, string $reference): bool
    {
        return $this->paymentMethods->linkedToScammer($scammer, $type, $reference);
    }

    public function createPaymentMethod(Scammer $scammer, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($scammer, $data): PaymentMethod {
            $paymentMethod = $this->paymentMethods->firstOrCreate(
                PaymentMethodType::from((int) $data['type']),
                trim((string) $data['reference']),
                (bool) ($data['is_active'] ?? true),
            );
            $this->scammers->attachPaymentMethod($scammer, $paymentMethod->id);

            return $paymentMethod;
        });
    }
}
