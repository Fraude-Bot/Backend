<?php

namespace App\Application\Media\Usecases;

use App\Application\Media\Commands\DeleteTemporaryMediaCommand;
use App\Application\Media\Commands\StoreTemporaryProfilePictureCommand;

interface MediaUsecaseInterface
{
    public function __invoke(DeleteTemporaryMediaCommand $command): void;

    public function storeTemporaryProfilePicture(StoreTemporaryProfilePictureCommand $command): string;
}
