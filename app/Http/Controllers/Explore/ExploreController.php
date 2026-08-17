<?php

namespace App\Http\Controllers\Explore;

use App\Domain\Explore\ReadModels\BuildExploreIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Explore\IndexExploreRequest;
use App\Http\ViewModels\LibraryViewModel;
use Inertia\Inertia;
use Inertia\Response;

class ExploreController extends Controller
{
    public function __invoke(IndexExploreRequest $request, BuildExploreIndex $index, LibraryViewModel $library): Response
    {
        return Inertia::render('explore/index', [
            ...$index->handle($request->user(), $request->filters(), $request->integer('page', 1)),
            'library' => $library->context($request->user()),
        ]);
    }
}
