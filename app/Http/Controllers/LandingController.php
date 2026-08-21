<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->user() !== null) {
            return to_route('dashboard');
        }

        return Inertia::render('welcome');
    }
}
