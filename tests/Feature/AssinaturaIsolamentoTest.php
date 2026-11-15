<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Isolamento das assinaturas entre clínicas.
 *
 * Cada clínica tem banco próprio. Uma assinatura da clínica A não pode ser
 * lida, impressa nem assinada por um usuário da clínica B, mesmo com o ID na
 * URL — o vínculo é resolvido no banco da clínica em atendimento.
 */
class AssinaturaIsolamentoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private const PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function usuarioComPermissoes(Clinic $clinica, string $email): User
    {
        $role = Role::create(['name' => 'papel_'.uniqid()]);

        foreach ([
            'pacientes.view',
            'prontuario.view',
            'contratos.view',
            'contratos.create',
            'contratos.update',
            'evolucao.view',
            'evolucao.update',
        ] as $permissao) {
            [$module, $action] = explode('.', $permissao, 2);

            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        $user = User::factory()->forClinic($clinica->id)->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    public function test_assinatura_de_uma_clinica_nao_e_alcancavel_por_outra(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@assinatura.test');
        $this->conectar($a);

        $userA = $this->usuarioComPermissoes($a, 'admin.a@assinatura.test');

        $paciente = Paciente::create(['nome' => 'Paciente A', 'status' => 'ativo']);
        $termo = Contract::create([
            'paciente_id' => $paciente->id,
            'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
            'conteudo' => 'Termo da clínica A.',
            'status' => 'pendente',
        ]);

        $this->actingAs($userA)
            ->post(route('pacientes.assinaturas.termo', $paciente), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertSessionHasNoErrors();

        $this->conectar($a);

        $assinatura = DocumentSignature::query()->sole();

        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@assinatura.test');
        $this->conectar($b);

        $userB = $this->usuarioComPermissoes($b, 'admin.b@assinatura.test');

        $this->actingAs($userB)
            ->get(route('pacientes.assinaturas.imagem', [$paciente, $assinatura]))
            ->assertNotFound();

        $this->actingAs($userB)
            ->get(route('pacientes.assinaturas.index', $paciente))
            ->assertNotFound();

        $this->actingAs($userB)
            ->post(route('pacientes.assinaturas.termo', $paciente), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertNotFound();

        $this->actingAs($userB)
            ->get(route('contratos.pdf', $termo))
            ->assertNotFound();

        $this->actingAs($userB)
            ->get(route('pacientes.ficha.pdf', $paciente))
            ->assertNotFound();

        $this->conectar($b);

        $this->assertSame(0, DocumentSignature::query()->count(), 'A clínica B não deve enxergar assinatura nenhuma.');

        $this->conectar($a);

        $this->assertSame(1, DocumentSignature::query()->count(), 'A assinatura da clínica A continua intacta.');
    }
}
