<?php

namespace App\Http\Controllers\Public;

use App\Application\Seo\Commands\ListSeoProfilesCommand;
use App\Application\Seo\Usecases\SeoUsecaseInterface;
use App\Http\Controllers\Controller;

class SeoController extends Controller
{
    public function __construct(private SeoUsecaseInterface $seo) {}

    public function index()
    {
        return response()->json($this->seo->list(new ListSeoProfilesCommand));
    }
}
