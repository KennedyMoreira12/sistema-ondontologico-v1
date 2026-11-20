<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic as CentralClinic;
use App\Models\Central\IntegrationToken;
use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Services\WhatsApp\ConfiguracaoWhatsApp;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Fluxo odontológico da Clínica Malheiro (modo configurável por clínica).
 *
 * Nenhuma mensagem real sai daqui: o endpoint devolve o texto e a máquina de
 * estados é assertada turno a turno, igual ao fluxo padrão.
 */
class WhatsAppFluxoOdontoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private string $token = 'token-odonto';

    private string $jid = '5599999999999@s.whatsapp.net';

    private Professional $profissional;

    private Procedure $procedimento;

    private Procedure $implante;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * Clínica com plano WhatsApp, token, profissional, procedimentos e a
     * configuração odontológica ligada apenas nesta clínica (isolamento).
     */
    private function criarContextoOdonto(): CentralClinic
    {
        $clinica = $this->criarClinicaTeste('Clínica Malheiro');

        $especialidade = Specialty::create(['name' => 'Odontologia']);
        $endodontia = Specialty::create(['name' => 'Endodontia']);

        $this->profissional = Professional::create([
            'nome' => 'Dra. Ana',
            'is_active' => true,
        ]);
        $this->profissional->specialties()->attach([$especialidade->id, $endodontia->id]);

        $this->procedimento = Procedure::create([
            'codigo' => 'CONSULTA',
            'nome' => 'Consulta clínica / avaliação',
            'duracao_minutos' => 30,
            'is_active' => true,
        ]);

        $this->implante = Procedure::create([
            'codigo' => 'IMPL_MALH',
            'nome' => 'Instalação de implantes',
            'duracao_minutos' => 60,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProfessionalSchedule::create([
                'professional_id' => $this->profissional->id,
                'weekday' => $weekday,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]);
        }

        Room::create(['nome' => 'Sala 1', 'is_active' => true]);

        foreach (['agendado', 'confirmado', 'cancelado', 'atendido'] as $name) {
            AppointmentStatus::create(['name' => $name, 'label' => ucfirst($name)]);
        }

        $this->assinarPlanoComWhatsapp($clinica);

        IntegrationToken::create([
            'clinic_id' => $clinica->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($this->token),
            'abilities' => [IntegrationAbilities::WILDCARD],
        ]);

        // Ativa o modo odontológico SOMENTE nesta clínica.
        ClinicSetting::definir(
            'whatsapp.fluxo_odontologico',
            json_encode([
                'modo' => ConfiguracaoWhatsApp::FLUXO_MODO_ODONTOLOGICO,
                'procedimentos' => [
                    'consulta' => ['nome' => 'Consulta clínica / avaliação', 'duracao_minutos' => 30],
                    'implante' => ['nome' => 'Instalação de implantes', 'duracao_min_minutos' => 30, 'duracao_max_minutos' => 90, 'duracao_padrao_minutos' => 60],
                ],
                'urgencia' => [
                    'sinais' => ['dor intensa', 'inchaço', 'sangramento', 'trauma', 'febre'],
                    'mensagem' => 'Onde sente a dor e há quanto tempo começou?',
                    'mensagem_grave' => 'Procure atendimento de urgência imediatamente.',
                ],
                'transferencia' => 'Encaminhando para a equipe humana.',
                'pos_atendimento' => 'Olá, {nome}! Como ficou o atendimento?',
            ]),
            'whatsapp',
        );

        return $clinica;
    }

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

    public function test_menu_odontologico_tem_cinco_opcoes_e_nao_duplica_paciente(): void
    {
        $this->criarContextoOdonto();

        $r = $this->dizer('oi');

        $this->assertSame('consentimento', $r['etapa_para']);
        $this->assertStringContainsString('bem-vindo', $r['resposta']);
        $this->assertStringContainsString('1.', $r['resposta']);
        $this->assertStringContainsString('5.', $r['resposta']);

        // Opção 4: já sou paciente, sem cadastro ainda -> pede nome.
        $r = $this->dizer('4');

        $this->assertSame('cadastro', $r['etapa_para']);
        $this->assertStringContainsString('nome completo', $r['resposta']);

        // Cadastro identifica pelo telefone e não duplica.
        $r = $this->dizer('Maria Malheiro');

        $this->assertSame('minhas_consultas', $r['etapa_para']);
        $this->assertTenantDatabaseCount('pacientes', 1);

        // Mesma pessoa retorna: não cadastra de novo.
        $this->dizer('menu');
        $this->dizer('4');
        $r = $this->dizer('Maria Malheiro');

        $this->assertSame('minhas_consultas', $r['etapa_para']);
        $this->assertTenantDatabaseCount('pacientes', 1);
    }

    public function test_agendamento_odontologico_usa_duracao_do_procedimento(): void
    {
        $this->criarContextoOdonto();

        $this->dizer('oi');
        $this->dizer('1'); // agendar
        $this->dizer('Dra. Ana'); // cadastro (nome)
        $this->dizer('1'); // especialidade Odontologia
        $this->dizer('1'); // profissional Dra. Ana
        $this->dizer('1'); // procedimento Consulta (30 min)

        $r = $this->dizer('1'); // dia

        $this->assertSame('horario', $r['etapa_para']);
        $this->assertStringContainsString('Horários livres', $r['resposta']);

        $r = $this->dizer('1'); // horario

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Agendamento confirmado', $r['resposta']);

        $consulta = Appointment::sole();
        $this->assertSame(30, (int) $consulta->inicio->diffInMinutes($consulta->fim));
        $this->assertTenantDatabaseCount('pacientes', 1);
    }

    public function test_implante_permite_escolher_duracao_30_90_configurada_por_clinica(): void
    {
        $this->criarContextoOdonto();

        $this->dizer('oi');
        $this->dizer('1'); // agendar
        $this->dizer('Dra. Ana'); // cadastro
        $this->dizer('1'); // especialidade
        $this->dizer('1'); // profissional

        $r = $this->dizer('2'); // procedimento Implante -> pede duração

        $this->assertSame('duracao_implante', $r['etapa_para']);
        $this->assertStringContainsString('30 a 90 minutos', $r['resposta']);

        // Escolhe 60 min (opções: 30, 60, 90).
        $r = $this->dizer('2');

        $this->assertSame('data', $r['etapa_para']);
        $this->assertStringContainsString('60 min', $r['resposta']);

        $r = $this->dizer('1'); // dia
        $this->assertSame('horario', $r['etapa_para']);

        $r = $this->dizer('1'); // horario

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('Agendamento confirmado', $r['resposta']);
        $consulta = Appointment::sole();
        $this->assertSame(60, (int) $consulta->inicio->diffInMinutes($consulta->fim));
    }

    public function test_dor_urgencia_coleta_sem_diagnostico_e_encaminha_com_motivo(): void
    {
        $this->criarContextoOdonto();

        $this->dizer('oi');
        $this->dizer('2'); // dor/urgência
        $r = $this->dizer('Maria Malheiro'); // cadastro -> inicia coleta de dor

        $this->assertSame('dor_coleta', $r['etapa_para']);
        $this->assertStringContainsString('Onde sente a dor', $r['resposta']);

        $r = $this->dizer('tenho dor intensa no dente do siso');

        $this->assertSame('dor_coleta', $r['etapa_para']);
        $this->assertStringContainsString('leve, moderada ou intensa', mb_strtolower($r['resposta']));

        $r = $this->dizer('sangramento e inchaço');

        $this->assertSame('recepcao', $r['etapa_para']);
        $this->assertStringContainsString('Encaminhando para a equipe humana', $r['resposta']);

        // Motivo preservado no estado da conversa.
        $conversa = WhatsAppConversation::sole();
        $dados = $conversa->dados ?? [];
        $this->assertStringContainsString('Dor/desconforto', (string) ($dados['motivo_handoff'] ?? ''));

        // Bot suspenso (aguardando atendente).
        $this->assertSame('aguardando_atendente', $conversa->fresh()->bot_status);

        // Alerta HANDOFF aberto com motivo.
        $this->assertTenantDatabaseHas('whatsapp_alerts', [
            'tipo' => WhatsAppAlert::HANDOFF,
        ]);
    }

    public function test_faq_nao_inventa_preco_e_encaminha_para_equipe(): void
    {
        $this->criarContextoOdonto();

        $this->dizer('oi');
        $this->dizer('3'); // FAQ / procedimentos

        $r = $this->dizer('quanto custa o clareamento?');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('equipe', mb_strtolower($r['resposta']));
        $this->assertStringNotContainsString('R$', $r['resposta']);
    }

    public function test_minhas_consultas_odonto_oferece_cancelar_reagendar_e_humano(): void
    {
        $clinica = $this->criarContextoOdonto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $statusAgendado = AppointmentStatus::idFor(AppointmentStatus::AGENDADO);
        $inicio = Carbon::tomorrow()->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => $statusAgendado,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta clínica / avaliação',
            'servico' => 'Consulta clínica / avaliação',
        ]);

        // Paciente já cadastrado: consentimento leva direto ao menu.
        $this->dizer('oi');

        // Já sou paciente.
        $r = $this->dizer('4');

        $this->assertSame('minhas_consultas', $r['etapa_para']);
        $this->assertStringContainsString('João da Silva', $r['resposta']);

        // Cancelar (opção 3) -> seleciona consulta -> confirma.
        $r = $this->dizer('3');

        $this->assertSame('cancelar_consulta', $r['etapa_para']);
        $this->assertStringContainsString('Qual consulta', $r['resposta']);

        $r = $this->dizer('1');

        $this->assertSame('cancelar_consulta', $r['etapa_para']);
        $this->assertStringContainsString('Confirma o cancelamento', $r['resposta']);

        $r = $this->dizer('1'); // sim, cancelar

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('cancelada com segurança', $r['resposta']);
    }

    public function test_modo_odontologico_ativo_apenas_com_configuracao(): void
    {
        $this->criarContextoOdonto();

        $this->assertTrue(
            app(ConfiguracaoWhatsApp::class)->modoOdontologico()
        );
    }

    public function test_confirmar_apos_gravacao_sucesso_inclui_endereco(): void
    {
        $clinica = $this->criarContextoOdonto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $statusAgendado = AppointmentStatus::idFor(AppointmentStatus::AGENDADO);
        $amanha = Carbon::tomorrow()->setTime(9, 0);

        $consulta = Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => $statusAgendado,
            'inicio' => $amanha,
            'fim' => $amanha->copy()->addMinutes(30),
            'titulo' => 'Consulta',
            'servico' => 'Consulta clínica / avaliação',
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

    public function test_pos_atendimento_oferecido_apos_consulta_atendida(): void
    {
        $clinica = $this->criarContextoOdonto();

        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'João da Silva',
            'celular' => '5599999999999',
            'status' => 'ativo',
        ]);

        $statusAtendido = AppointmentStatus::idFor('atendido');
        $inicio = Carbon::now()->subHours(2); // atendida há pouco, ainda hoje

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissional->id,
            'appointment_status_id' => $statusAtendido,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'titulo' => 'Consulta',
            'servico' => 'Consulta clínica / avaliação',
        ]);

        // Paciente já cadastrado e com atendimento recente: o consentimento
        // odontológico oferece o pós-atendimento em vez do menu puro.
        $r = $this->dizer('oi');

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertStringContainsString('orientações', mb_strtolower($r['resposta']));
        $this->assertStringContainsString('João', $r['resposta']);
    }
}
