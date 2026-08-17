<?php

namespace App\Http\Controllers\Navigation;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompletedRunNotificationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['completed_run_notifications_read_at' => now()])->save();

        return back(status: 303);
    }
}
