<?php

namespace App\Application\Report\Usecases;

use App\Application\Report\Commands\SearchReportsCommand;
use App\Application\Report\Commands\StoreOrganizationReportCommand;
use App\Application\Report\Commands\StoreScammerReportCommand;
use App\Application\Report\Commands\StoreTemporaryProfilePictureCommand;
use App\Application\Report\Commands\StoreTemporaryProofsCommand;
use App\Domain\Search\ValueObjects\CardSearchResult;

interface ReportUsecaseInterface
{
    public function search(SearchReportsCommand $command): CardSearchResult;

    public function storeTemporaryProfilePicture(StoreTemporaryProfilePictureCommand $command): string;

    /**
     * @return list<string>
     */
    public function storeTemporaryProofs(StoreTemporaryProofsCommand $command): array;

    /**
     * @return array{id: int, organization_id: int, contact_ids: list<int>, payment_method_ids: list<int>, product_ids: list<int>, scammer_ids: list<int>, report_proof_ids: list<int>}
     */
    public function storeOrganization(StoreOrganizationReportCommand $command): array;

    /**
     * @return array{id: int, scammer_id: int, contact_ids: list<int>, payment_method_ids: list<int>, product_ids: list<int>, organization_ids: list<int>, report_proof_ids: list<int>}
     */
    public function storeScammer(StoreScammerReportCommand $command): array;
}
