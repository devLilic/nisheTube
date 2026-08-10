<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class LaunchTopicDiscoveryRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['confirmed' => ['accepted'], 'research_run' => ['required', 'uuid']];
    }
}
