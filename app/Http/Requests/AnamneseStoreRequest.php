<?php

namespace App\Http\Requests;

use App\Support\AnamnesePreAtendimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnamneseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('anamnese.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $doencas = $this->input('respostas.doencas', []);
        $outrasSelecionadas = is_array($doencas) && in_array('outras', $doencas, true);

        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'respostas' => ['required', 'array'],
            'respostas.doencas' => ['nullable', 'array'],
            'respostas.doencas.*' => ['string', Rule::in(array_keys(AnamnesePreAtendimento::DOENCAS))],
            'respostas.outras_doencas' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf($outrasSelecionadas),
            ],
            'respostas.usa_medicamentos' => ['required', Rule::in([AnamnesePreAtendimento::SIM, AnamnesePreAtendimento::NAO])],
            'respostas.medicamentos' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('respostas.usa_medicamentos') === AnamnesePreAtendimento::SIM),
            ],
            'respostas.dores_tonturas' => ['required', Rule::in([AnamnesePreAtendimento::SIM, AnamnesePreAtendimento::NAO])],
            'respostas.dores_tonturas_detalhes' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('respostas.dores_tonturas') === AnamnesePreAtendimento::SIM),
            ],
            'respostas.alergias' => ['required', Rule::in([AnamnesePreAtendimento::SIM, AnamnesePreAtendimento::NAO])],
            'respostas.alergias_detalhes' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('respostas.alergias') === AnamnesePreAtendimento::SIM),
            ],
            'respostas.tratamento_medico' => ['required', Rule::in([AnamnesePreAtendimento::SIM, AnamnesePreAtendimento::NAO])],
            'respostas.tratamento_medico_detalhes' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('respostas.tratamento_medico') === AnamnesePreAtendimento::SIM),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'respostas.required' => 'Preencha o questionário de pré-atendimento.',
            'respostas.doencas.*.in' => 'Selecione apenas doenças da lista.',
            'respostas.outras_doencas.required' => 'Especifique quais são as outras doenças.',
            'respostas.usa_medicamentos.required' => 'Informe se faz uso de medicamentos.',
            'respostas.medicamentos.required' => 'Informe quais medicamentos você usa.',
            'respostas.dores_tonturas.required' => 'Informe se sente dores de cabeça ou tonturas.',
            'respostas.dores_tonturas_detalhes.required' => 'Descreva as dores de cabeça ou tonturas.',
            'respostas.alergias.required' => 'Informe se possui alergias.',
            'respostas.alergias_detalhes.required' => 'Descreva as alergias.',
            'respostas.tratamento_medico.required' => 'Informe se está em tratamento médico.',
            'respostas.tratamento_medico_detalhes.required' => 'Descreva o tratamento médico atual.',
        ];
    }
}
