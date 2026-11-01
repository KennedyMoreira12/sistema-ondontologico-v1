<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados que o formulário web "Novo Post" coleta na composição manual.
 *
 * O texto (título, legendas e hashtags) é escrito à mão pelo operador — não há
 * mais geração por IA. O `image_url` reusa a mesma trava de SSRF da API: aqui
 * nada é baixado, mas o endereço é validado antes de ser persistido.
 */
class StoreSocialPostDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'legenda_instagram' => ['nullable', 'string', 'max:2200'],
            'legenda_facebook' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'image_url' => ['nullable', 'string', 'max:1000', new PublicHttpsUrl],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in(['facebook', 'instagram'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('image_url') && is_string($this->input('image_url')) && trim($this->input('image_url')) === '') {
            $this->merge(['image_url' => null]);
        }
    }
}
