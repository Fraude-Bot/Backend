<?php

namespace App\Application\Report;

use App\Domain\Scammer\ValueObjects\Clue;
use App\Domain\Search\ValueObjects\CardSearchResult;
use Illuminate\Http\UploadedFile;

interface ReportUsecaseInterface
{
    public function search(Clue $clue, int $page, int $count): CardSearchResult;

    public function storeTemporaryProfilePicture(UploadedFile $file): string;

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string>
     */
    public function storeTemporaryProofs(array $files): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array{id: int, organization_id: int, contact_ids: list<int>, payment_method_ids: list<int>, report_proof_ids: list<int>}
     */
    public function storeOrganization(array $input): array;
}
