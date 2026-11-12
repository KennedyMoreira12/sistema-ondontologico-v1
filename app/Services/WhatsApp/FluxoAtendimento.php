<?php

namespace App\Services\WhatsApp;

use App\Exceptions\HorarioOcupadoException;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\Specialty;
use App\Models\WhatsAppConversation;
use App\Services\Agenda\AgendamentoService;
use App\Services\Agenda\DisponibilidadeService;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * A conversa do WhatsApp: consentimento, cadastro, profissional, procedimento,
 * data, horário e confirmação. Menu, sem IA.
 *
 * A máquina mora aqui, e não no n8n, por dois motivos que já custaram caro:
 *
 * 1. O Code node do n8n, nesta instalação, não tem HTTP. `fetch`, `$helpers` e
 *    `require('https')` não existem ali, então a versão da conversa que chamava
 *    a API por dentro do n8n não executava. Aqui a máquina fala direto com o
 *    banco: sem HTTP e sem token no meio do caminho.
 * 2. Estado de conversa testável é estado em PHP. Um `switch` em JavaScript
 *    dentro de um Code node só se verifica lendo registro de execução do n8n; o
 *    mesmo `switch` aqui é um teste de feature comum.
 *
 * O n8n continua sendo o orquestrador: ele aplica os guards (token, allowlist,
 * `from_me`), chama este fluxo e envia a resposta. Nenhuma regra de negócio fica
 * no workflow.
 */
class FluxoAtendimento
{
    /**
     * Atalho que vale em QUALQUER etapa.
     *
     * Sem isto, um paciente que se perde no meio do fluxo precisa terminar a
     * mensagem para sair dele — e a chance de isso acontecer é maior do que
     * parece, porque a lista de profissionais e a de procedimentos são longas.
     *
     * @var list<string>
     */
    public const COMANDOS_GLOBAIS = ['menu', 'inicio', 'cancelar', 'sair', 'voltar', 'confirmar'];

    /**
     * Quantos dias à frente o bot oferece agenda.
     *
     * Cinco é o que o paciente consegue percorrer em uma conversa de WhatsApp;
     * oferecer dois meses transforma a lista em algo que ninguém lê.
     */
    public const DIAS_DE_AGENDA = 5;

    /**
     * Quantos horários aparecem na tela.
     *
     * O limite existe porque um turno de 4 h com passos de 30 min tem 16 linhas,
     * e o paciente escolhe pelo número: uma lista longa vira chute.
     */
    public const MAXIMO_HORARIOS = 10;

    /**
     * Quantos procedimentos são mostrados de uma vez.
     */
    public const MAXIMO_PROCEDIMENTOS = 12;

    /**
     * Quantas especialidades aparecem de uma vez.
     */
    public const MAXIMO_ESPECIALIDADES = 15;

    /**
     * Mapeamento de sintomas/queixas para especialidades.
     *
     * A chave é o termo que o paciente pode digitar (sinônimos separados por
     * pipe). O valor são fragmentos do nome da especialidade cadastrada na
     * clínica, tentados na ordem. Fragmento, e não nome exato, porque cada
     * clínica nomeia a própria tabela ("Implantes", "ESPECIALISTA EM
     * IMPLANTES", "DENTÍSTICA RESTAURADORA E ESTÉTICA") e um casamento exato
     * faria o recurso funcionar só na clínica em que foi escrito.
     *
     * @var array<string, list<string>>
     */
    public const SINTOMAS_POR_ESPECIALIDADE = [
        'dor de dente|dente doendo|dente latejando|molar doendo|cárie|carie|canal' => ['Endodontia'],
        'dente quebrado|dente trincado|fratura dentária|dente lascado|restauração|restauracao' => ['Restauradora', 'Estética', 'Clínica geral'],
        'limpeza|tártaro|tartaro|remoção de tártaro|profilaxia|higiene bucal' => ['Clínica geral', 'Periodontia'],
        'aparelho|aparelho dentário|brackets|alinhador|mordida torta|dente desalinhado' => ['Ortodontia'],
        'implante|implante dentário|prótese|protese|coroa|ponte fixa|dente perdido|falta dente' => ['Implante'],
        'cirurgia|extração|extracao|siso|siso doendo|cirurgia oral|biópsia|biopsia' => ['Cirurgia'],
        'clareamento|dente amarelado|dente manchado|estética dental|estetica dental|facetas' => ['Estética', 'Restauradora'],
        'gengiva sangrando|gengiva inchada|periodontite|gengivite|recesso gengival' => ['Periodontia'],
        'criança|crianca|dente de leite|primeira consulta criança|odontopediatria' => ['Odontopediatria'],
        'dor de cabeça|dor de cabeca|ATM|articulação|articulacao|má oclusão|ma oclusao|ranger de dente|bruxismo' => ['DTM', 'Clínica geral'],
    ];

    /**
     * Quanto tempo uma conversa abandonada sobrevive.
     *
     * É também o prazo de validade das listas guardadas em `dados`: depois
     * disso os horários da conversa não correspondem mais à agenda real, e o
     * bot tem que reconsultar.
     */
    public const VALIDADE_HORAS = 4;

    public function __construct(
        protected TenantManager $tenants,
        protected DisponibilidadeService $disponibilidade,
        protected AgendamentoService $agendamentos,
    ) {}

    /**
     * Processa uma mensagem recebida e devolve o que o n8n precisa fazer.
     *
     * @return array{ok: bool, etapa_de: string, etapa_para: string, resposta: string}
     */
    public function processar(string $remoteJid, string $texto, string $nomeClinica = ''): array
    {
        $telefone = $this->telefone($remoteJid);
        $texto = trim($texto);
        $conversa = $this->abrirConversa($remoteJid);
        $etapa = $conversa->etapa ?? 'consentimento';
        $dados = is_array($conversa->dados) ? $conversa->dados : [];

        // O comando global é checado antes do switch: "menu" precisa funcionar
        // mesmo com um estado corrompido, senão o paciente fica preso.
        if (in_array(mb_strtolower($texto), self::COMANDOS_GLOBAIS, true)) {
            $nome = $dados['nome'] ?? $this->paciente($telefone)?->nome;

            // "voltar" só faz sentido se não estiver no menu: volta para a
            // etapa anterior gravada, ou para o menu se não houver histórico.
            if (mb_strtolower($texto) === 'voltar' && $etapa !== 'menu') {
                $etapaAnterior = $this->etapaAnterior($conversa);
                $this->salvar($conversa, $etapaAnterior, $dados);

                return $this->resultado($etapa, $etapaAnterior, $this->textoDaEtapa($etapaAnterior, $dados, $nome));
            }

            // "confirmar" confirma a consulta do paciente para amanhã, quando
            // ele recebeu o lembrete de 24h. Funciona de qualquer etapa, e o
            // estado é gravado como o de qualquer outra transição: sem isso a
            // conversa continuaria parada na etapa antiga depois da confirmação.
            if (mb_strtolower($texto) === 'confirmar') {
                [$novaEtapa, $novosDados, $resposta] = $this->confirmarPresenca($telefone, $dados, $nome);

                $this->salvar($conversa, $novaEtapa, $novosDados);

                return $this->resultado($etapa, $novaEtapa, $resposta);
            }

            // `$dados` e não um array novo: o paciente que pede "menu" no meio do
            // agendamento precisa continuar sendo o mesmo paciente quando volta
            // a escolher horário.
            $this->salvar($conversa, 'menu', $this->base($dados + ['nome' => $nome]));

            return $this->resultado($etapa, 'menu', $this->menu(is_string($nome) ? $nome : null));
        }

        [$numero, $opcao] = $this->opcao($texto);

        [$novaEtapa, $novosDados, $resposta] = match ($etapa) {
            'consentimento' => $this->consentimento($telefone, $opcao, $nomeClinica),
            'cadastro' => $this->cadastro($telefone, $texto),
            'menu' => $this->menuInicial($dados, $opcao),
            'sintoma' => $this->sintoma($texto, $dados),
            'especialidade' => $this->especialidade($dados, $numero, $opcao),
            'profissional' => $this->profissional($dados, $numero, $opcao),
            'procedimento' => $this->procedimento($dados, $numero, $opcao),
            'data' => $this->data($dados, $numero, $opcao),
            'horario' => $this->horario($remoteJid, $dados, $numero, $opcao),
            'minhas_consultas' => $this->minhasConsultas($dados, $numero, $opcao),
            // Estado de espera: a pessoa já avisou que quer a recepção, e o bot
            // não deve continuar menuizando alguém que pediu um humano.
            'recepcao' => ['recepcao', $dados, "Um atendente da clínica responderá em breve. 😊\n\nResponda \"menu\" se quiser voltar às opções."],
            'encerrado' => ['encerrado', $dados, 'Atendimento encerrado. Responda "menu" se quiser voltar às opções.'],
            // Etapa desconhecida: melhor um menu do que silêncio. Como a lista de
            // etapas é validada na gravação, este default só é alcançado por um
            // estado escrito direto no banco.
            default => ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)],
        };

        $this->salvar($conversa, $novaEtapa, $novosDados);

        return $this->resultado($etapa, $novaEtapa, $resposta);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function consentimento(string $telefone, ?int $opcao, string $nomeClinica): array
    {
        if ($opcao === 0) {
            $paciente = $this->paciente($telefone);

            if ($paciente !== null) {
                return ['menu', ['paciente_id' => $paciente->id, 'nome' => $paciente->nome], $this->menu($paciente->nome)];
            }

            return ['cadastro', [], 'Vamos fazer seu cadastro rápido. Qual é o seu nome completo?'];
        }

        if ($opcao === 1) {
            return ['recepcao', [], 'Tudo bem! Vou avisar a recepção para que um atendente te responda em breve.'];
        }

        return ['consentimento', [], $this->textoConsentimento($nomeClinica)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function cadastro(string $telefone, string $texto): array
    {
        if (mb_strlen($texto) < 3) {
            return ['cadastro', [], 'Por favor, informe seu nome completo.'];
        }

        $paciente = $this->paciente($telefone);

        if ($paciente === null) {
            $paciente = Paciente::create([
                'clinica_id' => $this->tenants->id(),
                'nome' => $texto,
                'celular' => $telefone,
                'status' => 'ativo',
            ]);

            AuditLogger::log('criar', 'pacientes', $paciente->id, depois: $paciente->only(['nome', 'celular']));
        }

        return [
            'menu',
            ['paciente_id' => $paciente->id, 'nome' => $paciente->nome],
            "Cadastro realizado, {$paciente->nome}! ".$this->menu($paciente->nome),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function menuInicial(array $dados, ?int $opcao): array
    {
        if ($opcao === 2) {
            return ['recepcao', $dados, 'Tudo bem! A recepção vai te atender em breve.'];
        }

        if ($opcao === 1) {
            return ['minhas_consultas', $dados, $this->minhasConsultasTexto($dados)];
        }

        if ($opcao !== 0) {
            return ['menu', $dados, $this->menu($dados['nome'] ?? null)];
        }

        // A etapa de especialidade só entra quando existe profissional ATIVO
        // vinculado a alguma especialidade. Sem esse vínculo — clínicas cujos
        // profissionais foram cadastrados antes das especialidades — a lista de
        // especialidades seria um caminho sem saída e o paciente não conseguiria
        // agendar. Nesse caso, o comportamento anterior (listar todos) continua.
        $temEspecialidadesVinculadas = Specialty::query()
            ->whereHas('professionals', fn ($query) => $query->where('is_active', true))
            ->exists();

        if (! $temEspecialidadesVinculadas) {
            return $this->listaDeProfissionais($dados);
        }

        return ['especialidade', $dados, $this->textoEspecialidade()];
    }

    /**
     * Lista todos os profissionais ativos, sem passar por especialidade.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function listaDeProfissionais(array $dados): array
    {
        $profissionais = Professional::with('specialties')
            ->where('is_active', true)
            ->orderBy('nome')
            ->get();

        if ($profissionais->isEmpty()) {
            return ['recepcao', $dados, 'Nenhum profissional disponível para agendamento agora. Vou avisar a recepção.'];
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidades = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidades !== '' ? " ({$especialidades})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return [
            'profissional',
            array_merge($dados, ['profissionais' => $profissionais->map(fn (Professional $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
            ])->all()]),
            "Escolha o profissional:\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function profissional(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['profissionais'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['profissional', $dados, 'Opção inválida. Responda com o número do profissional.'];
        }

        // Cortado ANTES de montar a lista e antes de guardar: guardar os 30
        // procedimentos ativos e mostrar 12 faria o "13" ser uma escolha válida
        // que o paciente nunca viu, e a numeração da tela deixaria de bater com
        // a lista.
        $procedimentos = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->take(self::MAXIMO_PROCEDIMENTOS)
            ->get();

        if ($procedimentos->isEmpty()) {
            return ['recepcao', $dados, 'Não tenho a lista de procedimentos carregada. Vou avisar a recepção.'];
        }

        $lista = $procedimentos
            ->map(fn (Procedure $p, int $i) => ($i + 1).". {$p->nome} ({$p->duracao_minutos} min)")
            ->implode("\n");

        return [
            'procedimento',
            $dados + [
                'profissional_id' => $escolhido['id'],
                'profissional' => $escolhido['nome'],
                'procedimentos' => $procedimentos->map(fn (Procedure $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'duracao' => (int) $p->duracao_minutos,
                ])->all(),
            ],
            "Certo, {$escolhido['nome']}. Qual procedimento?\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function procedimento(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['procedimentos'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['procedimento', $dados, 'Opção inválida. Responda com o número do procedimento.'];
        }

        // A data é oferecida já com a agenda real, e não num turno seguinte:
        // exigir uma mensagem só para "vou verificar" deixava o paciente
        // esperando um nada, e era o ponto onde a conversa morria.
        $agenda = $this->disponibilidade->horariosLivres(
            (int) $dados['profissional_id'],
            (int) $escolhido['duracao'],
            self::DIAS_DE_AGENDA,
        );

        $dias = $this->diasComHorarios($agenda);

        if ($dias === []) {
            return [
                'menu',
                $this->base($dados),
                "Sem horários livres para {$escolhido['nome']} com {$dados['profissional']} nos próximos dias.\n\n".$this->menu($dados['nome'] ?? null),
            ];
        }

        $lista = collect($dias)
            ->map(fn (array $dia, int $i) => ($i + 1).". {$dia['data']} ({$dia['dia_semana']}) — {$dia['quantidade']} horário(s)")
            ->implode("\n");

        return [
            'data',
            $dados + [
                'procedimento_id' => $escolhido['id'],
                'procedimento' => $escolhido['nome'],
                'duracao' => (int) $escolhido['duracao'],
                'dias' => $dias,
            ],
            "Escolha o dia para {$escolhido['nome']} ({$escolhido['duracao']} min):\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function data(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['dias'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['data', $dados, 'Opção inválida. Responda com o número do dia.'];
        }

        // Reconsultado, e não a lista guardada em `dados`: entre a tela do dia e
        // a escolha do horário pode passar uma hora, e nesse intervalo outra
        // pessoa agenda o mesmo slot.
        $horarios = $this->disponibilidade->horariosDoDia(
            (int) $dados['profissional_id'],
            (int) $dados['duracao'],
            $escolhido['data'],
            self::DIAS_DE_AGENDA,
        );

        if ($horarios === []) {
            return ['data', $dados, 'Esse dia não tem mais horários livres. Responda "menu" para ver outras opções.'];
        }

        // O mesmo corte do procedimento: a tela mostra 10, e é só o que o paciente
        // pode escolher que vai para o estado. Guardar os 16 do turno permitiria
        // agendar o "14" para alguém que nunca o viu.
        $visiveis = collect($horarios)
            ->take(self::MAXIMO_HORARIOS)
            ->values();

        $lista = $visiveis
            ->map(fn (string $hora, int $i) => ($i + 1).". {$hora}")
            ->implode("\n");

        return [
            'horario',
            $dados + [
                'data_escolhida' => $escolhido['data'],
                'horarios' => $visiveis->all(),
            ],
            "Horários livres em {$escolhido['data']} ({$escolhido['dia_semana']}):\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function horario(string $remoteJid, array $dados, ?string $numero, ?int $opcao): array
    {
        $hora = $this->itemEscolhido($dados['horarios'] ?? [], $numero, $opcao);

        if (! is_string($hora)) {
            return ['horario', $dados, 'Opção inválida. Responda com o número do horário.'];
        }

        /**
         * Chave por conversa+procedimento+slot: a Evolution reentrega o mesmo
         * webhook, e sem esta chave a reentrega criaria uma segunda consulta para
         * a mesma pessoa no mesmo horário.
         */
        $chave = mb_substr(
            "{$remoteJid}|{$dados['procedimento_id']}|{$dados['data_escolhida']}|{$hora}",
            0,
            64,
        );

        try {
            $this->agendamentos->criar([
                'paciente_id' => (int) $dados['paciente_id'],
                'profissional_id' => (int) $dados['profissional_id'],
                'dia' => $dados['data_escolhida'],
                'hora_inicio' => $hora,
                // A duração vem do procedimento escolhido, nunca um número solto.
                'duracao' => (int) $dados['duracao'],
                'tipo' => AppointmentStatus::AGENDADO,
                'observacao' => "WhatsApp: {$dados['procedimento']}",
                'idempotency_key' => $chave,
            ]);
        } catch (HorarioOcupadoException) {
            return ['horario', $dados, 'Esse horário acabou de ser ocupado. Responda "menu" para ver outros.'];
        } catch (LockTimeoutException) {
            return ['horario', $dados, 'A agenda está ocupada no momento. Tente de novo em instantes.'];
        } catch (UniqueConstraintViolationException) {
            /**
             * Perdeu a corrida do índice único da chave de idempotência: outra
             * requisição com a mesma chave gravou primeiro. O cliente quer o
             * agendamento, e ele existe — então isto é sucesso.
             */
        } catch (\Throwable $exception) {
            Log::error('A máquina de estados do WhatsApp não conseguiu agendar.', [
                'remote_jid' => $remoteJid,
                'erro' => $exception->getMessage(),
            ]);

            return ['horario', $dados, 'Não foi possível confirmar o agendamento agora. Tente de novo.'];
        }

        $nome = $dados['nome'] ?? null;

        return [
            'menu',
            $this->base($dados),
            "✅ Agendamento confirmado!\n\n"
            ."📅 {$dados['data_escolhida']} às {$hora}\n"
            ."🦷 {$dados['procedimento']}\n"
            ."👤 {$dados['profissional']}\n\n"
            .$this->menu($nome),
        ];
    }

    /**
     * O mínimo do estado que precisa sobreviver a um retorno ao menu.
     *
     * `paciente_id` é o campo que se perde com facilidade: as telas que devolvem a
     * conversa ao menu guardavam só o nome, e o paciente que usava "menu" no meio
     * do agendamento chegava à tela de horário com `paciente_id` vazio — o que vira
     * violação de chave estrangeira e uma consulta órfã, com a resposta "não foi
     * possível confirmar" para quem fez tudo certinho.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    protected function base(array $dados): array
    {
        $base = [];

        if (($dados['paciente_id'] ?? null) !== null) {
            $base['paciente_id'] = (int) $dados['paciente_id'];
        }

        if (($dados['nome'] ?? null) !== null) {
            $base['nome'] = $dados['nome'];
        }

        return $base;
    }

    /**
     * Escolhe um item de lista pela posição enviada pelo paciente.
     *
     * Texto não numérico e número fora da faixa devolvem a mesma coisa, porque
     * para o paciente é a mesma coisa: ele não escolheu nenhum item da lista.
     */
    protected function itemEscolhido(mixed $lista, ?string $numero, ?int $opcao): mixed
    {
        if ($numero === null || $opcao === null || ! is_array($lista)) {
            return null;
        }

        return $lista[$opcao] ?? null;
    }

    /**
     * Texto digitado pelo paciente convertido em número de opção.
     *
     * Só dígitos contam: o paciente copia o menu inteiro, emoji incluso, e
     * "1️⃣ Agendar consulta" tem que valer como "1".
     *
     * @return array{0: string|null, 1: int|null}
     */
    protected function opcao(string $texto): array
    {
        $numero = preg_replace('/\D/', '', $texto);

        if ($numero === null || $numero === '') {
            return [null, null];
        }

        return [$numero, (int) $numero - 1];
    }

    /**
     * Só os dias que têm horário livre, no formato que a tela do paciente
     * precisa.
     *
     * @param  list<array<string, mixed>>  $agenda
     * @return list<array{data: string, dia_semana: string, quantidade: int}>
     */
    protected function diasComHorarios(array $agenda): array
    {
        return collect($agenda)
            ->filter(fn (array $dia) => $dia['horarios'] !== [])
            ->map(fn (array $dia) => [
                'data' => $dia['data'],
                'dia_semana' => $dia['dia_semana'],
                'quantidade' => count($dia['horarios']),
            ])
            ->values()
            ->all();
    }

    /**
     * Paciente pelo celular, casando pelos últimos 9 dígitos.
     *
     * Os últimos 9 e não o número inteiro: o mesmo paciente aparece gravado com
     * DDD, com zero à esquerda e com o nono dígito acrescentado, e casar pelo
     * inteiro exato faz o bot pedir cadastro de quem já existe.
     */
    protected function paciente(string $telefone): ?Paciente
    {
        if (strlen($telefone) < 9) {
            return null;
        }

        return Paciente::where('celular', 'like', '%'.substr($telefone, -9))->first();
    }

    /**
     * Lê a conversa, resetando se estiver expirada.
     *
     * A expiração é verificada aqui, e não só guardada: sem esta checagem, uma
     * conversa abandonada na etapa `horario` voltaria do dia seguinte ainda
     * esperando a escolha de um horário de um turno que já passou, e o bot
     * marcaria a consulta para um dia que nem existe mais.
     */
    protected function abrirConversa(string $remoteJid): WhatsAppConversation
    {
        return WhatsAppConversation::abrirPara($remoteJid, self::VALIDADE_HORAS);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function salvar(WhatsAppConversation $conversa, string $etapa, array $dados): void
    {
        $conversa->etapa = $etapa;
        $conversa->dados = $dados;
        $conversa->expires_at = now()->addHours(self::VALIDADE_HORAS);
        $conversa->save();
    }

    protected function menu(?string $nome): string
    {
        $saudacao = is_string($nome) && $nome !== '' ? ", {$nome}" : '';

        return "Olá{$saudacao}! 👋 Como posso ajudar?\n\n1️⃣ Agendar consulta\n2️⃣ Minhas consultas\n3️⃣ Falar com a recepção";
    }

    /**
     * Determina a etapa anterior com base na etapa atual, para o comando "voltar".
     */
    protected function etapaAnterior(WhatsAppConversation $conversa): string
    {
        $etapa = $conversa->etapa ?? 'menu';
        $dados = is_array($conversa->dados) ? $conversa->dados : [];

        return match ($etapa) {
            'sintoma' => 'especialidade',
            'especialidade' => 'menu',
            // Sem especialidade escolhida (clínica que lista todos os
            // profissionais), "voltar" do profissional é o menu.
            'profissional' => isset($dados['especialidade_id']) ? 'especialidade' : 'menu',
            'procedimento' => 'profissional',
            'data' => 'procedimento',
            'horario' => 'data',
            'minhas_consultas' => 'menu',
            default => 'menu',
        };
    }

    /**
     * Texto adequado para cada etapa ao voltar, para não deixar o paciente sem contexto.
     */
    protected function textoDaEtapa(string $etapa, array $dados, ?string $nome): string
    {
        return match ($etapa) {
            'especialidade' => $this->textoEspecialidade(),
            'sintoma' => $this->textoSintoma(),
            'profissional' => $this->textoProfissional($dados),
            'procedimento' => $this->textoProcedimento(),
            'data' => $this->textoData($dados),
            'horario' => $this->textoHorario($dados),
            'minhas_consultas' => $this->minhasConsultasTexto($dados),
            default => $this->menu($nome),
        };
    }

    protected function textoEspecialidade(): string
    {
        $especialidades = Specialty::orderBy('name')
            ->take(self::MAXIMO_ESPECIALIDADES)
            ->get();

        if ($especialidades->isEmpty()) {
            return 'Não há especialidades cadastradas.';
        }

        $lista = $especialidades
            ->map(fn (Specialty $e, int $i) => ($i + 1).". {$e->name}")
            ->implode("\n");

        return "Escolha a especialidade:\n\n{$lista}\n\n".(count($especialidades) + 1).". Descrever o que sinto (ex: dor de dente)\n\nResponda com o número.";
    }

    protected function textoProfissional(array $dados): string
    {
        $especialidadeId = $dados['especialidade_id'] ?? null;

        $query = Professional::with('specialties')->where('is_active', true);

        if ($especialidadeId) {
            $query->whereHas('specialties', fn ($q) => $q->where('specialties.id', $especialidadeId));
        }

        $profissionais = $query->orderBy('nome')->get();

        // Mesmo recuo de `profissionaisDaEspecialidade`: sem vínculo cadastrado,
        // mostrar todos é melhor do que deixar o paciente sem opção ao voltar.
        if ($profissionais->isEmpty() && $especialidadeId) {
            $profissionais = Professional::with('specialties')
                ->where('is_active', true)
                ->orderBy('nome')
                ->get();
        }

        if ($profissionais->isEmpty()) {
            return 'Não há profissionais para esta especialidade.';
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidades = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidades !== '' ? " ({$especialidades})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return "Escolha o profissional:\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoProcedimento(): string
    {
        $procedimentos = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->take(self::MAXIMO_PROCEDIMENTOS)
            ->get();

        if ($procedimentos->isEmpty()) {
            return 'Não há procedimentos cadastrados.';
        }

        $lista = $procedimentos
            ->map(fn (Procedure $p, int $i) => ($i + 1).". {$p->nome} ({$p->duracao_minutos} min)")
            ->implode("\n");

        return "Qual procedimento?\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoData(array $dados): string
    {
        $profissionalId = $dados['profissional_id'] ?? null;
        $duracao = $dados['duracao'] ?? null;

        if (! $profissionalId || ! $duracao) {
            return 'Escolha primeiro o profissional e o procedimento.';
        }

        $agenda = $this->disponibilidade->horariosLivres(
            (int) $profissionalId,
            (int) $duracao,
            self::DIAS_DE_AGENDA,
        );

        $dias = $this->diasComHorarios($agenda);

        if ($dias === []) {
            return 'Sem horários livres nos próximos dias.';
        }

        $lista = collect($dias)
            ->map(fn (array $dia, int $i) => ($i + 1).". {$dia['data']} ({$dia['dia_semana']}) — {$dia['quantidade']} horário(s)")
            ->implode("\n");

        return "Escolha o dia:\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoHorario(array $dados): string
    {
        $profissionalId = $dados['profissional_id'] ?? null;
        $duracao = $dados['duracao'] ?? null;
        $data = $dados['data_escolhida'] ?? null;

        if (! $profissionalId || ! $duracao || ! $data) {
            return 'Escolha primeiro o profissional, procedimento e data.';
        }

        $horarios = $this->disponibilidade->horariosDoDia(
            (int) $profissionalId,
            (int) $duracao,
            $data,
            self::DIAS_DE_AGENDA,
        );

        if ($horarios === []) {
            return 'Esse dia não tem mais horários livres.';
        }

        $visiveis = collect($horarios)
            ->take(self::MAXIMO_HORARIOS)
            ->values();

        $lista = $visiveis
            ->map(fn (string $hora, int $i) => ($i + 1).". {$hora}")
            ->implode("\n");

        return "Horários livres em {$data}:\n\n{$lista}\n\nResponda com o número.";
    }

    /**
     * Lista especialidades e guarda a escolhida para filtrar profissionais.
     */
    protected function especialidade(array $dados, ?string $numero, ?int $opcao): array
    {
        if ($opcao === null) {
            return ['especialidade', $dados, $this->textoEspecialidade()];
        }

        $especialidades = $this->especialidadesVisiveis();

        if ($especialidades->isEmpty()) {
            return $this->listaDeProfissionais($dados);
        }

        // Última opção é "Descrever o que sinto".
        if ($opcao === $especialidades->count()) {
            return ['sintoma', $dados, $this->textoSintoma()];
        }

        $escolhida = $especialidades[$opcao] ?? null;

        if (! $escolhida) {
            return ['especialidade', $dados, 'Opção inválida. Responda com o número da especialidade.'];
        }

        return $this->profissionaisDaEspecialidade($escolhida, $dados);
    }

    /**
     * @return Collection<int, Specialty>
     */
    protected function especialidadesVisiveis(): Collection
    {
        return Specialty::orderBy('name')
            ->take(self::MAXIMO_ESPECIALIDADES)
            ->get();
    }

    /**
     * Profissionais de uma especialidade, com queda para todos os ativos quando
     * o vínculo profissional-especialidade ainda não existe na clínica.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function profissionaisDaEspecialidade(Specialty $especialidade, array $dados): array
    {
        $profissionais = Professional::with('specialties')
            ->where('is_active', true)
            ->whereHas('specialties', fn ($query) => $query->where('specialties.id', $especialidade->id))
            ->orderBy('nome')
            ->get();

        $nota = "Certo, {$especialidade->name}. ";

        if ($profissionais->isEmpty()) {
            // Especialidade sem profissional vinculado não pode ser um beco sem
            // saída: mostra todos os ativos, como o fluxo fazia antes da etapa
            // de especialidade.
            $profissionais = Professional::with('specialties')
                ->where('is_active', true)
                ->orderBy('nome')
                ->get();

            $nota = "Ainda não há profissional específico para {$especialidade->name}. ";
        }

        if ($profissionais->isEmpty()) {
            return ['recepcao', $dados, 'Nenhum profissional disponível para agendamento agora. Vou avisar a recepção.'];
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidadesNomes = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidadesNomes !== '' ? " ({$especialidadesNomes})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return [
            'profissional',
            array_merge($dados, [
                'especialidade_id' => $especialidade->id,
                'especialidade' => $especialidade->name,
                'profissionais' => $profissionais->map(fn (Professional $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                ])->all(),
            ]),
            $nota."Escolha o profissional:\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * Texto da etapa de sintoma.
     */
    protected function textoSintoma(): string
    {
        return "Descreva o que você está sentindo.\n\nExemplos: dor de dente, limpeza, aparelho, implante, gengiva sangrando.\n\nResponda com a sua queixa ou digite 'menu' para voltar.";
    }

    /**
     * Busca a especialidade pelo sintoma descrito pelo paciente e avança para
     * os profissionais dela.
     *
     * @param  string  $texto  O que o paciente digitou (texto livre)
     * @param  array<string, mixed>  $dados  Estado da conversa
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function sintoma(string $texto, array $dados): array
    {
        $textoNormalizado = mb_strtolower(trim($texto));

        foreach (self::SINTOMAS_POR_ESPECIALIDADE as $sintomas => $fragmentos) {
            foreach (explode('|', $sintomas) as $termo) {
                if (mb_strpos($textoNormalizado, mb_strtolower(trim($termo))) === false) {
                    continue;
                }

                $especialidade = $this->especialidadePorFragmentos($fragmentos);

                if ($especialidade !== null) {
                    return $this->profissionaisDaEspecialidade($especialidade, $dados);
                }

                break;
            }
        }

        // Sem casamento: volta à lista de especialidades em vez de insistir na
        // mesma pergunta. O paciente que escreveu algo fora do mapa ainda
        // consegue escolher pelo número.
        return [
            'especialidade',
            $dados,
            "Não consegui identificar a especialidade pelo que você escreveu.\n\n".$this->textoEspecialidade(),
        ];
    }

    /**
     * Procura a especialidade da clínica por fragmento de nome, na ordem em que
     * os fragmentos foram declarados.
     *
     * @param  list<string>  $fragmentos
     */
    protected function especialidadePorFragmentos(array $fragmentos): ?Specialty
    {
        $especialidades = $this->especialidadesVisiveis();

        foreach ($fragmentos as $fragmento) {
            $encontrada = $especialidades->first(
                fn (Specialty $especialidade) => mb_stripos($especialidade->name, $fragmento) !== false,
            );

            if ($encontrada !== null) {
                return $encontrada;
            }
        }

        return null;
    }

    /**
     * Confirma a consulta do paciente para amanhã (resposta ao lembrete de 24h).
     *
     * @param  string  $telefone  Telefone do paciente (apenas dígitos)
     * @param  array<string, mixed>  $dados  Estado da conversa
     * @param  string|null  $nome  Nome do paciente
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function confirmarPresenca(string $telefone, array $dados, ?string $nome): array
    {
        $paciente = $this->paciente($telefone);

        if (! $paciente) {
            return ['menu', $this->base($dados), 'Não consegui identificar seu cadastro. Responda "menu" para começar novamente.'];
        }

        $amanha = Carbon::tomorrow()->startOfDay();
        $statusIds = AppointmentStatus::query()
            ->whereIn('name', [AppointmentStatus::AGENDADO, AppointmentStatus::CONFIRMADO])
            ->pluck('id');

        $consulta = Appointment::query()
            ->where('paciente_id', $paciente->id)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereDate('inicio', $amanha)
            ->orderBy('inicio')
            ->first();

        if (! $consulta) {
            return ['menu', $this->base($dados), "Não há consulta agendada para amanhã em seu nome.\n\n".$this->menu($nome ?? null)];
        }

        $statusConfirmado = AppointmentStatus::idFor(AppointmentStatus::CONFIRMADO);
        $consulta->update(['appointment_status_id' => $statusConfirmado]);

        AuditLogger::log('confirmar', 'appointments', $consulta->id, depois: ['appointment_status_id' => $statusConfirmado]);

        $hora = $consulta->inicio->format('H:i');
        $profissional = $consulta->profissional?->nome ?? 'profissional';

        return [
            'menu',
            $this->base($dados),
            "✅ Consulta confirmada!\n\n📅 Amanhã às {$hora}\n👤 {$profissional}\n\nObrigado por confirmar. Até lá!\n\n".$this->menu($nome ?? null),
        ];
    }

    /**
     * Mostra as consultas futuras do paciente.
     */
    protected function minhasConsultas(array $dados, ?string $numero, ?int $opcao): array
    {
        if ($opcao === null) {
            return ['minhas_consultas', $dados, $this->minhasConsultasTexto($dados)];
        }

        // Qualquer número volta ao menu.
        return ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)];
    }

    protected function minhasConsultasTexto(array $dados): string
    {
        $pacienteId = $dados['paciente_id'] ?? null;

        if (! $pacienteId) {
            return 'Não consegui identificar seu cadastro. Responda "menu" para começar novamente.';
        }

        $consultas = Appointment::with(['profissional', 'status'])
            ->where('paciente_id', $pacienteId)
            ->where('inicio', '>=', now())
            ->orderBy('inicio')
            ->get()
            // Cancelada não é consulta a mostrar: o paciente não pode chegar no
            // consultório por causa de uma linha que já foi desmarcada.
            ->reject(fn (Appointment $c) => $c->status?->name === AppointmentStatus::CANCELADO);

        if ($consultas->isEmpty()) {
            return "Você não tem consultas agendadas.\n\n".$this->menu($dados['nome'] ?? null);
        }

        $linhas = $consultas->map(function (Appointment $c): string {
            $statusNome = $c->status?->label ?? $c->status?->name ?? 'Agendado';

            return "📅 {$c->inicio->format('d/m H:i')} — {$c->profissional?->nome} ({$statusNome})";
        })->implode("\n");

        return "Suas consultas:\n\n{$linhas}\n\nResponda \"menu\" para voltar.";
    }

    protected function textoConsentimento(string $nomeClinica): string
    {
        $clinica = $nomeClinica !== '' ? $nomeClinica : 'clínica';

        return "Olá! Sou a assistente virtual da {$clinica}.\n\n"
            .'Usamos o WhatsApp para atendimento e agendamento. Seus dados são tratados conforme a LGPD e usados apenas para esse fim.'."\n\n"
            ."Deseja continuar?\n\n1️⃣ Sim, continuar\n2️⃣ Falar com a recepção";
    }

    protected function telefone(string $remoteJid): string
    {
        return preg_replace('/\D/', '', $remoteJid);
    }

    /**
     * @return array{ok: bool, etapa_de: string, etapa_para: string, resposta: string}
     */
    protected function resultado(string $de, string $para, string $resposta): array
    {
        return [
            'ok' => true,
            'etapa_de' => $de,
            'etapa_para' => $para,
            'resposta' => $resposta,
        ];
    }
}
