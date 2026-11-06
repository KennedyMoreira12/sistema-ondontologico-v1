<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Isolamento HTTP dos módulos que substituíram as planilhas da Daiane.
 *
 * O `TenantIsolationTest` prova que os bancos são separados; este arquivo
 * prova que as telas de agenda, atendimento, financeiro e relatórios também
 * não misturam dados quando duas clínicas usam o mesmo processo. E que um
 * registro da outra clínica não é alcançável nem pelo ID direto na URL.
 */
class TenantIsolationModulosTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_telas_da_daiane_nao_expoem_dados_de_outra_clinica(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        $this->conectar($a);
        $this->semearClinica($a, 'A');
        $userA = $this->usuarioComPermissoes($a, 'admin.a@clinica.test');

        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@clinica.test');
        $this->conectar($b);
        $this->semearClinica($b, 'B');

        $this->actingAs($userA);

        $this->get(route('agenda.dia', ['data' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Paciente A')
            ->assertDontSee('Paciente B');

        $this->get(route('atendimentos.index', ['data' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Paciente A')
            ->assertDontSee('Paciente B');

        $this->get(route('financeiro.contas-pagar.index'))
            ->assertOk()
            ->assertSee('Conta A')
            ->assertDontSee('Conta B');

        $this->get(route('financeiro.index'))
            ->assertOk()
            ->assertSee('Paciente A')
            ->assertDontSee('Paciente B');

        $this->get(route('relatorios.receitas', [
            'de' => today()->toDateString(),
            'ate' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Paciente A')
            ->assertDontSee('Paciente B');

        $fluxo = $this->get(route('financeiro.fluxo-caixa', [
            'de' => today()->toDateString(),
            'ate' => today()->toDateString(),
        ]))->assertOk();

        // Cada clínica recebeu R$ 50; se houvesse vazamento, o total seria 100.
        $this->assertSame(50.0, round((float) $fluxo->viewData('totalRecebido'), 2));
    }

    public function test_registro_de_outra_clinica_nao_e_alcancavel_pelo_id(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        $this->conectar($a);
        $this->semearClinica($a, 'A');
        $userA = $this->usuarioComPermissoes($a, 'admin.a@clinica.test');

        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@clinica.test');
        $this->conectar($b);
        $dadosB = $this->semearClinica($b, 'B');

        /*
         * A clínica B cria dois registros: o segundo recebe um ID que não
         * existe na clínica A. Assim o teste prova o 404 de verdade, sem
         * confundir com um ID coincidente entre bancos diferentes.
         */
        $segundaContaB = AccountsPayable::create([
            'nome_conta' => 'Conta B exclusiva',
            'valor' => 80,
            'data_vencimento' => today()->toDateString(),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'outros',
            'tipo_documento' => 'outros',
        ]);

        $segundaConsultaB = $this->criarConsulta($b, 'B', $dadosB['status'], 11);

        $this->actingAs($userA);

        $this->get(route('financeiro.contas-pagar.show', $segundaContaB))->assertNotFound();

        $this->patch(route('agenda.reagendar', $segundaConsultaB), [
            'dia' => today()->toDateString(),
            'hora_inicio' => '15:00',
            'duracao' => 30,
        ])->assertNotFound();

        // O registro da clínica B continua intacto no banco dela.
        $this->conectar($b);

        $this->assertSame(
            'Conta B exclusiva',
            AccountsPayable::findOrFail($segundaContaB->id)->nome_conta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function semearClinica(Clinic $clinic, string $sufixo): array
    {
        $profissional = Professional::create([
            'nome' => "Profissional {$sufixo}",
            'is_active' => true,
        ]);

        $sala = Room::create([
            'nome' => "Sala {$sufixo}",
            'is_active' => true,
        ]);

        $status = AppointmentStatus::create([
            'name' => 'agendado',
            'label' => 'Agendado',
            'cor' => '#0d6efd',
        ]);

        $paciente = Paciente::create([
            'clinica_id' => $clinic->id,
            'nome' => "Paciente {$sufixo}",
            'status' => 'ativo',
        ]);

        $consulta = $this->criarConsulta($clinic, $sufixo, $status, 9, $paciente, $profissional, $sala);

        AccountsPayable::create([
            'nome_conta' => "Conta {$sufixo}",
            'valor' => 100,
            'data_vencimento' => today()->toDateString(),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'outros',
            'tipo_documento' => 'outros',
        ]);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'descricao' => "Recebível {$sufixo}",
            'vencimento' => today()->toDateString(),
            'valor' => 50,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        Payment::create([
            'paciente_id' => $paciente->id,
            'data' => today()->toDateString(),
            'valor' => 50,
            'servico' => "Serviço {$sufixo}",
        ]);

        return compact('profissional', 'sala', 'status', 'paciente', 'consulta');
    }

    private function criarConsulta(
        Clinic $clinic,
        string $sufixo,
        AppointmentStatus $status,
        int $hora,
        ?Paciente $paciente = null,
        ?Professional $profissional = null,
        ?Room $sala = null,
    ): Appointment {
        $paciente ??= Paciente::query()->where('nome', "Paciente {$sufixo}")->firstOrFail();
        $profissional ??= Professional::query()->where('nome', "Profissional {$sufixo}")->firstOrFail();
        $sala ??= Room::query()->where('nome', "Sala {$sufixo}")->firstOrFail();

        $inicio = today()->setTime($hora, 0);

        return Appointment::create([
            'clinica_id' => $clinic->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'room_id' => $sala->id,
            'appointment_status_id' => $status->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => "Atendimento {$sufixo}",
            'servico' => "Serviço {$sufixo}",
        ]);
    }

    private function usuarioComPermissoes(Clinic $clinic, string $email): User
    {
        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Admin '.$clinic->name,
            'email' => $email,
            'password' => 'senha-teste-123',
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'papel_'.uniqid()]);

        foreach ([
            'agenda.view', 'agenda.update', 'financeiro.view', 'financeiro.receive',
            'relatorios.view', 'pacientes.view',
        ] as $permissao) {
            [$module, $action] = explode('.', $permissao, 2);

            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        $user->assignRole($role);

        return $user;
    }
}
