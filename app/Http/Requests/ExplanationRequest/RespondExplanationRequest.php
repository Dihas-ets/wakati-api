<?php

namespace App\Http\Requests\ExplanationRequest;

use Illuminate\Foundation\Http\FormRequest;

class RespondExplanationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $explanation = $this->route('explanationRequest');

        return $explanation && $this->user() && $this->user()->id === $explanation->user_id;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
