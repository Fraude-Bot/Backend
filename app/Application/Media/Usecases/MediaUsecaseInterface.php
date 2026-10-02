<?php

namespace App\Application\Media\Usecases;

use App\Application\Media\Commands\DeleteTemporaryMediaCommand;

interface MediaUsecaseInterface
{
    public function __invoke(DeleteTemporaryMediaCommand $command): void;
}
