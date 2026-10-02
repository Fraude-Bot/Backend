<?php

namespace App\Application\Scammer\Usecases;

use App\Application\Scammer\Commands\CreateScammerContactCommand;
use App\Application\Scammer\Commands\CreateScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\DeleteScammerCommand;
use App\Application\Scammer\Commands\HasScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\ListScammerContactsCommand;
use App\Application\Scammer\Commands\ListScammerReportsCommand;
use App\Application\Scammer\Commands\ListScammersCommand;
use App\Application\Scammer\Commands\LoadScammerCommand;
use App\Application\Scammer\Commands\RestoreScammerCommand;
use App\Application\Scammer\Commands\ScammerCalendarCommand;
use App\Application\Scammer\Commands\ScammerMapCommand;
use App\Application\Scammer\Commands\ShowScammerCommand;
use App\Application\Scammer\Commands\StoreScammerCommand;
use App\Application\Scammer\Commands\SuggestScammersCommand;
use App\Application\Scammer\Commands\UpdateScammerCommand;
use App\Application\Scammer\Commands\UpdateScammerContactCommand;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use Illuminate\Support\Collection;

interface ScammerUsecaseInterface
{
    public function show(ShowScammerCommand $command): ?Scammer;

    public function calendar(ScammerCalendarCommand $command): ?Collection;

    public function contacts(ListScammerContactsCommand $command): ?PaginatedResult;

    public function reports(ListScammerReportsCommand $command): ?PaginatedResult;

    public function map(ScammerMapCommand $command): ?MapResult;

    /**
     * @return list<string>
     */
    public function suggest(SuggestScammersCommand $command): array;

    public function list(ListScammersCommand $command): Collection;

    public function load(LoadScammerCommand $command): Scammer;

    public function store(StoreScammerCommand $command): Scammer;

    public function update(UpdateScammerCommand $command): Scammer;

    public function delete(DeleteScammerCommand $command): void;

    public function restore(RestoreScammerCommand $command): Scammer;

    public function createContact(CreateScammerContactCommand $command): Contact;

    public function updateContact(UpdateScammerContactCommand $command): ?Contact;

    public function hasPaymentMethod(HasScammerPaymentMethodCommand $command): bool;

    public function createPaymentMethod(CreateScammerPaymentMethodCommand $command): PaymentMethod;
}
