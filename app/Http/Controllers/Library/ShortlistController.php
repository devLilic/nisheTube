<?php

namespace App\Http\Controllers\Library;

use App\Domain\Library\ReadModels\BuildShortlistComparison;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShortlistController extends Controller
{
    public function __invoke(Request $request, BuildShortlistComparison $comparison): Response
    {
        $runs = $request->input('runs', []);
        $runs = is_array($runs) ? $runs : [];
        $selected = array_slice(array_values(array_unique(array_filter(
            $runs,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ))), 0, 5);

        return Inertia::render('shortlist/index', ['shortlist' => $comparison->handle($request->user(), $selected)]);
    }
}
