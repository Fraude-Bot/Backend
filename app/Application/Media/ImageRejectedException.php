<?php

namespace App\Application\Media;

use RuntimeException;

class ImageRejectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The image was rejected.');
    }
}
