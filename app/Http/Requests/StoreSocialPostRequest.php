<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tema' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string', 'max:255'],
            'publico' => ['nullable', 'string', 'max:191'],
            'tipo' => ['nullable', 'string', 'max:60'],
            'observacoes' => ['nullable', 'string'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'legenda_instagram' => ['nullable', 'string'],
            'legenda_facebook' => ['nullable', 'string'],
            'cta' => ['nullable', 'string', 'max:255'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'prompt_imagem' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:1000', new PublicHttpsUrl],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in(['facebook', 'instagram'])],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('platforms') && is_string($this->input('platforms'))) {
            $this->merge(['platforms' => [$this->input('platforms')]]);
        }

        if ($this->has('image_url') && is_string($this->input('image_url')) && trim($this->input('image_url')) === '') {
            $this->merge(['image_url' => null]);
        }
    }
}
