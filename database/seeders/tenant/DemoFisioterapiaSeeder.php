<?php

namespace Database\Seeders\tenant;

use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Avaliacao;
use App\Models\Budget;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\ConsumoSessao;
use App\Models\Contract;
use App\Models\EvolucaoSessao;
use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\PatientInsurance;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoFisioterapiaSeeder extends Seeder
{
    private Clinic $clinica;

    private User $admin;

    private User $userRecepcao;

    private User $userFinanceiro;

    private User $userFisio;

    private array $status;

    public function run(): void
    {
        $this->clinica = $this->resolveClinic();
        $this->admin = $this->resolveAdmin();

        $this->status = AppointmentStatus::pluck('id', 'name')->all();

        $this->ajustarCatalogoParaFisioterapia();
        $this->semearUsuariosEDirigentes();
        $this->semearPacientes();
        $this->semearConvivencia();
        $this->semearAvaliacoes();
        $this->semearAgendaEvolucoes();
        $this->semearPacotesESessoes();
        $this->semearFinanceiro();
        $this->semearContratos();
        $this->semearEstoque();
        $this->semearAuditoria();
    }

    private function criarUsuario(string $email, string $name, string $password, int $roleId): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'clinic_id' => $this->clinica->id,
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->assignRole(Role::findOrFail($roleId));

        return $user;
    }

    private function resolveClinic(): Clinic
    {
        $clinic = app(TenantManager::class)->current();

        if ($clinic === null) {
            throw new RuntimeException('DemoFisioterapiaSeeder precisa de uma clínica resolvida.');
        }

        return $clinic;
    }

    private function resolveAdmin(): User
    {
        $admin = User::query()
            ->where('clinic_id', $this->clinica->id)
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            throw new RuntimeException("Nenhum usuário administrativo encontrado para a clínica [{$this->clinica->name}].");
        }

        return $admin;
    }

    /**
     * Ajusta o catálogo da clínica para a vertical de fisioterapia.
     *
     * O catálogo que o `CatalogSeeder` cria é odontológico: procedimentos como
     * "Tratamento de canal", especialidades como "Endodontia" e a operadora
     * "OdontoSaúde". Numa demonstração de fisioterapia isso aparece na tela e
     * denuncia que o sistema é de outra clínica — então os itens odontológicos
     * saem e os de fisioterapia entram.
     *
     * ATENÇÃO: este seeder é de DEMONSTRAÇÃO. Ele ajusta o catálogo da clínica
     * onde roda, e por isso só deve ser executado num ambiente de demonstração
     * (`php artisan tenants:seed --demo-fisio`), nunca numa clínica real.
     */
    private function ajustarCatalogoParaFisioterapia(): void
    {
        // convênios primeiro: o plano aponta para a operadora
        InsurancePlan::whereIn('nome', ['Plano Essencial', 'Plano Completo'])->delete();
        InsuranceCompany::where('nome', 'OdontoSaúde')->delete();

        // especialidades: o modelo não tem is_active, então saem da lista
        Specialty::whereIn('name', [
            'Clínica geral', 'Endodontia', 'Cirurgia', 'Periodontia',
            'Ortodontia', 'Implantes', 'Odontopediatria',
        ])->delete();

        // procedimentos odontológicos
        Procedure::whereIn('codigo', [
            'LIMPO', 'CLIN_GER', 'RAD_PG', 'END_FR_A', 'RES_AME',
            'RES_RES', 'EXT_DEC', 'EXT_INC', 'PROT_COR', 'PT_BT_M',
        ])->delete();

        // categorias de produto exclusivas da odontologia
        ProductCategory::whereIn('name', [
            'Materiais restauradores', 'Cimentos', 'Anestésicos',
        ])->delete();

        // o papel "dentista" não existe numa clínica de fisioterapia: sai da
        // lista de perfis do painel (a permissões vêm junto)
        $papelDentista = Role::where('name', 'dentista')->first();
        if ($papelDentista !== null) {
            $papelDentista->permissions()->detach();
            $papelDentista->userRoles()->delete();
            $papelDentista->delete();
        }

        $procedimentos = [
            ['codigo' => 'FISIO_60', 'nome' => 'Fisioterapia (sessão de 60 minutos)', 'categoria' => 'Fisioterapia', 'duracao_minutos' => 60, 'valor_padrao' => 180.00],
            ['codigo' => 'FISIO_45', 'nome' => 'Fisioterapia (sessão de 45 minutos)', 'categoria' => 'Fisioterapia', 'duracao_minutos' => 45, 'valor_padrao' => 150.00],
            ['codigo' => 'AVAL_FIS', 'nome' => 'Avaliação inicial de fisioterapia', 'categoria' => 'Avaliação', 'duracao_minutos' => 60, 'valor_padrao' => 200.00],
            ['codigo' => 'RPG_SES', 'nome' => 'RPG — Reeducação Postural Global', 'categoria' => 'Postural', 'duracao_minutos' => 60, 'valor_padrao' => 190.00],
            ['codigo' => 'PIL_CLIN', 'nome' => 'Pilates clínico (grupo)', 'categoria' => 'Grupo', 'duracao_minutos' => 60, 'valor_padrao' => 90.00],
            ['codigo' => 'FOR_MUSC', 'nome' => 'Fortalecimento muscular orientado', 'categoria' => 'Exercício', 'duracao_minutos' => 45, 'valor_padrao' => 140.00],
            ['codigo' => 'HIDRO_TER', 'nome' => 'Hidroterapia', 'categoria' => 'Terapia física', 'duracao_minutos' => 45, 'valor_padrao' => 160.00],
            ['codigo' => 'DRY_NEED', 'nome' => 'Dry needling', 'categoria' => 'Invasivo', 'duracao_minutos' => 30, 'valor_padrao' => 220.00],
            ['codigo' => 'FOTO_LED', 'nome' => 'Fototerapia (LED)', 'categoria' => 'Terapia física', 'duracao_minutos' => 20, 'valor_padrao' => 80.00],
            ['codigo' => 'ULTRA_TER', 'nome' => 'Ultrassom terapêutico', 'categoria' => 'Terapia física', 'duracao_minutos' => 20, 'valor_padrao' => 70.00],
        ];

        foreach ($procedimentos as $procedimento) {
            Procedure::firstOrCreate(['codigo' => $procedimento['codigo']], $procedimento);
        }

        $especialidades = [
            'Fisioterapia', 'Fisioterapia Ortopédica', 'Fisioterapia Neurológica',
            'Fisioterapia Respiratória', 'Fisioterapia Esportiva', 'RPG', 'Osteopatia',
            'Pilates Clínico', 'Hidroterapia',
        ];

        foreach ($especialidades as $especialidade) {
            Specialty::firstOrCreate(['name' => $especialidade]);
        }
    }

    private function semearUsuariosEDirigentes(): void
    {
        foreach (Room::get() as $sala) {
            $sala->update(['clinica_id' => $this->clinica->id]);
        }

        $roles = Role::whereIn('name', ['fisioterapeuta', 'recepcao', 'financeiro'])->pluck('id', 'name');

        $this->userRecepcao = $this->criarUsuario('recepcao.fisio@ljdemo.test', 'Juliana Recepção', 'recepcao123', $roles['recepcao']);
        $this->userFinanceiro = $this->criarUsuario('financeiro.fisio@ljdemo.test', 'Renata Financeiro', 'financeiro123', $roles['financeiro']);
        $this->userFisio = $this->criarUsuario('fisioterapeuta@ljdemo.test', 'Dra. Camila Fisioterapia', 'fisio123', $roles['fisioterapeuta']);

        $ortopedia = Specialty::firstOrCreate(['name' => 'Fisioterapia Ortopédica']);
        $neurologia = Specialty::firstOrCreate(['name' => 'Fisioterapia Neurológica']);
        $respiratoria = Specialty::firstOrCreate(['name' => 'Fisioterapia Respiratória']);
        $esportiva = Specialty::firstOrCreate(['name' => 'Fisioterapia Esportiva']);

        $this->cadastrarProfissional('Dra. Camila Fisioterapia', '312.456.789-01', 'CREFITO-3/123456-F', 'fisioterapeuta', 40.00, [$ortopedia, $esportiva], $this->userFisio);
        $this->cadastrarProfissional('Dr. Lucas Reabilitação', '198.765.432-10', 'CREFITO-3/654321-F', 'fisioterapeuta', 40.00, [$neurologia, $respiratoria]);
    }

    private function cadastrarProfissional(string $nome, string $cpf, string $registro, string $tipo, float $comissao, array $especialidades, ?User $user = null): Professional
    {
        $profissional = Professional::firstOrCreate(
            ['nome' => $nome],
            [
                'clinica_id' => $this->clinica->id,
                'user_id' => $user?->id,
                'cpf' => $cpf,
                'cro' => null,
                'registro_profissional' => $registro,
                'conselho_regional' => 'CREFITO-3',
                'tipo' => $tipo,
                'comissao' => $comissao,
                'is_active' => true,
            ]
        );

        $profissional->specialties()->syncWithoutDetaching(collect($especialidades)->pluck('id')->all());

        return $profissional;
    }

    private function semearPacientes(): void
    {
        $dados = [
            ['Maria Oliveira', '390.533.447-05', '1985-03-12', 'F', 'maria.oliveira@email.com', '(11) 98765-4321', 'Rua das Flores', 'Bela Vista', 'São Paulo', 'SP'],
            ['João Pereira', '128.473.556-40', '1978-08-25', 'M', 'joao.pereira@email.com', '(11) 97654-3210', 'Av. Paulista', 'Centro', 'São Paulo', 'SP'],
            ['Ana Clara Santos', '433.552.671-15', '1992-01-19', 'F', 'anaclara.santos@email.com', '(11) 96543-2109', 'Rua Tatuapé', 'Tatuapé', 'São Paulo', 'SP'],
            ['Carlos Eduardo Lima', '145.618.290-77', '1980-11-03', 'M', 'carlos.lima@email.com', '(11) 95432-1098', 'Alameda Moema', 'Moema', 'São Paulo', 'SP'],
            ['Fernanda Costa', '578.125.903-08', '1995-06-27', 'F', 'fernanda.costa@email.com', '(11) 94321-0987', 'Rua Campinas', 'Campinas', 'Campinas', 'SP'],
            ['Rafael Mendes', '876.341.512-30', '1969-12-08', 'M', 'rafael.mendes@email.com', '(11) 93210-9876', 'Rua Guarulhos', 'Santana', 'São Paulo', 'SP'],
        ];

        foreach ($dados as [$nome, $cpf, $nascimento, $sexo, $email, $celular, $endereco, $bairro, $cidade, $estado]) {
            Paciente::firstOrCreate(
                ['cpf' => $cpf],
                [
                    'clinica_id' => $this->clinica->id,
                    'nome' => $nome,
                    'rg' => 'RG'.substr(preg_replace('/\D/', '', $cpf), 0, 8),
                    'nascimento' => $nascimento,
                    'sexo' => $sexo,
                    'email' => $email,
                    'phone' => '(11) 4000-'.rand(1000, 9999),
                    'celular' => $celular,
                    'cep' => sprintf('%05d-%03d', rand(11111, 99999), rand(0, 999)),
                    'endereco' => $endereco,
                    'numero' => (string) rand(10, 999),
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'estado' => $estado,
                    'responsavel' => null,
                    'status' => 'ativo',
                ]
            );
        }
    }

    private function semearConvivencia(): void
    {
        $co = InsuranceCompany::firstOrCreate(
            ['nome' => 'FisioSaúde'],
            ['cnpj' => '33.333.333/0001-33', 'phone' => '(11) 3500-0000', 'responsavel' => 'Comercial FisioSaúde', 'is_active' => true]
        );
        $plano = InsurancePlan::firstOrCreate(
            ['insurance_company_id' => $co->id, 'nome' => 'Plano Fisioterapia'],
            ['registro' => 'ANS-FIS-001', 'tipo' => 'ambulatorial', 'is_active' => true]
        );

        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $joao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();

        PatientInsurance::firstOrCreate(
            ['paciente_id' => $maria->id, 'insurance_plan_id' => $plano->id],
            ['numero_carteirinha' => 'FS'.rand(10000, 99999), 'validade' => now()->addMonths(12), 'is_active' => true]
        );
        PatientInsurance::firstOrCreate(
            ['paciente_id' => $joao->id, 'insurance_plan_id' => $plano->id],
            ['numero_carteirinha' => 'FS'.rand(10000, 99999), 'validade' => now()->addMonths(12), 'is_active' => true]
        );
    }

    private function semearAvaliacoes(): void
    {
        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $joao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();
        $ana = Paciente::where('cpf', '433.552.671-15')->firstOrFail();

        $camila = Professional::where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();
        $lucas = Professional::where('nome', 'Dr. Lucas Reabilitação')->firstOrFail();

        Avaliacao::firstOrCreate(
            ['paciente_id' => $maria->id, 'data' => now()->subDays(10)->toDateString()],
            [
                'profissional_id' => $camila->id,
                'queixa_principal' => 'Dor lombar há 3 meses, piora ao ficar em pé por muito tempo.',
                'historia_clinica' => 'Trabalha em escritório, permanece sentada por longos períodos.',
                'exame_fisico' => 'Dor à palpação da musculatura paravertebral lombar. Amplitude de movimento levemente reduzida.',
                'hipotese_clinica' => 'Lombalgia mecânica.',
                'objetivos' => 'Reduzir dor, melhorar mobilidade e fortalecer musculatura estabilizadora.',
                'plano_terapeutico' => 'Terapia manual, exercícios de estabilização e orientação postural.',
                'status' => Avaliacao::FINALIZADA,
                'finalizada_em' => now()->subDays(10),
            ]
        );

        Avaliacao::firstOrCreate(
            ['paciente_id' => $joao->id, 'data' => now()->subDays(8)->toDateString()],
            [
                'profissional_id' => $lucas->id,
                'queixa_principal' => 'Pós-operatório de reconstrução do LCA há 6 semanas.',
                'historia_clinica' => 'Realizou cirurgia artroscópica com sucesso.',
                'exame_fisico' => 'Edema residual, marcha com apoio parcial, ganho de amplitude em evolução.',
                'hipotese_clinica' => 'Reabilitação pós-operatória de LCA.',
                'objetivos' => 'Recuperar amplitude, fortalecer quadríceps e voltar à marcha independente.',
                'plano_terapeutico' => 'Crioterapia, mobilização, exercícios isométricos e propriocepção.',
                'status' => Avaliacao::FINALIZADA,
                'finalizada_em' => now()->subDays(8),
            ]
        );

        Avaliacao::firstOrCreate(
            ['paciente_id' => $ana->id, 'data' => now()->toDateString()],
            [
                'profissional_id' => $camila->id,
                'queixa_principal' => 'Dor no ombro direito ao elevar o braço.',
                'historia_clinica' => 'Início há 2 meses, sem trauma conhecido.',
                'exame_fisico' => 'Teste de Neer positivo, arco doloroso entre 60-120 graus.',
                'hipotese_clinica' => 'Síndrome do impacto subacromial.',
                'objetivos' => 'Reduzir dor, restaurar amplitude e fortalecer manguito rotador.',
                'plano_terapeutico' => 'A ser definido nas próximas sessões.',
                'status' => Avaliacao::RASCUNHO,
            ]
        );
    }

    private function semearAgendaEvolucoes(): void
    {
        $hoje = Carbon::today();

        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $joao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();
        $ana = Paciente::where('cpf', '433.552.671-15')->firstOrFail();
        $carlos = Paciente::where('cpf', '145.618.290-77')->firstOrFail();
        $fernanda = Paciente::where('cpf', '578.125.903-08')->firstOrFail();
        $rafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();

        $camila = Professional::where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();
        $lucas = Professional::where('nome', 'Dr. Lucas Reabilitação')->firstOrFail();

        $sala1 = Room::where('nome', 'Sala 1')->firstOrFail();
        $sala2 = Room::where('nome', 'Sala 2')->firstOrFail();
        $sala3 = Room::where('nome', 'Sala 3')->firstOrFail();

        $avaliacaoMaria = Avaliacao::where('paciente_id', $maria->id)->where('status', Avaliacao::FINALIZADA)->first();
        $avaliacaoJoao = Avaliacao::where('paciente_id', $joao->id)->where('status', Avaliacao::FINALIZADA)->first();

        // Hoje
        $this->agendar($maria, $camila, $hoje->copy()->setTime(8, 0), 50, 'confirmado', 'Sessão de fisioterapia - Lombalgia', $sala1);
        $this->agendar($joao, $lucas, $hoje->copy()->setTime(9, 0), 50, 'em_atendimento', 'Sessão de fisioterapia - Pós-LCA', $sala2);
        $this->agendar($ana, $camila, $hoje->copy()->setTime(10, 30), 50, 'agendado', 'Avaliação/Sessão - Ombro', $sala1);
        $this->agendar($fernanda, $camila, $hoje->copy()->setTime(14, 0), 50, 'confirmado', 'Fisioterapia - Cervicalgia', $sala3);

        // Amanhã
        $this->agendar($carlos, $lucas, $hoje->copy()->addDay()->setTime(8, 30), 50, 'agendado', 'Fisioterapia esportiva - Joelho', $sala2);
        $this->agendar($rafael, $camila, $hoje->copy()->addDay()->setTime(15, 0), 50, 'agendado', 'Reabilitação - Ombro', $sala1);

        // Passado
        $appt1 = $this->agendar($maria, $camila, $hoje->copy()->subDays(7)->setTime(8, 0), 50, 'atendido', 'Sessão de fisioterapia - Lombalgia', $sala1);
        $this->agendar($maria, $camila, $hoje->copy()->subDays(5)->setTime(8, 0), 50, 'atendido', 'Sessão de fisioterapia - Lombalgia', $sala1);
        $this->agendar($maria, $camila, $hoje->copy()->subDays(3)->setTime(8, 0), 50, 'atendido', 'Sessão de fisioterapia - Lombalgia', $sala1);
        $this->agendar($joao, $lucas, $hoje->copy()->subDays(6)->setTime(9, 0), 50, 'atendido', 'Sessão de fisioterapia - Pós-LCA', $sala2);
        $this->agendar($joao, $lucas, $hoje->copy()->subDays(4)->setTime(9, 0), 50, 'atendido', 'Sessão de fisioterapia - Pós-LCA', $sala2);

        $this->criarEvolucao($maria, $camila, $hoje->copy()->subDays(7)->setTime(8, 0), $avaliacaoMaria, 'Paciente relata melhora da dor em posição sentada. Realizados exercícios de estabilização lombar com boa tolerância. Orientado a manter postura correta no trabalho.');
        $this->criarEvolucao($maria, $camila, $hoje->copy()->subDays(5)->setTime(8, 0), $avaliacaoMaria, 'Redução da dor em relação à sessão anterior. Exercícios progressivos bem tolerados. Dor passou de 7/10 para 4/10.');
        $this->criarEvolucao($maria, $camila, $hoje->copy()->subDays(3)->setTime(8, 0), $avaliacaoMaria, 'Boa evolução. Paciente consegue ficar em pé por mais tempo sem dor. Fortalecimento de glúteos e estabilizadores. Dor 2/10.');
        $this->criarEvolucao($joao, $lucas, $hoje->copy()->subDays(6)->setTime(9, 0), $avaliacaoJoao, 'Amplitude de flexão de joelho 95 graus. Marcha com muletas, descarga parcial. Exercícios de extensão ativa com bom desempenho.');
        $this->criarEvolucao($joao, $lucas, $hoje->copy()->subDays(4)->setTime(9, 0), $avaliacaoJoao, 'Ganho de amplitude significativo. Flexão 110 graus. Iniciado treino de equilíbrio. Paciente motivado e colaborativo.');
    }

    private function agendar(Paciente $paciente, Professional $profissional, Carbon $inicio, int $duracao, string $status, string $titulo, ?Room $sala = null): ?Appointment
    {
        if (Appointment::where('paciente_id', $paciente->id)->where('inicio', $inicio)->exists()) {
            return null;
        }

        return Appointment::create([
            'clinica_id' => $this->clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'room_id' => $sala?->id,
            'appointment_status_id' => $this->status[$status],
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes($duracao),
            'titulo' => $titulo,
            'retorno' => false,
        ]);
    }

    private function criarEvolucao(Paciente $paciente, Professional $profissional, Carbon $inicio, ?Avaliacao $avaliacao, string $conteudo): void
    {
        $consulta = Appointment::where('paciente_id', $paciente->id)->where('inicio', $inicio)->first();

        if ($consulta === null) {
            return;
        }

        if (EvolucaoSessao::where('paciente_id', $paciente->id)->where('data', $inicio->toDateString())->where('conteudo', $conteudo)->exists()) {
            return;
        }

        EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'avaliacao_id' => $avaliacao?->id,
            'appointment_id' => $consulta->id,
            'data' => $inicio->toDateString(),
            'conteudo' => $conteudo,
            'tecnicas' => 'Terapia manual e exercícios terapêuticos',
            'resposta_paciente' => 'Boa tolerância, sem intercorrências',
            'dor_antes' => 5,
            'dor_depois' => 2,
            'status' => EvolucaoSessao::FINALIZADA,
            'finalizada_em' => $inicio->copy()->addMinutes(50),
        ]);

        AuditLogger::log('finalizar', 'evolucoes_sessao', $paciente->id, depois: ['data' => $inicio->toDateString()], user: $profissional->user_id ? User::find($profissional->user_id) : $this->admin);
    }

    private function semearPacotesESessoes(): void
    {
        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $joao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();

        $camila = Professional::where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();
        $lucas = Professional::where('nome', 'Dr. Lucas Reabilitação')->firstOrFail();

        $hoje = Carbon::today();

        $pacoteMaria = PacoteSessao::firstOrCreate(
            ['paciente_id' => $maria->id, 'nome' => 'Pacote Lombalgia - 10 sessões'],
            [
                'profissional_id' => $camila->id,
                'total_sessoes' => 10,
                'sessoes_usadas' => 3,
                'valor_sessao' => 150.00,
                'valor_total' => 1500.00,
                'status' => PacoteSessao::ATIVO,
                'validade_em' => $hoje->copy()->addMonths(3),
            ]
        );

        foreach ([7, 5, 3] as $dias) {
            $evolucao = EvolucaoSessao::where('paciente_id', $maria->id)->where('data', $hoje->copy()->subDays($dias)->toDateString())->first();
            if ($evolucao && ! ConsumoSessao::where('evolucao_sessao_id', $evolucao->id)->exists()) {
                ConsumoSessao::create([
                    'pacote_id' => $pacoteMaria->id,
                    'paciente_id' => $maria->id,
                    'profissional_id' => $camila->id,
                    'evolucao_sessao_id' => $evolucao->id,
                    'data' => $hoje->copy()->subDays($dias)->toDateString(),
                    'valor' => 150.00,
                    'observacoes' => 'Consumo automático do pacote',
                ]);
            }
        }

        $pacoteJoao = PacoteSessao::firstOrCreate(
            ['paciente_id' => $joao->id, 'nome' => 'Pacote Pós-LCA - 12 sessões'],
            [
                'profissional_id' => $lucas->id,
                'total_sessoes' => 12,
                'sessoes_usadas' => 2,
                'valor_sessao' => 160.00,
                'valor_total' => 1920.00,
                'status' => PacoteSessao::ATIVO,
                'validade_em' => $hoje->copy()->addMonths(4),
            ]
        );

        foreach ([6, 4] as $dias) {
            $evolucao = EvolucaoSessao::where('paciente_id', $joao->id)->where('data', $hoje->copy()->subDays($dias)->toDateString())->first();
            if ($evolucao && ! ConsumoSessao::where('evolucao_sessao_id', $evolucao->id)->exists()) {
                ConsumoSessao::create([
                    'pacote_id' => $pacoteJoao->id,
                    'paciente_id' => $joao->id,
                    'profissional_id' => $lucas->id,
                    'evolucao_sessao_id' => $evolucao->id,
                    'data' => $hoje->copy()->subDays($dias)->toDateString(),
                    'valor' => 160.00,
                    'observacoes' => 'Consumo automático do pacote',
                ]);
            }
        }
    }

    private function semearFinanceiro(): void
    {
        $pix = PaymentMethod::where('name', 'pix')->firstOrFail();
        $dinheiro = PaymentMethod::where('name', 'dinheiro')->firstOrFail();

        $pacoteMaria = PacoteSessao::where('paciente_id', Paciente::where('cpf', '390.533.447-05')->firstOrFail()->id)->firstOrFail();
        $pacoteJoao = PacoteSessao::where('paciente_id', Paciente::where('cpf', '128.473.556-40')->firstOrFail()->id)->firstOrFail();

        $pMaria = $pacoteMaria->paciente;
        $pJoao = $pacoteJoao->paciente;

        // Criar orçamentos fictícios para contas a receber
        $orcMaria = Budget::firstOrCreate(
            ['paciente_id' => $pMaria->id, 'titulo' => 'Orçamento — Pacote Lombalgia'],
            [
                'profissional_id' => $pacoteMaria->profissional_id,
                'validade' => now()->addDays(30),
                'desconto' => 0,
                'subtotal' => 1500.00,
                'total' => 1500.00,
                'status' => 'aprovado',
            ]
        );

        $contaMaria = AccountsReceivable::updateOrCreate(
            ['paciente_id' => $pMaria->id, 'budget_id' => $orcMaria->id],
            [
                'vencimento' => now()->addDays(15),
                'valor' => 1500.00,
                'valor_pago' => 750.00,
                'desconto' => 0,
                'status' => 'parcial',
                'observacoes' => 'Pagamento parcial do pacote',
            ]
        );
        $diaMaria = now()->subDays(5)->startOfDay();
        Payment::firstOrCreate(
            ['accounts_receivable_id' => $contaMaria->id, 'data' => $diaMaria, 'valor' => 750.00],
            ['payment_method_id' => $pix->id, 'user_id' => $this->userFinanceiro->id, 'observacoes' => 'Entrada do pacote de fisioterapia']
        );

        $orcJoao = Budget::firstOrCreate(
            ['paciente_id' => $pJoao->id, 'titulo' => 'Orçamento — Pacote Pós-LCA'],
            [
                'profissional_id' => $pacoteJoao->profissional_id,
                'validade' => now()->addDays(30),
                'desconto' => 0,
                'subtotal' => 1920.00,
                'total' => 1920.00,
                'status' => 'aprovado',
            ]
        );

        $contaJoao = AccountsReceivable::updateOrCreate(
            ['paciente_id' => $pJoao->id, 'budget_id' => $orcJoao->id],
            [
                'vencimento' => now()->addDays(10),
                'valor' => 1920.00,
                'valor_pago' => 1920.00,
                'desconto' => 0,
                'status' => 'pago',
                'observacoes' => null,
            ]
        );
        $diaJoao = now()->subDays(8)->startOfDay();
        Payment::firstOrCreate(
            ['accounts_receivable_id' => $contaJoao->id, 'data' => $diaJoao, 'valor' => 1920.00],
            ['payment_method_id' => $dinheiro->id, 'user_id' => $this->userFinanceiro->id, 'observacoes' => 'Pagamento integral do pacote']
        );
    }

    private function semearContratos(): void
    {
        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $joao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();

        $this->contrato($maria, 'Termo de consentimento para fisioterapia', 'assinado', 'Autorizo a realização de tratamento fisioterápico conforme avaliação e plano terapêutico apresentados, ciente dos objetivos e cuidados.', now()->subDays(10));
        $this->contrato($joao, 'Contrato de prestação de serviços - Pacote de sessões', 'assinado', 'Contrato para prestação de serviços de fisioterapia no pacote de 12 sessões, com valores e condições acordados.', now()->subDays(8));
    }

    private function contrato(Paciente $paciente, string $tipo, string $status, string $conteudo, ?Carbon $assinadoEm): void
    {
        Contract::firstOrCreate(
            ['paciente_id' => $paciente->id, 'tipo' => $tipo],
            [
                'user_id' => $this->userRecepcao->id,
                'conteudo' => $conteudo,
                'status' => $status,
                'assinado_em' => $assinadoEm,
            ]
        );

        AuditLogger::log('criar', 'contracts', $paciente->id, depois: ['tipo' => $tipo, 'status' => $status], user: $this->userRecepcao);
    }

    private function semearEstoque(): void
    {
        $fisioMed = Supplier::firstOrCreate(
            ['nome' => 'FisioMed Distribuidora'],
            ['documento' => '44.444.444/0001-44', 'email' => 'vendas@fisiomed.com.br', 'phone' => '(11) 3600-0000', 'contato' => 'Comercial', 'is_active' => true]
        );

        $descartaveis = ProductCategory::firstOrCreate(['name' => 'Descartáveis']);
        $orteses = ProductCategory::firstOrCreate(['name' => 'Órteses e Bandagens']);
        $eletroterapia = ProductCategory::firstOrCreate(['name' => 'Eletroterapia']);

        $this->produto($fisioMed, $descartaveis, 'Gaze estéril 7,5x7,5 cm', 'GAZ-F01', 'pacote', 30, 10, 4.00, 8.00, null, null);
        $this->produto($fisioMed, $orteses, 'Bandagem elástica 10cm', 'BAN-F02', 'rolo', 20, 5, 12.00, 22.00, null, null);
        $this->produto($fisioMed, $eletroterapia, 'Gel condutor para eletroterapia', 'GEL-F03', 'un', 15, 5, 25.00, 45.00, now()->addMonths(12), 'L2501');
    }

    private function produto(Supplier $fornecedor, ProductCategory $categoria, string $nome, string $codigo, string $unidade, int $estoque, int $estoqueMinimo, float $custo, float $preco, ?Carbon $validade, ?string $lote): void
    {
        $produto = Product::firstOrCreate(
            ['codigo' => $codigo],
            [
                'product_category_id' => $categoria->id,
                'supplier_id' => $fornecedor->id,
                'nome' => $nome,
                'unidade' => $unidade,
                'estoque' => $estoque,
                'estoque_minimo' => $estoqueMinimo,
                'custo' => $custo,
                'preco' => $preco,
                'validade' => $validade,
                'lote' => $lote,
                'controlar_estoque' => true,
                'is_active' => true,
            ]
        );

        if (StockMovement::where('product_id', $produto->id)->exists()) {
            return;
        }

        StockMovement::create([
            'product_id' => $produto->id,
            'user_id' => $this->userRecepcao->id,
            'tipo' => 'entrada',
            'quantidade' => $estoque,
            'saldo_anterior' => 0,
            'saldo_posterior' => $estoque,
            'custo' => $custo,
            'motivo' => 'Compra inicial - Demo',
        ]);
    }

    private function semearAuditoria(): void
    {
        $maria = Paciente::where('nome', 'Maria Oliveira')->firstOrFail();
        $joao = Paciente::where('nome', 'João Pereira')->firstOrFail();

        AuditLogger::log('criar', 'pacientes', $maria->id, depois: ['nome' => $maria->nome], user: $this->userRecepcao);
        AuditLogger::log('criar', 'pacientes', $joao->id, depois: ['nome' => $joao->nome], user: $this->userRecepcao);
        AuditLogger::log('finalizar', 'avaliacoes', $maria->id, depois: ['paciente' => $maria->nome], user: $this->userFisio);
    }
}
