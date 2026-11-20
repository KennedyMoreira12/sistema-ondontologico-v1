<?php

namespace Database\Seeders\tenant;

use App\Models\ClinicProfile;
use App\Models\ClinicSetting;
use App\Models\Procedure;
use App\Services\WhatsApp\ConfiguracaoWhatsApp;
use Illuminate\Database\Seeder;

/**
 * Configuração odontológica da Clínica Malheiro (tenant 1).
 *
 * Idempotente e restrito ao tenant da Malheiro. Grava serviços, textos e
 * parâmetros do atendimento em tabelas do tenant (procedures, clinic_settings),
 * sem tocar em dados de pacientes nem em outras clínicas.
 */
class MalheiroOdontologiaSeeder extends Seeder
{
    public function run(): void
    {
        if (function_exists('tenant_id') && tenant_id() !== 1) {
            return;
        }

        $this->procedimentos();
        $this->configuracoes();
    }

    private function procedimentos(): void
    {
        $observacao = 'Todos os procedimentos são precedidos de avaliação clínica e radiográfica, quando indicada, para direcionarmos a melhor conduta para cada caso.';

        $servicos = [
            [
                'codigo' => 'CLIN_GER',
                'nome' => 'Consulta clínica / avaliação',
                'categoria' => 'Consulta',
                'duracao_minutos' => 30,
                'valor_padrao' => null,
                'descricao' => "Avaliação clínica e planejamento odontológico.\n\n{$observacao}",
                'is_active' => true,
            ],
            [
                'codigo' => 'REST_MALH',
                'nome' => 'Restaurações',
                'categoria' => 'Restauradora',
                'duracao_minutos' => 60,
                'valor_padrao' => null,
                'descricao' => "A duração poderá variar conforme quantidade de dentes e complexidade.\n\n{$observacao}",
                'is_active' => true,
            ],
            [
                'codigo' => 'LIMPO',
                'nome' => 'Profilaxia (limpeza) com ultrassom',
                'categoria' => 'Prevenção',
                'duracao_minutos' => 60,
                'valor_padrao' => null,
                'descricao' => "Uso de ultrassom conforme avaliação profissional.\n\n{$observacao}",
                'is_active' => true,
            ],
            [
                'codigo' => 'IMPL_MALH',
                'nome' => 'Instalação de implantes',
                'categoria' => 'Implantes',
                'duracao_minutos' => 60,
                'valor_padrao' => null,
                'descricao' => "Duração configurável pela equipe entre 30 e 90 minutos, conforme o planejamento de cada caso.\n\n{$observacao}",
                'is_active' => true,
            ],
            [
                'codigo' => 'PT_BT_M',
                'nome' => 'Clareamento dental',
                'categoria' => 'Estética',
                'duracao_minutos' => 60,
                'valor_padrao' => null,
                'descricao' => "Indicado após avaliação clínica.\n\n{$observacao}",
                'is_active' => true,
            ],
        ];

        foreach ($servicos as $servico) {
            $valorPadrao = $servico['valor_padrao'];
            unset($servico['valor_padrao']);

            $procedure = Procedure::firstOrNew(['codigo' => $servico['codigo']]);
            $procedure->fill($servico);

            // Não inventar preço: só define se ainda não houver valor informado.
            if (! $procedure->exists || $procedure->valor_padrao === null) {
                $procedure->valor_padrao = $valorPadrao ?? 0;
            }

            $procedure->save();
        }
    }

    private function configuracoes(): void
    {
        $endereco = $this->enderecoClinica();
        $prompt = $this->promptIA();
        $fluxo = $this->fluxoOdontologico();
        $fluxo['modo'] = ConfiguracaoWhatsApp::FLUXO_MODO_ODONTOLOGICO;

        $valores = [
            ConfiguracaoWhatsApp::PROMPT_IA => $prompt,
            ConfiguracaoWhatsApp::MENSAGEM_BOAS_VINDAS => $this->boasVindas(),
            ConfiguracaoWhatsApp::MENSAGEM_MENU => $this->menu(),
            ConfiguracaoWhatsApp::MENSAGEM_AUSENCIA => $this->mensagemAusencia(),
            ConfiguracaoWhatsApp::ENDERECO_CONFIRMACAO => $endereco,
        ];

        foreach ($valores as $chave => $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }

            ClinicSetting::definir('whatsapp.'.$chave, $valor, 'whatsapp');
        }

        ClinicSetting::definir(
            'whatsapp.'.ConfiguracaoWhatsApp::FLUXO_ODONTOLOGICO,
            json_encode($fluxo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: null,
            'whatsapp',
        );
    }

    private function enderecoClinica(): ?string
    {
        $perfil = ClinicProfile::current();

        if (! $perfil) {
            return null;
        }

        $partes = array_filter([
            trim(implode(', ', array_filter([$perfil->address, $perfil->number]))),
            $perfil->complement,
            $perfil->district,
            trim(implode(' - ', array_filter([$perfil->city, $perfil->state]))),
            $perfil->postal_code ? 'CEP '.$perfil->postal_code : null,
        ], fn ($valor) => is_string($valor) && trim($valor) !== '');

        $endereco = implode(' - ', $partes);

        return $endereco !== '' ? $endereco : null;
    }

    private function boasVindas(): string
    {
        return "Olá! Seja bem-vindo(a) à Clínica Malheiro! 😊\n\n"
            ."Estamos aqui para cuidar do seu sorriso e ajudar você a encontrar o melhor atendimento odontológico.\n\n"
            ."Como podemos ajudá-lo(a) hoje?\n\n"
            ."1. Gostaria de agendar uma avaliação\n"
            ."2. Estou sentindo dor ou desconforto\n"
            ."3. Gostaria de saber sobre algum procedimento\n"
            ."4. Já sou paciente e preciso de atendimento\n"
            ."5. Gostaria de falar com a equipe\n\n"
            .'Se preferir, pode escrever sua dúvida diretamente por aqui.';
    }

    private function menu(): string
    {
        return "Como podemos ajudá-lo(a) hoje?\n\n"
            ."1. Gostaria de agendar uma avaliação\n"
            ."2. Estou sentindo dor ou desconforto\n"
            ."3. Gostaria de saber sobre algum procedimento\n"
            ."4. Já sou paciente e preciso de atendimento\n"
            ."5. Gostaria de falar com a equipe\n\n"
            .'Se preferir, pode escrever sua dúvida diretamente por aqui.';
    }

    private function mensagemAusencia(): string
    {
        return 'A Clínica Malheiro está fora do horário de atendimento no momento. '
            .'Sua mensagem foi recebida e nossa equipe dará continuidade assim que possível. '
            .'Se for uma urgência, procure um serviço de atendimento imediato.';
    }

    /**
     * @return array<string, mixed>
     */
    private function fluxoOdontologico(): array
    {
        return [
            'clinica' => 'Malheiro',
            'procedimentos' => [
                'consulta' => ['nome' => 'Consulta clínica / avaliação', 'duracao_minutos' => 30],
                'restauracao' => ['nome' => 'Restaurações', 'duracao_minutos' => 60],
                'profilaxia' => ['nome' => 'Profilaxia (limpeza) com ultrassom', 'duracao_minutos' => 60],
                'implante' => ['nome' => 'Instalação de implantes', 'duracao_min_minutos' => 30, 'duracao_max_minutos' => 90, 'duracao_padrao_minutos' => 60],
                'clareamento' => ['nome' => 'Clareamento dental', 'duracao_minutos' => 60],
            ],
            'observacao_padrao' => 'Todos os procedimentos são precedidos de avaliação clínica e radiográfica, quando indicada, para direcionarmos a melhor conduta para cada caso.',
            'faq' => [
                'consulta_tempo' => 'A consulta clínica de avaliação possui duração estimada de 30 minutos.',
                'clareamento' => 'O clareamento é indicado após avaliação clínica, para verificar a indicação e as condições de saúde bucal.',
                'implante_tempo' => 'A duração estimada pode variar de 30 a 90 minutos, conforme procedimento e planejamento.',
                'profilaxia_ultrassom' => 'A profilaxia pode incluir ultrassom, conforme avaliação e necessidade clínica.',
                'radiografia' => 'A avaliação radiográfica será realizada quando indicada pelo profissional.',
                'foto' => 'O paciente pode enviar foto para auxiliar a equipe na compreensão da solicitação, mas ela não substitui avaliação clínica e radiográfica.',
                'preco' => 'Para valores, nossa equipe pode informar conforme o procedimento e a avaliação necessária. Se quiser, posso encaminhar sua mensagem para a equipe.',
            ],
            'urgencia' => [
                'sinais' => ['dor intensa', 'inchaço', 'inchaco', 'inchaço facial', 'febre', 'sangramento', 'trauma', 'dificuldade para abrir a boca', 'dificuldade para engolir', 'dificuldade para respirar'],
                'mensagem' => "Sinto muito que você esteja sentindo desconforto. 😔\n\nPara que possamos direcionar seu atendimento, poderia me informar onde está sentindo a dor e há quanto tempo começou?",
                'mensagem_grave' => 'Se houver dor intensa, inchaço importante, febre, sangramento persistente, trauma ou dificuldade para respirar/engolir/abrir a boca, procure atendimento de urgência imediatamente. Posso encaminhar sua conversa para nossa equipe agora.',
            ],
            'transferencia' => 'Claro! 😊 Vou encaminhar sua conversa para nossa equipe de atendimento.\n\nAssim que possível, um profissional da clínica dará continuidade ao atendimento por aqui.',
            'confirmacao_template' => "Seu atendimento foi agendado! 😊\n\n📅 Data: {data}\n⏰ Horário: {horario}\n🦷 Procedimento: {procedimento}\n📍 Local: {endereco}\n\nCaso precise alterar seu horário, entre em contato conosco.",
            'lembrete_template' => "Olá, {nome}! 😊\n\nPassando para confirmar sua consulta na Clínica Malheiro.\n\n📅 Data: {data}\n⏰ Horário: {horario}\n🦷 Atendimento: {procedimento}\n\nVocê confirma sua presença?\n\n1. Sim, confirmo\n2. Preciso reagendar\n3. Não poderei comparecer",
            'pos_atendimento' => "Olá, {nome}! 😊\n\nGostaríamos de saber como foi seu atendimento na Clínica Malheiro.\n\nCaso tenha alguma dúvida sobre as orientações recebidas ou precise de algum auxílio, estamos à disposição para encaminhar sua mensagem à equipe.",
        ];
    }

    private function promptIA(): string
    {
        return <<<'PROMPT'
IDENTIDADE:
Você é a assistente virtual de atendimento da Clínica Malheiro, clínica odontológica, no WhatsApp.

PERSONALIDADE:
- Cordial, empática, profissional, objetiva, natural e respeitosa.

OBJETIVOS:
- Recepcionar e identificar pacientes.
- Entender necessidades e informar serviços cadastrados.
- Auxiliar no pré-atendimento (anamnese) de forma gradual e opcional.
- Auxiliar agendamentos, confirmações, reagendamentos e cancelamentos consultando o sistema.
- Encaminhar para a equipe humana quando necessário.

SERVIÇOS E DURAÇÕES (estimativas, sujeitas à avaliação):
- Consulta clínica / avaliação: 30 minutos.
- Restaurações: 60 minutos (pode variar conforme quantidade de dentes e complexidade).
- Profilaxia (limpeza) com ultrassom: 60 minutos (ultrassom conforme avaliação).
- Instalação de implantes: 30 a 90 minutos, conforme planejamento da equipe.
- Clareamento dental: 60 minutos, indicado após avaliação clínica.
Todos os procedimentos são precedidos de avaliação clínica e radiográfica, quando indicada.

REGRAS:
- Responda em português brasileiro, com mensagens curtas e, sempre que possível, uma pergunta por vez.
- Não repita informações, não pressione o paciente e não invente dados.
- Nunca invente preços, convênios, endereços, horários ou disponibilidade; consulte o sistema antes de confirmar horários.
- Nunca diagnostique, prescreva, recomende doses, recomende suspender medicamentos, garanta resultados, defina tratamentos ou interprete exames como diagnóstico. Você não substitui o dentista.
- Se houver dúvida clínica, orçamento específico, problema financeiro, reclamação, urgência, tratamento em andamento, dificuldade de compreensão ou pedido do paciente, encaminhe para a equipe humana.
- Em caso de dor intensa, inchaço facial importante, febre, sangramento persistente, trauma, dificuldade para abrir a boca, engolir ou respirar, oriente procurar serviço de urgência imediatamente e encaminhe à equipe.
- Não obrigue o paciente a fornecer dados sensíveis pelo WhatsApp; ele pode preferir responder ao dentista. Respeite privacidade e LGPD.
- A confirmação de agendamento é feita apenas pelo sistema após gravação bem-sucedida.
PROMPT;
    }
}
