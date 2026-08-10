<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class LaunchTopicSearchRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['confirmed' => ['accepted'], 'query_text' => ['required', 'string', 'min:2', 'max:500']];
    }
}
