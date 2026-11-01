<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProspectCommercialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->guard('admin')->check() && auth()->guard('admin')->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `template_id` e `mensagem` são opcionais em si; a exigência de informar
     * pelo menos um deles vale só para preparar/enviar (ver withValidator), de
     * modo que pausar/retomar o bot não falhem por campos vazios.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'prospect_id' => ['required', 'integer', 'exists:prospects,id'],
            'template_id' => ['nullable', 'integer', 'exists:prospect_message_templates,id'],
            'mensagem' => ['nullable', 'string', 'max:4096'],
            'corpo_aprovado' => ['nullable', 'string', 'max:4096'],
            'acao' => ['required', 'string', 'in:preparar,aprovar_enviar,pausar_bot,retomar_bot'],
        ];
    }

    /**
     * Mensagens de validação em português (o projeto não possui pasta lang/).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prospect_id.required' => 'Prospecto não informado.',
            'prospect_id.integer' => 'Prospecto inválido.',
            'prospect_id.exists' => 'Prospecto não encontrado.',
            'template_id.integer' => 'Modelo de mensagem inválido.',
            'template_id.exists' => 'O modelo de mensagem selecionado não existe.',
            'mensagem.string' => 'A mensagem deve ser um texto.',
            'mensagem.max' => 'A mensagem é muito longa (limite de 4096 caracteres).',
            'corpo_aprovado.max' => 'O conteúdo da prévia é muito longo (limite de 4096 caracteres).',
            'acao.required' => 'Ação não informada.',
            'acao.in' => 'Ação inválida.',
        ];
    }

    /**
     * Para preparar a prévia (e enviar), é preciso um modelo selecionado ou uma
     * mensagem digitada. Pausar/retomar não exigem nenhum dos dois.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $acao = (string) $this->input('acao');

            if (! in_array($acao, ['preparar', 'aprovar_enviar'], true)) {
                return;
            }

            if (! $this->filled('template_id') && trim((string) $this->input('mensagem')) === '') {
                $validator->errors()->add('mensagem', 'Selecione um modelo de mensagem ou escreva uma mensagem.');
            }

            // O envio exige uma prévia gerada (o corpo aprovado vai junto).
            if ($acao === 'aprovar_enviar' && (string) $this->input('corpo_aprovado') === '') {
                $validator->errors()->add('corpo_aprovado', 'Prepare a prévia antes de enviar.');
            }
        });
    }
}
