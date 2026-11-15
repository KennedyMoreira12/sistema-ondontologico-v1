<?php

namespace App\Services\Notificacoes;

use App\Services\Notificacoes\Coletores\AgendaColetor;
use App\Services\Notificacoes\Coletores\AssinaturasColetor;
use App\Services\Notificacoes\Coletores\EstoqueColetor;
use App\Services\Notificacoes\Coletores\FinanceiroColetor;
use App\Services\Notificacoes\Coletores\PacientesColetor;

/**
 * Todos os coletores da clínica, na ordem em que o painel mostra.
 *
 * A lista é explícita de propósito: um coletor novo precisa ser registrado
 * aqui, e não entra em produção por acidente de descoberta automática.
 */
class ColetorNotificacoes
{
    public function __construct(
        private FinanceiroColetor $financeiro,
        private EstoqueColetor $estoque,
        private AgendaColetor $agenda,
        private PacientesColetor $pacientes,
        private AssinaturasColetor $assinaturas,
    ) {}

    /**
     * @return array<string, list<Alerta>>
     */
    public function coletar(): array
    {
        return [
            $this->financeiro->categoria() => $this->financeiro->coletar(),
            $this->estoque->categoria() => $this->estoque->coletar(),
            $this->agenda->categoria() => $this->agenda->coletar(),
            $this->pacientes->categoria() => $this->pacientes->coletar(),
            $this->assinaturas->categoria() => $this->assinaturas->coletar(),
        ];
    }
}
