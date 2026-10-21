<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload de uma planilha de prospecção (.xlsx ou .csv) para prévia e importação.
 *
 * A extensão é checada aqui além do conteúdo, porque a leitura do arquivo
 * depende do formato (zip de XMLs vs. CSV). A rota também tem o middleware
 * `superadmin`; o `authorize()` cobre o reaproveitamento do request.
 */
class ProspectImportRequest extends FormRequest
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
            'arquivo' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'Selecione um arquivo .xlsx ou .csv.',
            'arquivo.file' => 'O envio não é um arquivo válido.',
            'arquivo.max' => 'O arquivo não pode passar de 5 MB.',
            'arquivo.extensions' => 'Formato não suportado. Envie um arquivo .xlsx ou .csv.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
