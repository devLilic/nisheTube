<?php

namespace App\Http\Controllers\Navigation;

use App\Domain\Navigation\Services\GlobalResearchSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Navigation\GlobalResearchSearchRequest;
use Illuminate\Http\JsonResponse;

class GlobalResearchSearchController extends Controller
{
    public function __invoke(GlobalResearchSearchRequest $request, GlobalResearchSearch $search): JsonResponse
    {
        return response()->json($search->search($request->user(), (string) $request->validated('q')));
    }
}
