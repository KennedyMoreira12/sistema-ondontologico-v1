<?php

namespace Tests\Feature;

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * O token de integração é POR CLÍNICA, e essa é a propriedade de segurança que
 * sustenta o bot por clínica: o token da clínica A não pode abrir o banco da
 * clínica B. Um token global daria ao n8n acesso a todos os pacientes do sistema.
 *
 * Aqui cada clínica tem seu próprio arquivo sqlite, então "vazar" exigiria que a
 * conexão `tenant` fosse trocada para o banco errado.
 */
class WhatsAppApiIsolamentoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_token_de_uma_clinica_serve_apenas_o_banco_dela(): void
    {
        $a = $this->criarClinica('Clínica A', 'a@teste.test');
        Paciente::create(['nome' => 'Paciente A', 'celular' => '5511900000001', 'status' => 'ativo']);

        $b = $this->criarClinica('Clínica B', 'b@teste.test');
        Paciente::create(['nome' => 'Paciente B', 'celular' => '5511900000002', 'status' => 'ativo']);

        $this->comToken($b['token'])
            ->getJson(route('api.whatsapp.paciente', ['telefone' => '5511900000002']))
            ->assertOk()
            ->assertJsonPath('paciente.nome', 'Paciente B');

        $this->comToken($a['token'])
            ->getJson(route('api.whatsapp.paciente', ['telefone' => '5511900000001']))
            ->assertOk()
            ->assertJsonPath('paciente.nome', 'Paciente A');
    }

    public function test_token_de_uma_clinica_nao_encontra_paciente_da_outra(): void
    {
        $a = $this->criarClinica('Clínica A', 'a@teste.test');
        Paciente::create(['nome' => 'Paciente A', 'celular' => '5511900000001', 'status' => 'ativo']);

        $b = $this->criarClinica('Clínica B', 'b@teste.test');

        // O telefone existe, mas só no banco da clínica A. Com o token da B a
        // consulta tem de vir vazia, mesmo que a B conheça o número.
        $this->comToken($b['token'])
            ->getJson(route('api.whatsapp.paciente', ['telefone' => '5511900000001']))
            ->assertOk()
            ->assertJsonPath('paciente', null);
    }

    public function test_info_do_token_mostra_a_clinica_do_token(): void
    {
        $a = $this->criarClinica('Clínica A', 'a@teste.test');
        $b = $this->criarClinica('Clínica B', 'b@teste.test');

        $this->comToken($a['token'])
            ->getJson(route('api.whatsapp.info'))
            ->assertOk()
            ->assertJsonPath('clinica.id', $a['clinic']->id)
            ->assertJsonPath('clinica.nome', 'Clínica A');

        $this->comToken($b['token'])
            ->getJson(route('api.whatsapp.info'))
            ->assertOk()
            ->assertJsonPath('clinica.id', $b['clinic']->id)
            ->assertJsonPath('clinica.nome', 'Clínica B');
    }

    /**
     * @return $this
     */
    private function comToken(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
