<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envia ao Laravel a imagem que o workflow n8n gerou no OpenRouter.
 *
 * O mesmo endpoint recebe dois corpos diferentes: o sucesso vai como arquivo
 * em `image`, e a falha da geração vai como texto em `error` sem arquivo, para
 * o post ficar com a imagem pendente em vez de perder o conteúdo que já saiu
 * correto. Por isso `image` usa `required_without:error` — os dois juntos não
 * são esperados, mas apenas um precisa estar sempre presente.
 *
 * O tipo é conferido pelo conteúdo (`mimes` lê o arquivo), nunca pelo nome do
 * arquivo, porque a extensão vem do MIME real que a gente grava no disco.
 */
class StoreSocialPostImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240', 'required_without:error'],
            'error' => ['nullable', 'string', 'max:500'],
            'model' => ['nullable', 'string', 'max:100'],
            'cost_usd' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
