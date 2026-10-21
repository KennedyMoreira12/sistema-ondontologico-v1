<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->admin()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'legenda_instagram' => ['nullable', 'string', 'max:2200'],
            'legenda_facebook' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:1000'],

            /**
             * Ao menos uma rede é obrigatória: um post sem destino não tem o que
             * publicar. Os valores são conferidos contra a lista que o serviço
             * de publicação entende.
             */
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in(['facebook', 'instagram'])],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platforms.required' => 'Escolha ao menos uma rede (Facebook ou Instagram).',
            'platforms.min' => 'Escolha ao menos uma rede (Facebook ou Instagram).',
            'image.max' => 'A imagem pode ter no máximo 8 MB.',
            'image.mimes' => 'A imagem deve ser JPG, PNG ou WEBP.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
