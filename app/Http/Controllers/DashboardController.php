<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\ReadModels\BuildDashboard;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildDashboard $dashboard): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'dashboard' => Inertia::defer(
                fn (): array => $dashboard->handle($user),
                rescue: true,
            ),
        ]);
    }
}
