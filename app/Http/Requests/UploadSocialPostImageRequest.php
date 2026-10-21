<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Imagem que o operador envia pelo painel para um post social.
 *
 * Diferente do endpoint do n8n, aqui o arquivo é sempre obrigatório: o operador
 * escolheu enviar uma imagem e não há o caso "só o erro da geração". O tipo é
 * conferido pelo conteúdo com `mimes`, que lê o arquivo em vez de confiar na
 * extensão do nome.
 */
class UploadSocialPostImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
