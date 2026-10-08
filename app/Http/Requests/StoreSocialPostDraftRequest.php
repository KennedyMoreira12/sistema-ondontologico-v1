<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados que o formulário web "Novo Post" coleta para gerar o conteúdo.
 *
 * É o subconjunto de `StoreSocialPostRequest` que o operador informa à mão: o
 * texto gerado (título, legendas, hashtags) não vem do formulário, e sim do
 * workflow que consulta o modelo. O `image_url` reusa a mesma trava de SSRF da
 * API — aqui nada é baixado, mas o endereço é repassado adiante.
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
            'tema' => ['required', 'string', 'max:255'],
            'objetivo' => ['required', 'string', 'max:255'],
            'publico' => ['required', 'string', 'max:191'],
            'tipo' => ['required', 'string', Rule::in(['educativo', 'institucional', 'promocional', 'informativo', 'sazonal'])],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:1000', new PublicHttpsUrl],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in(['facebook', 'instagram'])],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('image_url') && is_string($this->input('image_url')) && trim($this->input('image_url')) === '') {
            $this->merge(['image_url' => null]);
        }
    }
}
