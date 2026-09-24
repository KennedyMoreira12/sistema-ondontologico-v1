<?php

namespace Database\Seeders;

use App\Models\AccountsReceivable;
use App\Models\Anamnese;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Clinica;
use App\Models\ClinicalNote;
use App\Models\Contract;
use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use App\Models\MedicalAlert;
use App\Models\OdontogramTooth;
use App\Models\Paciente;
use App\Models\PatientInsurance;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Models\WaitingList;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private Clinica $clinica;

    private User $admin;

    private User $userRecepcao;

    private User $userFinanceiro;

    private User $userDentista;

    private array $status;

    public function run(): void
    {
        $this->clinica = Clinica::firstOrFail();
        $this->admin = User::where('email', 'admin@sco.com')->firstOrFail();

        $this->status = AppointmentStatus::pluck('id', 'name')->all();

        $this->semearUsuariosEDirigentes();
        $this->semearPacientes();
        $this->semearConvivencia();
        $this->semearClinico();
        $this->semearAgendaEClinicalNotes();
        $this->semearPlanosEOrcamentos();
        $this->semearFinanceiro();
        $this->semearContratos();
        $this->semearEstoque();
        $this->semearAuditoria();
    }

    private function semearUsuariosEDirigentes(): void
    {
        foreach (Room::get() as $sala) {
            $sala->update(['clinica_id' => $this->clinica->id]);
        }

        $roles = Role::whereIn('name', ['dentista', 'recepcao', 'financeiro'])->pluck('id', 'name');

        $this->userRecepcao = User::firstOrCreate(
            ['email' => 'recepcao@sco.com'],
            [
                'name' => 'Carla Recepção',
                'password' => Hash::make('recepcao123'),
                'email_verified_at' => now(),
                'role_id' => $roles['recepcao'],
                'clinica_id' => $this->clinica->id,
                'is_active' => true,
            ]
        );

        $this->userFinanceiro = User::firstOrCreate(
            ['email' => 'financeiro@sco.com'],
            [
                'name' => 'Marcos Financeiro',
                'password' => Hash::make('financeiro123'),
                'email_verified_at' => now(),
                'role_id' => $roles['financeiro'],
                'clinica_id' => $this->clinica->id,
                'is_active' => true,
            ]
        );

        $this->userDentista = User::firstOrCreate(
            ['email' => 'dentista@sco.com'],
            [
                'name' => 'Dr. Paulo Andrade',
                'password' => Hash::make('dentista123'),
                'email_verified_at' => now(),
                'role_id' => $roles['dentista'],
                'clinica_id' => $this->clinica->id,
                'is_active' => true,
            ]
        );

        $clnicaGeral = Specialty::where('name', 'Clínica geral')->firstOrFail();
        $endodontia = Specialty::where('name', 'Endodontia')->firstOrFail();
        $cirurgia = Specialty::where('name', 'Cirurgia')->firstOrFail();
        $implantes = Specialty::where('name', 'Implantes')->firstOrFail();
        $periodontia = Specialty::where('name', 'Periodontia')->firstOrFail();
        $ortodontia = Specialty::where('name', 'Ortodontia')->firstOrFail();

        $this->cadastrarProfissional(
            'Dra. Ana Souza',
            '300.415.278-90',
            'SP-25478',
            'dentista',
            25.00,
            [$endodontia, $periodontia]
        );

        $this->cadastrarProfissional(
            'Dr. Carlos Mendes',
            '288.105.293-44',
            'SP-30156',
            'dentista',
            30.00,
            [$cirurgia, $implantes]
        );

        $this->cadastrarProfissional(
            'Dr. Paulo Andrade',
            '120.875.641-39',
            'SP-48123',
            'dentista',
            20.00,
            [$clnicaGeral, $ortodontia],
            $this->userDentista
        );
    }

    private function cadastrarProfissional(
        string $nome,
        string $cpf,
        string $cro,
        string $tipo,
        float $comissao,
        array $especialidades,
        ?User $user = null
    ): Professional {
        $profissional = Professional::firstOrCreate(
            ['nome' => $nome],
            [
                'clinica_id' => $this->clinica->id,
                'user_id' => $user?->id,
                'cpf' => $cpf,
                'cro' => $cro,
                'tipo' => $tipo,
                'comissao' => $comissao,
                'is_active' => true,
            ]
        );

        $profissional->specialties()->syncWithoutDetaching(
            collect($especialidades)->pluck('id')->all()
        );

        return $profissional;
    }

    private function semearPacientes(): void
    {
        $dados = [
            ['Maria Oliveira', '390.533.447-05', '1985-03-12', 'F', 'maria.oliveira@email.com', '(11) 98765-4321', 'Julia', 'Bela Vista', 'São Paulo', 'SP'],
            ['João Pereira', '128.473.556-40', '1978-08-25', 'M', 'joao.pereira@email.com', '(11) 97654-3210', 'Mário', 'Centro', 'São Paulo', 'SP'],
            ['Ana Clara Santos', '433.552.671-15', '1992-01-19', 'F', 'anaclara.santos@email.com', '(11) 96543-2109', 'Rosa', 'Tatuapé', 'São Paulo', 'SP'],
            ['Carlos Eduardo Lima', '145.618.290-77', '1980-11-03', 'M', 'carlos.lima@email.com', '(11) 95432-1098', 'José', 'Moema', 'São Paulo', 'SP'],
            ['Fernanda Costa', '578.125.903-08', '1995-06-27', 'F', 'fernanda.costa@email.com', '(11) 94321-0987', 'Paulo', 'Campinas', 'Campinas', 'SP'],
            ['Rafael Mendes', '876.341.512-30', '1969-12-08', 'M', 'rafael.mendes@email.com', '(11) 93210-9876', 'Antônio', 'Santana', 'Guarulhos', 'SP'],
            ['Juliana Almeida', '210.945.368-92', '1988-04-15', 'F', 'juliana.almeida@email.com', '(11) 92109-8765', 'Sandra', 'Pinheiros', 'São Paulo', 'SP'],
            ['Bruno Carvalho', '377.882.440-61', '1990-09-02', 'M', 'bruno.carvalho@email.com', '(11) 91098-7654', 'Luiz', 'Barra Funda', 'São Paulo', 'SP'],
            ['Patrícia Gomes', '592.014.873-26', '1975-02-22', 'F', 'patricia.gomes@email.com', '(11) 90987-6543', 'Cláudio', 'Vila Mariana', 'São Paulo', 'SP'],
            ['Lucas Ferreira', '603.842.190-51', '2000-07-30', 'M', 'lucas.ferreira@email.com', '(11) 89876-5432', 'Renata', 'Osasco', 'Osasco', 'SP'],
            ['Beatriz Rocha', '441.770.235-88', '1986-10-11', 'F', 'beatriz.rocha@email.com', '(11) 88765-4321', 'Fábio', 'Jabaquara', 'São Paulo', 'SP'],
            ['Thiago Nascimento', '319.005.764-73', '1993-05-04', 'M', 'thiago.nascimento@email.com', '(11) 87654-3210', 'Eliane', 'Lapa', 'São Paulo', 'SP'],
        ];

        foreach ($dados as [$nome, $cpf, $nascimento, $sexo, $email, $celular, $responsavel, $bairro, $cidade, $estado]) {
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
                    'endereco' => 'Rua '.$nome,
                    'numero' => (string) rand(10, 999),
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'estado' => $estado,
                    'responsavel' => $responsavel,
                    'status' => 'ativo',
                ]
            );
        }
    }

    private function semearConvivencia(): void
    {
        $plenaSaude = InsuranceCompany::firstOrCreate(
            ['nome' => 'PlenaSaúde'],
            ['cnpj' => '22.222.222/0001-22', 'phone' => '(11) 3000-0000', 'responsavel' => 'Comercial PlenaSaúde', 'is_active' => true]
        );
        $planoCompleto = InsurancePlan::firstOrCreate(
            ['insurance_company_id' => $plenaSaude->id, 'nome' => 'Plano Completo'],
            ['registro' => 'ANS-0002', 'tipo' => 'ambulatorial', 'is_active' => true]
        );

        $essencial = InsurancePlan::where('nome', 'Plano Essencial')->firstOrFail();
        $completo = InsurancePlan::where('nome', 'Plano Completo')->firstOrFail();

        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $carlos = Paciente::where('cpf', '145.618.290-77')->firstOrFail();
        $patricia = Paciente::where('cpf', '592.014.873-26')->firstOrFail();

        PatientInsurance::firstOrCreate(
            ['paciente_id' => $maria->id, 'insurance_plan_id' => $essencial->id],
            ['numero_carteirinha' => 'ODS'.rand(10000, 99999), 'validade' => now()->addMonths(18), 'is_active' => true]
        );
        PatientInsurance::firstOrCreate(
            ['paciente_id' => $carlos->id, 'insurance_plan_id' => $completo->id],
            ['numero_carteirinha' => 'PLS'.rand(10000, 99999), 'validade' => now()->addYears(2), 'is_active' => true]
        );
        PatientInsurance::firstOrCreate(
            ['paciente_id' => $patricia->id, 'insurance_plan_id' => $completo->id],
            ['numero_carteirinha' => 'PLS'.rand(10000, 99999), 'validade' => now()->addYears(1), 'is_active' => true]
        );
    }

    private function semearClinico(): void
    {
        $maria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $rafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();
        $anaClara = Paciente::where('cpf', '433.552.671-15')->firstOrFail();

        $profAna = Professional::where('nome', 'Dra. Ana Souza')->firstOrFail();
        $profCarlos = Professional::where('nome', 'Dr. Carlos Mendes')->firstOrFail();
        $profJoao = Professional::where('nome', 'Dr. João Silva')->firstOrFail();

        Anamnese::firstOrCreate(
            ['paciente_id' => $maria->id, 'titulo' => 'Anamnese inicial'],
            [
                'profissional_id' => $profJoao->id,
                'respostas' => [
                    'Alergia a medicamentos' => 'Sensibilidade a anestésicos locais',
                    'Doença pré-existente' => 'Nenhuma',
                    'Faz uso contínuo de' => 'Nenhum',
                ],
                'responsavel' => 'Maria Oliveira',
            ]
        );

        Anamnese::firstOrCreate(
            ['paciente_id' => $rafael->id, 'titulo' => 'Anamnese inicial'],
            [
                'profissional_id' => $profCarlos->id,
                'respostas' => [
                    'Alergia a medicamentos' => 'Penicilina',
                    'Doença pré-existente' => 'Hipertensão controlada',
                    'Faz uso contínuo de' => 'Losartana 50mg',
                ],
                'responsavel' => 'Rafael Mendes',
            ]
        );

        Anamnese::firstOrCreate(
            ['paciente_id' => $anaClara->id, 'titulo' => 'Anamnese inicial'],
            [
                'profissional_id' => $profCarlos->id,
                'respostas' => [
                    'Alergia a medicamentos' => 'Látex',
                    'Doença pré-existente' => 'Nenhuma',
                    'Faz uso contínuo de' => 'Nenhum',
                ],
                'responsavel' => 'Ana Clara Santos',
            ]
        );

        MedicalAlert::firstOrCreate(
            ['paciente_id' => $maria->id, 'tipo' => 'alergia', 'descricao' => 'Sensibilidade a anestésicos locais', 'is_active' => true],
        );

        MedicalAlert::firstOrCreate(
            ['paciente_id' => $rafael->id, 'tipo' => 'alergia', 'descricao' => 'Alergia a penicilina', 'is_active' => true],
        );

        MedicalAlert::firstOrCreate(
            ['paciente_id' => $rafael->id, 'tipo' => 'medicacao', 'descricao' => 'Uso contínuo de Losartana (hipertensão)', 'is_active' => true],
        );

        MedicalAlert::firstOrCreate(
            ['paciente_id' => $anaClara->id, 'tipo' => 'alergia', 'descricao' => 'Alergia a látex', 'is_active' => true],
        );

        $coroa = ToothCondition::where('codigo', 'coroa')->firstOrFail();
        $restaurado = ToothCondition::where('codigo', 'restaurado')->firstOrFail();
        $cariado = ToothCondition::where('codigo', 'cariado')->firstOrFail();
        $canal = ToothCondition::where('codigo', 'canal')->firstOrFail();
        $ausente = ToothCondition::where('codigo', 'ausente')->firstOrFail();

        $this->marcarDente($maria, $profJoao, [[11, $coroa], [12, $restaurado], [21, $restaurado], [26, $cariado]]);
        $this->marcarDente($rafael, $profCarlos, [[36, $ausente], [35, $cariado], [46, $restaurado]]);
        $this->marcarDente($anaClara, $profCarlos, [[26, $canal], [27, $cariado]]);

        $this->semearPrescricoes();
    }

    private function marcarDente(Paciente $paciente, Professional $profissional, array $dentes): void
    {
        $odontogram = $paciente->odontogramas()->firstOrCreate(
            ['status' => 'ativo'],
            ['titulo' => 'Odontograma principal']
        );

        foreach ($dentes as [$numero, $condicao]) {
            OdontogramTooth::firstOrCreate(
                ['odontogram_id' => $odontogram->id, 'dente' => $numero],
                [
                    'tooth_condition_id' => $condicao->id,
                    'condicao' => $condicao->name,
                    'profissional_id' => $profissional->id,
                    'observacao' => 'Registrado no cadastro inicial',
                ]
            );
        }
    }

    private function semearPrescricoes(): void
    {
        $anaClara = Paciente::where('cpf', '433.552.671-15')->firstOrFail();
        $rafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();
        $profCarlos = Professional::where('nome', 'Dr. Carlos Mendes')->firstOrFail();
        $profJoao = Professional::where('nome', 'Dr. João Silva')->firstOrFail();

        $prescricao = Prescription::firstOrCreate(
            ['paciente_id' => $anaClara->id, 'data' => now()->subDays(5)->toDateString()],
            ['profissional_id' => $profCarlos->id, 'observacoes' => 'Pós-operatório de tratamento endodôntico.']
        );
        PrescriptionItem::firstOrCreate(
            ['prescription_id' => $prescricao->id, 'medicamento' => 'Amoxicilina 500mg'],
            ['dose' => '1 cápsula', 'frequencia' => '8/8h', 'duracao' => '7 dias']
        );
        PrescriptionItem::firstOrCreate(
            ['prescription_id' => $prescricao->id, 'medicamento' => 'Nimesulida 100mg'],
            ['dose' => '1 comprimido', 'frequencia' => '12/12h', 'duracao' => '5 dias']
        );
        PrescriptionItem::firstOrCreate(
            ['prescription_id' => $prescricao->id, 'medicamento' => 'Dipirona 500mg se dor'],
            ['dose' => '1 comprimido', 'frequencia' => '6/6h se necessário', 'duracao' => '3 dias']
        );

        $prescricao2 = Prescription::firstOrCreate(
            ['paciente_id' => $rafael->id, 'data' => now()->subDays(12)->toDateString()],
            ['profissional_id' => $profJoao->id, 'observacoes' => 'Analgésico pós-exodontia.']
        );
        PrescriptionItem::firstOrCreate(
            ['prescription_id' => $prescricao2->id, 'medicamento' => 'Ibuprofeno 600mg'],
            ['dose' => '1 comprimido', 'frequencia' => '8/8h', 'duracao' => '5 dias']
        );
        PrescriptionItem::firstOrCreate(
            ['prescription_id' => $prescricao2->id, 'medicamento' => 'Amoxicilina 875mg'],
            ['dose' => '1 comprimido', 'frequencia' => '12/12h', 'duracao' => '7 dias']
        );
    }

    private function semearAgendaEClinicalNotes(): void
    {
        $hoje = Carbon::today();

        $pMaria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $pJoao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();
        $pAna = Paciente::where('cpf', '433.552.671-15')->firstOrFail();
        $pCarlos = Paciente::where('cpf', '145.618.290-77')->firstOrFail();
        $pFernanda = Paciente::where('cpf', '578.125.903-08')->firstOrFail();
        $pRafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();
        $pJuliana = Paciente::where('cpf', '210.945.368-92')->firstOrFail();
        $pBruno = Paciente::where('cpf', '377.882.440-61')->firstOrFail();
        $pPatricia = Paciente::where('cpf', '592.014.873-26')->firstOrFail();
        $pLucas = Paciente::where('cpf', '603.842.190-51')->firstOrFail();
        $pBeatriz = Paciente::where('cpf', '441.770.235-88')->firstOrFail();
        $pThiago = Paciente::where('cpf', '319.005.764-73')->firstOrFail();

        $profJoao = Professional::where('nome', 'Dr. João Silva')->firstOrFail();
        $profAna = Professional::where('nome', 'Dra. Ana Souza')->firstOrFail();
        $profCarlos = Professional::where('nome', 'Dr. Carlos Mendes')->firstOrFail();

        $sala1 = Room::where('nome', 'Sala 1')->firstOrFail();
        $sala2 = Room::where('nome', 'Sala 2')->firstOrFail();
        $sala3 = Room::where('nome', 'Sala 3')->firstOrFail();

        // Hoje
        $this->agendar($pMaria, $profAna, $hoje->copy()->setTime(8, 0), 60, 'confirmado', 'Limpeza', $sala1);
        $this->agendar($pJoao, $profJoao, $hoje->copy()->setTime(9, 0), 30, 'em_atendimento', 'Consulta clínica geral', $sala2);
        $this->agendar($pFernanda, $profJoao, $hoje->copy()->setTime(10, 0), 30, 'agendado', 'Avaliação inicial', $sala2);
        $this->agendar($pAna, $profCarlos, $hoje->copy()->setTime(14, 0), 90, 'confirmado', 'Tratamento de canal', $sala3);
        $this->agendar($pRafael, $profJoao, $hoje->copy()->setTime(15, 30), 30, 'atendido', 'Retorno pós-operatório', $sala1);
        $this->agendar($pBeatriz, $profAna, $hoje->copy()->setTime(16, 30), 60, 'agendado', 'Profilaxia e limpeza', $sala1);

        // Amanhã
        $this->agendar($pLucas, $profAna, $hoje->copy()->addDay()->setTime(8, 30), 60, 'confirmado', 'Clareamento dental', $sala2);
        $this->agendar($pThiago, $profCarlos, $hoje->copy()->addDay()->setTime(10, 0), 90, 'agendado', 'Tratamento de canal', $sala3);
        $this->agendar($pPatricia, $profJoao, $hoje->copy()->addDay()->setTime(14, 30), 30, 'confirmado', 'Consulta clínica geral', $sala1);

        // Próxima semana
        $this->agendar($pBruno, $profJoao, $hoje->copy()->addDays(4)->setTime(9, 0), 60, 'agendado', 'Limpeza', $sala1);
        $this->agendar($pPatricia, $profAna, $hoje->copy()->addDays(4)->setTime(11, 0), 45, 'agendado', 'Restauração em resina', $sala2);
        $this->agendar($pMaria, $profCarlos, $hoje->copy()->addDays(4)->setTime(15, 0), 30, 'confirmado', 'Retorno de avaliação', $sala3);
        $this->agendar($pJuliana, $profAna, $hoje->copy()->addDays(5)->setTime(10, 30), 45, 'agendado', 'Clareamento dental', $sala2);
        $this->agendar($pCarlos, $profJoao, $hoje->copy()->addDays(5)->setTime(13, 30), 30, 'agendado', 'Consulta clínica geral', $sala1);

        // Semana passada
        $this->agendar($pMaria, $profAna, $hoje->copy()->subDays(8)->setTime(9, 0), 60, 'atendido', 'Limpeza', $sala1);
        $this->agendar($pAna, $profCarlos, $hoje->copy()->subDays(3)->setTime(14, 0), 90, 'atendido', 'Tratamento de canal (início)', $sala3);
        $this->agendar($pJoao, $profJoao, $hoje->copy()->subDays(3)->setTime(10, 0), 30, 'faltou', 'Consulta clínica geral', $sala2);
        $this->agendar($pRafael, $profJoao, $hoje->copy()->subDays(13)->setTime(9, 30), 60, 'cancelado', 'Extração planejada', $sala1);

        // Evoluções clínicas em consultas passadas
        $this->evolucao($pMaria, $profAna, $hoje->copy()->subDays(8)->setTime(9, 0), 'evolucao', 'Paciente realizou profilaxia e raspagem supragengival. Sem intercorrências. Orientada sobre técnica de escovação e uso de fio dental.');
        $this->evolucao($pAna, $profCarlos, $hoje->copy()->subDays(3)->setTime(14, 0), 'evolucao', 'Abertura coronária do dente 26 realizada. Instrumentação parcial dos canais. Medicação intracanal. Reagendado para conclusão.');
        $this->evolucao($pRafael, $profJoao, $hoje->copy()->setTime(15, 30), 'evolucao', 'Retorno pós-operatório de extração do dente 36. Cicatrização satisfatória, sem sinais de infecção. Manter higiene no local.');

        // Lista de espera
        WaitingList::firstOrCreate(
            ['paciente_id' => $pBeatriz->id, 'motivo' => 'Aguardando vaga para clareamento dental'],
            ['prioridade' => 1, 'periodo_inicio' => $hoje, 'status' => 'aguardando']
        );
        WaitingList::firstOrCreate(
            ['paciente_id' => $pThiago->id, 'motivo' => 'Aguardando início de tratamento de canal'],
            ['prioridade' => 2, 'periodo_inicio' => $hoje, 'periodo_fim' => $hoje->copy()->addDays(14), 'status' => 'aguardando']
        );
    }

    private function agendar(
        Paciente $paciente,
        Professional $profissional,
        Carbon $inicio,
        int $duracao,
        string $status,
        string $titulo,
        ?Room $sala = null,
    ): void {
        if (Appointment::where('paciente_id', $paciente->id)->where('inicio', $inicio)->exists()) {
            return;
        }

        Appointment::create([
            'clinica_id' => $this->clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'room_id' => $sala?->id,
            'appointment_status_id' => $this->status[$status],
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes($duracao),
            'titulo' => $titulo,
            'retorno' => str($titulo)->startsWith('Retorno'),
        ]);
    }

    private function evolucao(
        Paciente $paciente,
        Professional $profissional,
        Carbon $inicio,
        string $tipo,
        string $conteudo
    ): void {
        $consulta = Appointment::where('paciente_id', $paciente->id)->where('inicio', $inicio)->first();

        if ($consulta === null) {
            return;
        }

        ClinicalNote::firstOrCreate(
            ['paciente_id' => $paciente->id, 'appointment_id' => $consulta->id],
            [
                'profissional_id' => $profissional->id,
                'tipo' => $tipo,
                'conteudo' => $conteudo,
            ]
        );
    }

    private function semearPlanosEOrcamentos(): void
    {
        $profJoao = Professional::where('nome', 'Dr. João Silva')->firstOrFail();
        $profAna = Professional::where('nome', 'Dra. Ana Souza')->firstOrFail();
        $profCarlos = Professional::where('nome', 'Dr. Carlos Mendes')->firstOrFail();

        $procedLimpeza = Procedure::where('codigo', 'LIMPO')->firstOrFail();
        $procedConsulta = Procedure::where('codigo', 'CLIN_GER')->firstOrFail();
        $procedRadio = Procedure::where('codigo', 'RAD_PG')->firstOrFail();
        $procedCanal = Procedure::where('codigo', 'END_FR_A')->firstOrFail();
        $procedResina = Procedure::where('codigo', 'RES_RES')->firstOrFail();
        $procedExtrai = Procedure::where('codigo', 'EXT_INC')->firstOrFail();
        $procedCoroa = Procedure::where('codigo', 'PROT_COR')->firstOrFail();
        $procedClareamento = Procedure::where('codigo', 'PT_BT_M')->firstOrFail();

        $pMaria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $pJoao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();
        $pAna = Paciente::where('cpf', '433.552.671-15')->firstOrFail();
        $pFernanda = Paciente::where('cpf', '578.125.903-08')->firstOrFail();
        $pRafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();
        $pLucas = Paciente::where('cpf', '603.842.190-51')->firstOrFail();

        // Plano Maria — reabilitação estética
        $planoMaria = $this->plano($pMaria, $profJoao, 'Reabilitação estética anterior', 'Recuperação estética dos dentes anteriores com coroas e clareamento.', 'em_andamento', 50.00);
        $this->item($planoMaria, $procedCoroa, 11, 1, 1200.00, 0);
        $this->item($planoMaria, $procedClareamento, null, 1, 400.00, 0);

        // Plano Ana — endodontia
        $planoAna = $this->plano($pAna, $profCarlos, 'Tratamento endodôntico do dente 26', 'Terapia de canal no elemento 26.', 'aprovado', 0);
        $this->item($planoAna, $procedCanal, 26, 1, 600.00, 0);

        // Plano João — prevenção (concluído)
        $planoJoao = $this->plano($pJoao, $profJoao, 'Acompanhamento preventivo', 'Limpeza e orientação de higiene.', 'concluido', 0);
        $itemJoao = $this->item($planoJoao, $procedLimpeza, null, 1, 150.00, 0);
        $itemJoao->update(['status' => 'executado']);

        // Plano Fernanda — avaliação (rascunho)
        $planoFernanda = $this->plano($pFernanda, $profJoao, 'Avaliação e planejamento', 'Avaliação inicial com documentação radiográfica.', 'rascunho', 0);
        $this->item($planoFernanda, $procedConsulta, null, 1, 100.00, 0);
        $this->item($planoFernanda, $procedRadio, null, 1, 80.00, 0);

        // Plano Rafael — extração + prótese
        $planoRafael = $this->plano($pRafael, $profCarlos, 'Extração e reabilitação com coroa', 'Remoção do dente 36 e futura coroa sobre implante.', 'aprovado', 50.00);
        $this->item($planoRafael, $procedExtrai, 36, 1, 250.00, 0);
        $this->item($planoRafael, $procedCoroa, 36, 1, 1200.00, 0);

        // Orçamentos
        $orcMaria = $this->orcamento($pMaria, $planoMaria, $profJoao, 'Orçamento — reabilitação estética', 'aprovado', now()->addDays(30), 50.00);
        $this->itemOrcamento($orcMaria, $procedCoroa, 11, 'Coroas de porcelana (2 dentes)', 2, 1200.00, 0);
        $this->itemOrcamento($orcMaria, $procedClareamento, null, 'Clareamento dental em consultório', 1, 400.00, 0);

        $orcAna = $this->orcamento($pAna, $planoAna, $profCarlos, 'Orçamento — tratamento de canal', 'aprovado', now()->addDays(15), 0);
        $this->itemOrcamento($orcAna, $procedCanal, 26, 'Tratamento de canal (1 raiz)', 1, 600.00, 0);

        $orcJoao = $this->orcamento($pJoao, $planoJoao, $profJoao, 'Orçamento — limpeza e consulta', 'aprovado', now()->addDays(10), 0);
        $this->itemOrcamento($orcJoao, $procedLimpeza, null, 'Limpeza completa', 1, 150.00, 0);

        $orcFernanda = $this->orcamento($pFernanda, $planoFernanda, $profJoao, 'Orçamento — avaliação inicial', 'emitido', now()->addDays(7), 0);
        $this->itemOrcamento($orcFernanda, $procedConsulta, null, 'Consulta clínica geral', 1, 100.00, 0);
        $this->itemOrcamento($orcFernanda, $procedRadio, null, 'Radiografia panorâmica', 1, 80.00, 0);

        $orcRafael = $this->orcamento($pRafael, $planoRafael, $profCarlos, 'Orçamento — cirurgia e prótese', 'emitido', now()->addDays(20), 50.00);
        $this->itemOrcamento($orcRafael, $procedExtrai, 36, 'Extração do dente 36', 1, 250.00, 0);
        $this->itemOrcamento($orcRafael, $procedCoroa, 36, 'Coroa de porcelana', 1, 1200.00, 0);

        $orcLucas = $this->orcamento($pLucas, null, $profAna, 'Orçamento — clareamento', 'recusado', now()->addDays(5), 0);
        $this->itemOrcamento($orcLucas, $procedClareamento, null, 'Clareamento dental a laser', 2, 400.00, 0);
    }

    private function plano(
        Paciente $paciente,
        Professional $profissional,
        string $titulo,
        string $descricao,
        string $status,
        float $desconto
    ): TreatmentPlan {
        $plano = TreatmentPlan::firstOrCreate(
            ['paciente_id' => $paciente->id, 'titulo' => $titulo],
            [
                'profissional_id' => $profissional->id,
                'descricao' => $descricao,
                'status' => $status,
                'desconto' => $desconto,
                'total' => 0,
            ]
        );

        AuditLogger::log('criar_plano', 'treatment_plans', $plano->id, depois: ['titulo' => $titulo, 'status' => $status], user: $this->admin);

        return $plano;
    }

    private function item(
        TreatmentPlan $plano,
        Procedure $procedimento,
        ?int $dente,
        int $quantidade,
        float $valorUnitario,
        float $desconto,
        string $status = 'planejado'
    ): TreatmentPlanItem {
        $valorTotal = max(round(($valorUnitario * $quantidade) - $desconto, 2), 0);

        $item = TreatmentPlanItem::firstOrCreate(
            ['treatment_plan_id' => $plano->id, 'procedure_id' => $procedimento->id, 'dente' => $dente],
            [
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'desconto' => $desconto,
                'valor_total' => $valorTotal,
                'status' => $status,
            ]
        );

        $this->recalcular($plano);

        return $item;
    }

    private function orcamento(
        Paciente $paciente,
        ?TreatmentPlan $plano,
        Professional $profissional,
        string $titulo,
        string $status,
        ?Carbon $validade,
        float $desconto
    ): Budget {
        $orcamento = Budget::firstOrCreate(
            ['paciente_id' => $paciente->id, 'titulo' => $titulo],
            [
                'treatment_plan_id' => $plano?->id,
                'profissional_id' => $profissional->id,
                'validade' => $validade,
                'desconto' => $desconto,
                'subtotal' => 0,
                'total' => 0,
                'status' => $status,
            ]
        );

        AuditLogger::log('criar_orcamento', 'budgets', $orcamento->id, depois: ['titulo' => $titulo, 'status' => $status], user: $this->admin);

        return $orcamento;
    }

    private function itemOrcamento(
        Budget $orcamento,
        Procedure $procedimento,
        ?int $dente,
        string $descricao,
        int $quantidade,
        float $valorUnitario,
        float $desconto
    ): BudgetItem {
        $valorTotal = max(round(($valorUnitario * $quantidade) - $desconto, 2), 0);

        $item = BudgetItem::firstOrCreate(
            ['budget_id' => $orcamento->id, 'procedure_id' => $procedimento->id, 'dente' => $dente, 'descricao' => $descricao],
            [
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'desconto' => $desconto,
                'valor_total' => $valorTotal,
            ]
        );

        $subtotal = (float) $orcamento->items()->sum('valor_total');
        $total = max(round($subtotal - (float) $orcamento->desconto, 2), 0);
        $orcamento->update(['subtotal' => $subtotal, 'total' => $total]);

        return $item;
    }

    private function recalcular(TreatmentPlan $plano): void
    {
        $somaItens = (float) $plano->items()->sum('valor_total');
        $total = max(round($somaItens - (float) $plano->desconto, 2), 0);

        $plano->update(['total' => $total]);
    }

    private function semearFinanceiro(): void
    {
        $pix = PaymentMethod::where('name', 'pix')->firstOrFail();
        $dinheiro = PaymentMethod::where('name', 'dinheiro')->firstOrFail();

        $orcMaria = Budget::where('titulo', 'Orçamento — reabilitação estética')->firstOrFail();
        $orcAna = Budget::where('titulo', 'Orçamento — tratamento de canal')->firstOrFail();
        $orcJoao = Budget::where('titulo', 'Orçamento — limpeza e consulta')->firstOrFail();

        $pMaria = $orcMaria->paciente;
        $pAna = $orcAna->paciente;
        $pJoao = $orcJoao->paciente;

        // Conta Maria — parcial, com pagamento via PIX
        $contaMaria = $this->conta($pMaria, $orcMaria, 2750.00, 600.00, 'parcial', now()->addDays(15));
        $this->pagamento($contaMaria, $pix, $this->userFinanceiro, now()->subDays(2), 600.00, 'Entrada de 50% do tratamento estético');

        // Conta Ana — aberta
        $this->conta($pAna, $orcAna, 600.00, 0, 'aberta', now()->addDays(10));

        // Conta João — paga
        $contaJoao = $this->conta($pJoao, $orcJoao, 150.00, 150.00, 'pago', now()->subDays(5));
        $this->pagamento($contaJoao, $dinheiro, $this->userFinanceiro, now()->subDays(4), 150.00, 'Pagamento integral à vista');
    }

    private function conta(
        Paciente $paciente,
        Budget $orcamento,
        float $valor,
        float $valorPago,
        string $status,
        ?Carbon $vencimento
    ): AccountsReceivable {
        return AccountsReceivable::updateOrCreate(
            ['paciente_id' => $paciente->id, 'budget_id' => $orcamento->id],
            [
                'vencimento' => $vencimento,
                'valor' => $valor,
                'valor_pago' => $valorPago,
                'desconto' => 0,
                'status' => $status,
                'observacoes' => $status === 'parcial' ? 'Pagamento parcial registrado.' : null,
            ]
        );
    }

    private function pagamento(
        AccountsReceivable $conta,
        PaymentMethod $metodo,
        User $usuario,
        Carbon $data,
        float $valor,
        string $observacoes
    ): void {
        Payment::firstOrCreate(
            ['accounts_receivable_id' => $conta->id, 'data' => $data->toDateString(), 'valor' => $valor],
            [
                'payment_method_id' => $metodo->id,
                'user_id' => $usuario->id,
                'observacoes' => $observacoes,
            ]
        );

        AuditLogger::log('registrar_pagamento', 'payments', $conta->id, depois: ['valor' => $valor], user: $usuario);
    }

    private function semearContratos(): void
    {
        $pMaria = Paciente::where('cpf', '390.533.447-05')->firstOrFail();
        $pJoao = Paciente::where('cpf', '128.473.556-40')->firstOrFail();
        $pAna = Paciente::where('cpf', '433.552.671-15')->firstOrFail();
        $pRafael = Paciente::where('cpf', '876.341.512-30')->firstOrFail();
        $pLucas = Paciente::where('cpf', '603.842.190-51')->firstOrFail();

        $this->contrato($pMaria, 'Termo de consentimento para tratamento estético', 'assinado', 'Eu, Maria Oliveira, autorizo a realização de procedimentos estéticos dentários conforme plano apresentado, incluindo coroas de porcelana e clareamento dental.', now()->subDays(3));
        $this->contrato($pJoao, 'Contrato de prestação de serviços odontológicos', 'pendente', 'Contrato para acompanhamento preventivo e consultas periódicas, com combinado de valores conforme tabela vigente.', null);
        $this->contrato($pAna, 'Termo de consentimento para tratamento endodôntico', 'assinado', 'Autorização para realização de tratamento de canal no dente 26, incluindo anestesia e medicação intracanal.', now()->subDays(5));
        $this->contrato($pRafael, 'Termo de consentimento cirúrgico', 'assinado', 'Consentimento para extração do dente 36 e futura reabilitação protética, ciente dos riscos e cuidados pós-operatórios.', now()->subDays(12));
        $this->contrato($pLucas, 'Acordo de valores para clareamento', 'cancelado', 'Proposta de clareamento em duas sessões. Recusada pelo paciente após apresentação.', null);
    }

    private function contrato(
        Paciente $paciente,
        string $tipo,
        string $status,
        string $conteudo,
        ?Carbon $assinadoEm
    ): void {
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
        $dentalTech = Supplier::firstOrCreate(
            ['nome' => 'DentalTech Distribuidora'],
            ['documento' => '12.345.678/0001-90', 'email' => 'vendas@dentaltech.com.br', 'phone' => '(11) 3500-0000', 'contato' => 'Comercial', 'is_active' => true]
        );
        $maxdent = Supplier::firstOrCreate(
            ['nome' => 'MaxDent Comércio LTDA'],
            ['documento' => '98.765.432/0001-10', 'email' => 'contato@maxdent.com.br', 'phone' => '(11) 3200-0000', 'contato' => 'Silvia', 'is_active' => true]
        );

        $descartaveis = ProductCategory::where('name', 'Descartáveis')->firstOrFail();
        $restauradores = ProductCategory::where('name', 'Materiais restauradores')->firstOrFail();
        $anestesicos = ProductCategory::where('name', 'Anestésicos')->firstOrFail();
        $cimentos = ProductCategory::where('name', 'Cimentos')->firstOrFail();

        $this->produto($dentalTech, $descartaveis, 'Luva de procedimento P', 'LUV-01', 'cx', 200, 100, 18.00, 30.00, null, 'L2401');
        $this->produto($dentalTech, $descartaveis, 'Máscara descartável tripla', 'MAS-02', 'cx', 40, 50, 12.00, 22.00, null, 'L2402', 90);
        $this->produto($maxdent, $restauradores, 'Resina fotopolimerizável A2', 'RES-03', 'un', 12, 5, 85.00, 145.00, now()->addMonths(6), 'L2389');
        $this->produto($maxdent, $anestesicos, 'Anestésico Lidocaína 2%', 'ANE-04', 'cx', 30, 10, 9.00, 16.00, now()->addMonths(18), 'L2390');
        $this->produto($dentalTech, $descartaveis, 'Seringa carpule descartável', 'CIP-05', 'cx', 120, 40, 6.00, 11.00, null, null);
        $this->produto($maxdent, $cimentos, 'Ionômero de vidro', 'ION-06', 'un', 8, 4, 40.00, 70.00, now()->addMonths(9), 'L2377');
        $this->produto($maxdent, $cimentos, 'Cimento temporário provante', 'IND-07', 'un', 6, 5, 28.00, 52.00, now()->addMonths(9), 'L2377');
        $this->produto($dentalTech, $descartaveis, 'Gaze estéril 7,5x7,5 cm', 'GAZ-08', 'pacote', 25, 10, 5.00, 9.00, null, null);
    }

    private function produto(
        Supplier $fornecedor,
        ProductCategory $categoria,
        string $nome,
        string $codigo,
        string $unidade,
        int $estoque,
        int $estoqueMinimo,
        float $custo,
        float $preco,
        ?Carbon $validade,
        ?string $lote,
        int $entradaInicial = 0
    ): void {
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

        $estoqueAtual = $entradaInicial > 0 ? $entradaInicial : $estoque;

        StockMovement::create([
            'product_id' => $produto->id,
            'user_id' => $this->userRecepcao->id,
            'tipo' => 'entrada',
            'quantidade' => $estoqueAtual,
            'saldo_anterior' => 0,
            'saldo_posterior' => $estoqueAtual,
            'custo' => $custo,
            'motivo' => 'Compra inicial',
        ]);

        if ($entradaInicial > 0) {
            StockMovement::create([
                'product_id' => $produto->id,
                'user_id' => $this->userRecepcao->id,
                'tipo' => 'saida',
                'quantidade' => $entradaInicial - $estoque,
                'saldo_anterior' => $entradaInicial,
                'saldo_posterior' => $estoque,
                'custo' => $custo,
                'motivo' => 'Uso em consultas',
            ]);
        }
    }

    private function semearAuditoria(): void
    {
        $pMaria = Paciente::where('nome', 'Maria Oliveira')->firstOrFail();
        $pAna = Paciente::where('nome', 'Ana Clara Santos')->firstOrFail();
        $pJoao = Paciente::where('nome', 'João Pereira')->firstOrFail();

        AuditLogger::log('criar', 'pacientes', $pMaria->id, depois: ['nome' => $pMaria->nome], user: $this->userRecepcao);
        AuditLogger::log('criar', 'pacientes', $pAna->id, depois: ['nome' => $pAna->nome], user: $this->userRecepcao);
        AuditLogger::log('criar', 'pacientes', $pJoao->id, depois: ['nome' => $pJoao->nome], user: $this->userRecepcao);
        AuditLogger::log('marcar_dente', 'odontogram_teeth', $pMaria->id, depois: ['dente' => 11], user: $this->admin);
    }
}
