<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\WhatsAppConversation;
use App\Services\WhatsApp\ClinicFeatures;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * A conversa inteira, turno por turno, pelo mesmo endpoint que o n8n chama.
 *
 * O teste morre no primeiro turno quebrado se a máquina estiver errada, e é o
 * único lugar onde isso é barato: antes, a mesma lógica dentro do Code node do
 * n8n só se verificava lendo registro de execução, e o erro só aparecia em
 * produção depois de reiniciar o container.
 */
class WhatsAppFluxoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private string $token = 'token-fluxo';

    private string $jid = '5599999999999@s.whatsapp.net';

    private Professional $profissional;

    private Procedure $procedimento;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixa "agora" numa segunda-feira de manhã. A agenda de teste é das
        // 08:00 às 12:00 de segunda a sexta: sem isto o mesmo teste passa de
        // manhã e falha à tarde, que é o pior tipo de teste — o que ninguém
        // sabe se está quebrado.
        Carbon::setTestNow(Carbon::parse('2026-11-16 07:00:00'));

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => 'segredo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function criarContexto(?array $abilities = null): Clinic
    {
        $clinica = $this->criarClinicaTeste('Clínica Teste');

        $especialidade = Specialty::create(['name' => 'Odontologia']);

        $this->profissional = Professional::create([
            'nome' => 'Dra. Ana',
            'is_active' => true,
        ]);

        $this->profissional->specialties()->attach($especialidade->id);

        $this->procedimento = Procedure::create([
            'codigo' => 'CONSULTA',
            'nome' => 'Consulta',
            'duracao_minutos' => 30,
            'is_active' => true,
        ]);

        // Semana cheia: um teste que depende do dia da semana é um teste que
        // falha na sexta e passa na segunda.
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProfessionalSchedule::create([
                'professional_id' => $this->profissional->id,
                'weekday' => $weekday,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]);
        }

        Room::create(['nome' => 'Sala 1', 'is_active' => true]);

        foreach (['agendado', 'confirmado', 'cancelado'] as $name) {
            AppointmentStatus::create(['name' => $name, 'label' => ucfirst($name)]);
        }

        $this->assinarPlanoComWhatsapp($clinica);

        IntegrationToken::create([
            'clinic_id' => $clinica->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($this->token),
            'abilities' => $abilities ?? [IntegrationAbilities::WILDCARD],
        ]);

        return $clinica;
    }

    /**
     * Envia uma mensagem e devolve a resposta da máquina de estados.
     *
     * @return array{ok: bool, etapa_de: string, etapa_para: string, resposta: string}
     */
    private function dizer(string $texto, ?string $jid = null): array
    {
        $resposta = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.fluxo'), [
                'remote_jid' => $jid ?? $this->jid,
                'texto' => $texto,
            ]);

        $resposta->assertOk();

        return $resposta->json();
    }

    /**
     * Leva a conversa do consentimento até a tela de dias, e devolve o texto da
     * tela de dias.
     *
     * São quatro "1" depois do menu, um por tela: profissional, procedimento,
     * dia e horário. Encurtar isto aqui faz o teste seguinte afirmar sobre a tela
     * errada, que é o jeito mais fácil de escrever um teste verde que não protege
     * nada.
     */
    private function atéTelaDeData(): string
    {
        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        // menu -> especialidade
        $this->dizer('1');
        // especialidade -> profissional
        $this->dizer('1');
        // profissional -> procedimento
        $this->dizer('1');

        // procedimento -> dia
        return $this->dizer('1')['resposta'];
    }

    /**
     * Da tela de dias até a de horários.
     */
    private function atéTelaDeHorario(): string
    {
        $this->atéTelaDeData();

        return $this->dizer('1')['resposta'];
    }

    public function test_primeiro_contato_precisa_do_consentimento(): void
    {
        $this->criarContexto();

        $r = $this->dizer('oi');

        $this->assertSame('consentimento', $r['etapa_para']);
        $this->assertStringContainsString('LGPD', $r['resposta']);
        $this->assertSame('consentimento', WhatsAppConversation::first()->etapa);
    }

    public function test_texto_sem_numero_na_etapa_de_consentimento_repete_o_consentimento(): void
    {
        $this->criarContexto();

        $this->dizer('oi');

        $r = $this->dizer('o que é isso');

        $this->assertSame('consentimento', $r['etapa_para']);
        $this->assertStringContainsString('LGPD', $r['resposta']);
    }

    public function test_paciente_ja_cadastrado_pula_o_cadastro(): void
    {
        $clinica = $this->criarContexto();

        Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'Maria Existente',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $this->dizer('oi');
        $r = $this->dizer('1');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Maria Existente', $r['resposta']);
        // Reconhecer quem já está cadastrado é o que evita o cadastro duplicado,
        // e o cadastro duplicado é o que faz a clínica receber dois pacientes
        // com o mesmo celular.
        $this->assertTenantDatabaseCount('pacientes', 1);
    }

    public function test_cadastro_cria_o_paciente_e_avanca_para_o_menu(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $r = $this->dizer('João da Silva');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Cadastro realizado', $r['resposta']);
        $this->assertTenantDatabaseHas('pacientes', ['nome' => 'João da Silva']);
    }

    public function test_nome_curto_nao_cria_paciente(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $r = $this->dizer('Jo');

        $this->assertSame('cadastro', $r['etapa_para']);
        $this->assertTenantDatabaseMissing('pacientes', ['nome' => 'Jo']);
    }

    public function test_escolher_recepcao_vai_para_etapa_de_espera(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $r = $this->dizer('2');

        $this->assertSame('recepcao', $r['etapa_para']);

        // Estado de espera não continua menuizando: quem pediu um humano não
        // recebe o menu de novo a cada mensagem.
        $r = $this->dizer('1');

        $this->assertSame('recepcao', $r['etapa_para']);
        $this->assertStringContainsString('atendente', mb_strtolower($r['resposta']));
    }

    public function test_menu_devolve_o_menu_de_qualquer_etapa(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');
        $this->dizer('1');
        $this->dizer('1');

        $this->assertSame('profissional', WhatsAppConversation::first()->etapa);

        $r = $this->dizer('menu');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Agendar consulta', $r['resposta']);
    }

    public function test_emoji_no_menu_e_entendido_como_opcao(): void
    {
        $this->criarContexto();

        $this->dizer('oi');

        // O paciente copia o menu inteiro, emoji incluso.
        $r = $this->dizer('1️⃣ Sim, continuar');

        $this->assertSame('cadastro', $r['etapa_para']);
    }

    public function test_agendamento_completo_cria_a_consulta(): void
    {
        $this->criarContexto();

        $telaHorario = $this->atéTelaDeHorario();

        $this->assertStringContainsString('Horários livres', $telaHorario);

        $r = $this->dizer('1');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Agendamento confirmado', $r['resposta']);

        $consulta = Appointment::sole();

        $this->assertSame('João da Silva', $consulta->paciente->nome);
        $this->assertSame($this->profissional->id, $consulta->profissional_id);
        // `diffInMinutes` devolve float; sem o cast, `assertSame(30, 30.0)`
        // falha e o teste vira teatro.
        $this->assertSame(30, (int) $consulta->inicio->diffInMinutes($consulta->fim));
    }

    public function test_a_duracao_vem_do_procedimento_e_nao_da_escolha_do_paciente(): void
    {
        $this->criarContexto();

        $this->procedimento->update(['duracao_minutos' => 90]);

        $this->atéTelaDeHorario();
        $this->dizer('1');

        // 90 min a partir das 08:00 termina 09:30: um procedimento de 30 min
        // teria criado a consulta das 08:00 às 08:30.
        $this->assertSame(90, (int) Appointment::sole()->inicio->diffInMinutes(Appointment::sole()->fim));
    }

    public function test_horario_ocupado_por_outra_consulta_e_oferecido_depois_dele(): void
    {
        $this->criarContexto();

        $paciente = Paciente::create([
            'clinica_id' => 1,
            'nome' => 'Outro',
            'celular' => '5532999999999',
            'status' => 'ativo',
        ]);

        // ocupado das 08:00 às 09:00: o 08:00 e o 08:30 somem, e o 09:00 passa a ser
        // a primeira opção — que é o que se está provando.
        Appointment::create([
            'clinica_id' => 1,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => Carbon::parse($this->primeiroDiaUtil())->setTime(8, 0),
            'fim' => Carbon::parse($this->primeiroDiaUtil())->setTime(9, 0),
            'titulo' => 'Consulta',
        ]);

        $tela = $this->atéTelaDeHorario();

        $this->assertStringContainsString('1. 09:00', $tela);
        $this->assertStringNotContainsString('1. 08:00', $tela);
    }

    public function test_reenvio_da_mesma_escolha_nao_duplica_a_consulta(): void
    {
        $this->criarContexto();

        $this->atéTelaDeHorario();

        $r = $this->dizer('1');

        $this->assertStringContainsString('Agendamento confirmado', $r['resposta']);

        // A Evolution reentrega o mesmo webhook logo em seguida. A conversa já
        // fechou o agendamento, então o "1" reentregue cai no menu — e menu não
        // agenda nada. Uma segunda consulta para o mesmo horário aqui seria o
        // duplicate que o paciente vê como duas consultas.
        $this->dizer('1');

        $this->assertSame(1, Appointment::count());

        // A chave de idempotência do agendamento em si é coberta em
        // WhatsAppApiTest: aqui o que se prova é que a conversa não repete a
        // marcação.
    }

    public function test_opcao_fora_da_lista_nao_avanca_a_conversa(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');
        $this->dizer('1');
        $this->dizer('1');

        $r = $this->dizer('99');

        $this->assertSame('profissional', $r['etapa_para']);
        $this->assertStringContainsString('Opção inválida', $r['resposta']);
    }

    public function test_opcao_fora_da_lista_no_menu_mostra_o_menu_de_novo(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        $r = $this->dizer('99');

        // No menu não existe lista para o paciente errar: ele escolhe entre duas
        // opções, então o certo é repetir o menu.
        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Agendar consulta', $r['resposta']);
    }

    public function test_sem_profissional_ativo_a_conversa_vai_para_a_recepcao(): void
    {
        $this->criarContexto();

        $this->profissional->update(['is_active' => false]);

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        // Sem profissional ativo vinculado, o menu cai direto na lista de
        // profissionais — que está vazia — e manda para a recepção.
        $r = $this->dizer('1');

        $this->assertSame('recepcao', $r['etapa_para']);
        $this->assertStringContainsString('Nenhum profissional', $r['resposta']);
    }

    public function test_horarios_sao_reconsultados_e_nao_reaproveitados_do_estado(): void
    {
        $this->criarContexto();

        // Para na tela de dias de propósito: é a reconsulta AO ESCOLHER O DIA que
        // este teste cobre, e o horário guardado no estado é o da consulta antiga.
        $this->atéTelaDeData();

        // Na tela de dias o estado guarda os DIAS, e não os horários: os horários só
        // existem depois da escolha do dia. Guardar os horários aqui e seria
        // justamente o erro que este teste existe para pegar.
        $this->assertSame(
            $this->primeiroDiaUtil(),
            WhatsAppConversation::sole()->dados['dias'][0]['data'] ?? null,
        );

        // Alguém agenda o 08:00 entre a tela de dias e a escolha do paciente.
        $paciente = Paciente::create([
            'clinica_id' => 1,
            'nome' => 'Outro',
            'celular' => '5532999999999',
            'status' => 'ativo',
        ]);

        Appointment::create([
            'clinica_id' => 1,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => Carbon::parse($this->primeiroDiaUtil())->setTime(8, 0),
            'fim' => Carbon::parse($this->primeiroDiaUtil())->setTime(12, 0),
            'titulo' => 'Consulta',
        ]);

        $r = $this->dizer('1');

        // O turno inteiro acabou entre a tela de dias e a escolha, então a tela
        // de horas não pode ser montada. Ocupar só 30 min não provaria nada: o
        // dia ainda teria folga e o dia apareceria normalmente.
        $this->assertStringContainsString('não tem mais horários livres', $r['resposta']);
        $this->assertSame(1, Appointment::count());
    }

    public function test_slot_desocupado_entre_a_tela_e_a_escolha_e_recusado(): void
    {
        $this->criarContexto();

        $this->atéTelaDeHorario();

        // Aqui a lista já foi montada e mostrada; o 08:00 estava livre quando o
        // paciente viu. Se alguém agendar entre a tela e a escolha, o bot tem
        // que recusar em vez de criar consulta em cima da outra.
        $paciente = Paciente::create([
            'clinica_id' => 1,
            'nome' => 'Outro',
            'celular' => '5532999999999',
            'status' => 'ativo',
        ]);

        Appointment::create([
            'clinica_id' => 1,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => Carbon::parse($this->primeiroDiaUtil())->setTime(8, 0),
            'fim' => Carbon::parse($this->primeiroDiaUtil())->setTime(8, 30),
            'titulo' => 'Consulta',
        ]);

        $r = $this->dizer('1');

        $this->assertStringContainsString('acabou de ser ocupado', $r['resposta']);
        $this->assertSame(1, Appointment::count());
    }

    public function test_conversa_expirada_volta_para_o_consentimento(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        WhatsAppConversation::sole()->update(['expires_at' => now()->subMinute()]);

        $r = $this->dizer('oi');

        // A conversa expirada não continua de onde parou: sem isto, uma conversa
        // abandonada na tela de horário voltaria do dia seguinte ainda esperando
        // a escolha de um turno que já passou.
        $this->assertSame('consentimento', $r['etapa_de']);
        $this->assertSame('consentimento', $r['etapa_para']);
        $this->assertStringContainsString('LGPD', $r['resposta']);
    }

    public function test_rotulo_do_jid_no_telefone_e_ignorado(): void
    {
        $this->criarContexto();

        $p = $this->dizer('oi');
        $g = $this->dizer('oi', '5599999999999@g.us');

        // São conversas diferentes: o `g.us` é grupo, e o número não deve ser
        // confundido com o contato do `@s.whatsapp.net`.
        $this->assertSame('consentimento', $p['etapa_para']);
        $this->assertSame('consentimento', $g['etapa_para']);
        $this->assertTenantDatabaseCount('whatsapp_conversations', 2);
    }

    public function test_especialidade_lista_e_filtra_profissionais(): void
    {
        $this->criarContexto();

        // Outra especialidade com outro profissional
        $ortodontia = Specialty::create(['name' => 'Ortodontia']);
        $profissionalOrtodontia = Professional::create([
            'nome' => 'Dr. Brackets',
            'is_active' => true,
        ]);
        $profissionalOrtodontia->specialties()->attach($ortodontia->id);

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        // menu -> especialidade: as duas aparecem, sem os profissionais ainda.
        $r = $this->dizer('1');

        $this->assertSame('especialidade', $r['etapa_para']);
        $this->assertStringContainsString('Odontologia', $r['resposta']);
        $this->assertStringContainsString('Ortodontia', $r['resposta']);

        // Odontologia é a primeira em ordem alfabética: só a Dra. Ana aparece.
        $r = $this->dizer('1');

        $this->assertSame('profissional', $r['etapa_para']);
        $this->assertStringContainsString('Dra. Ana', $r['resposta']);
        $this->assertStringNotContainsString('Dr. Brackets', $r['resposta']);
    }

    public function test_minhas_consultas_mostra_agendamentos_do_paciente(): void
    {
        $clinica = $this->criarContexto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $statusConfirmado = AppointmentStatus::where('name', 'confirmado')->first();

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => $statusConfirmado->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        // Paciente já cadastrado: consentimento leva direto ao menu.
        $this->dizer('oi');
        $this->dizer('1');

        // menu -> minhas consultas (opção 2)
        $r = $this->dizer('2');

        $this->assertSame('minhas_consultas', $r['etapa_para']);
        $this->assertStringContainsString('Suas consultas', $r['resposta']);
        $this->assertStringContainsString($inicio->format('d/m'), $r['resposta']);
        $this->assertStringContainsString('Dra. Ana', $r['resposta']);
        $this->assertStringContainsString('Confirmado', $r['resposta']);
    }

    public function test_minhas_consultas_nao_mostra_consulta_cancelada(): void
    {
        $clinica = $this->criarContexto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::CANCELADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta cancelada',
        ]);

        $this->dizer('oi');
        $this->dizer('1');
        $r = $this->dizer('2');

        $this->assertStringContainsString('não tem consultas agendadas', $r['resposta']);
        $this->assertStringNotContainsString('Consulta cancelada', $r['resposta']);
    }

    public function test_menu_opcao_tres_vai_para_a_recepcao(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        $r = $this->dizer('3');

        $this->assertSame('recepcao', $r['etapa_para']);
        $this->assertStringContainsString('recepção', $r['resposta']);
    }

    public function test_sintoma_encaminha_para_a_especialidade_correta(): void
    {
        $this->criarContexto();

        // A clínica de teste tem só "Odontologia"; para o mapa de sintomas
        // apontar para um profissional vinculado, adicionamos Endodontia.
        $endodontia = Specialty::create(['name' => 'Endodontia']);
        $this->profissional->specialties()->attach($endodontia->id);

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');

        // menu -> especialidade
        $this->dizer('1');

        // Última opção da lista: "Descrever o que sinto".
        $r = $this->dizer('3');
        $this->assertSame('sintoma', $r['etapa_para']);

        $r = $this->dizer('estou com dor de dente');

        $this->assertSame('profissional', $r['etapa_para']);
        $this->assertStringContainsString('Endodontia', $r['resposta']);
        $this->assertStringContainsString('Dra. Ana', $r['resposta']);
    }

    public function test_sintoma_sem_casamento_volta_para_a_lista_de_especialidades(): void
    {
        $this->criarContexto();

        $this->dizer('oi');
        $this->dizer('1');
        $this->dizer('João da Silva');
        $this->dizer('1');
        $this->dizer('2');

        $r = $this->dizer('quero um café');

        $this->assertSame('especialidade', $r['etapa_para']);
        $this->assertStringContainsString('Não consegui identificar', $r['resposta']);
        $this->assertStringContainsString('Odontologia', $r['resposta']);
    }

    public function test_confirmar_marca_a_consulta_de_amanha_como_confirmada(): void
    {
        $clinica = $this->criarContexto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        $consulta = Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->dizer('oi');
        $this->dizer('1');

        $r = $this->dizer('confirmar');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Consulta confirmada', $r['resposta']);
        $this->assertSame(
            AppointmentStatus::idFor(AppointmentStatus::CONFIRMADO),
            $consulta->fresh()->appointment_status_id,
        );
    }

    public function test_confirmar_sem_consulta_para_amanha_avisa_o_paciente(): void
    {
        $clinica = $this->criarContexto();

        Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $this->dizer('oi');
        $this->dizer('1');

        $r = $this->dizer('confirmar');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Não há consulta agendada para amanhã', $r['resposta']);
    }

    public function test_token_sem_a_ability_do_fluxo_e_recusado(): void
    {
        $this->criarContexto(['appointments.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.fluxo'), [
                'remote_jid' => $this->jid,
                'texto' => 'oi',
            ])
            ->assertForbidden();
    }

    public function test_fluxo_exige_remote_jid(): void
    {
        $this->criarContexto();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.fluxo'), ['texto' => 'oi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remote_jid');
    }

    public function test_fluxo_nao_envia_mensagem_nem_registra_saida(): void
    {
        $this->criarContexto();

        $this->dizer('oi');

        // A máquina devolve o texto; quem envia é o n8n. Sem isto, o Laravel
        // falaria com a Evolution por dentro e o dry-run não existiria.
        $this->assertTenantDatabaseCount('whatsapp_messages', 0);
    }

    public function test_comando_de_lembretes_enfileira_consulta_de_amanha(): void
    {
        $clinica = $this->criarContexto();

        // Sem instância/token da Evolution o comando ignora a clínica, e o
        // teste não provaria o enfileiramento.
        $clinica->update([
            'evolution_instance' => 'instancia-teste',
            'evolution_token' => 'token-teste',
        ]);

        $this->habilitarLembretes($clinica);

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        $consulta = Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->artisan('whatsapp:lembretes')->assertSuccessful();

        $this->assertTenantDatabaseHas('whatsapp_outbox', [
            'correlation_id' => "lembrete_24h_{$consulta->id}",
            'telefone' => '5599999999999',
            'source' => 'lembrete_24h',
        ]);
    }

    public function test_comando_de_lembretes_nao_duplica_o_lembrete(): void
    {
        $clinica = $this->criarContexto();

        $clinica->update([
            'evolution_instance' => 'instancia-teste',
            'evolution_token' => 'token-teste',
        ]);

        $this->habilitarLembretes($clinica);

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->artisan('whatsapp:lembretes')->assertSuccessful();
        $this->artisan('whatsapp:lembretes')->assertSuccessful();

        $this->assertTenantDatabaseCount('whatsapp_outbox', 1);
    }

    public function test_comando_de_lembretes_ignora_clinica_sem_integracao(): void
    {
        $clinica = $this->criarContexto();

        // Flag ligada de propósito: o que se prova aqui é a ausência de
        // integração, não a flag.
        $this->habilitarLembretes($clinica);

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->artisan('whatsapp:lembretes')->assertSuccessful();

        $this->assertTenantDatabaseCount('whatsapp_outbox', 0);
    }

    public function test_comando_de_lembretes_em_dry_run_nao_grava_nada(): void
    {
        $clinica = $this->criarContexto();

        $clinica->update([
            'evolution_instance' => 'instancia-teste',
            'evolution_token' => 'token-teste',
        ]);

        $this->habilitarLembretes($clinica);

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->artisan('whatsapp:lembretes', ['--dry-run' => true])->assertSuccessful();

        $this->assertTenantDatabaseCount('whatsapp_outbox', 0);
    }

    public function test_comando_de_lembretes_nao_faz_nada_com_a_flag_desligada(): void
    {
        $clinica = $this->criarContexto();

        // Evolution configurada, mas a flag `reminders` continua no padrão
        // desligado: o comando não pode enfileirar nada.
        $clinica->update([
            'evolution_instance' => 'instancia-teste',
            'evolution_token' => 'token-teste',
        ]);

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => AppointmentStatus::idFor(AppointmentStatus::AGENDADO),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
        ]);

        $this->artisan('whatsapp:lembretes')->assertSuccessful();

        $this->assertTenantDatabaseCount('whatsapp_outbox', 0);
    }

    /**
     * Liga o recurso de lembretes para a clínica, como um operador faria.
     */
    private function habilitarLembretes(Clinic $clinica): void
    {
        app(ClinicFeatures::class)->set($clinica, 'reminders', true);
    }

    /**
     * Primeiro dia útil a partir de hoje, que é o primeiro que a agenda oferece.
     */
    private function primeiroDiaUtil(): string
    {
        $dia = Carbon::today();

        // A agenda é das 08:00 às 12:00; depois disso o primeiro dia com vaga
        // já é o seguinte. E sábado/domingo não têm expediente.
        if (now()->gte($dia->copy()->setTime(12, 0))) {
            $dia = $dia->copy()->addDay();
        }

        while ($dia->isWeekend()) {
            $dia = $dia->copy()->addDay();
        }

        return $dia->toDateString();
    }
}
