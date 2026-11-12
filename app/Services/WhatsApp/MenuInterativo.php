<?php

namespace App\Services\WhatsApp;

use App\Models\Central\Clinic;
use App\Models\Specialty;
use App\Services\EvolutionService;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Menus de botões e listas do WhatsApp, com o texto numerado como fallback.
 *
 * A Evolution 2.3.7 instalada aceita `sendButtons` e `sendList`, mas isso não
 * garante que o aparelho do paciente renderize botões: o suporte depende do
 * cliente de WhatsApp e da conta. Por isso este serviço NUNCA substitui o texto
 * — ele constrói o menu interativo e, no mesmo objeto, sabe gerar a versão
 * numerada que o fluxo determinístico já usa. Quando o envio interativo é
 * recusado, o fallback é enviado no lugar.
 *
 * O contrato exposto ao n8n é o desta classe (`tipo`, `titulo`, `descricao`,
 * `rodape`, `botao_lista`, `opcoes`), e não o payload cru da Evolution. Assim a
 * troca de versão do gateway fica em `payload()`, sem obrigar o workflow a
 * conhecer `footerText`, `sections` e `rowId`.
 *
 * Os limites (3 botões, 10 linhas, títulos curtos) não são estética: são o que
 * o WhatsApp aceita. Passar disso faz a Evolution recusar a mensagem inteira, e
 * o paciente não recebe nem o menu nem o fallback.
 */
class MenuInterativo
{
    public const TIPO_BOTOES = 'buttons';

    public const TIPO_LISTA = 'list';

    /**
     * Menu para a etapa em que a conversa acabou de entrar.
     *
     * A lista de opções vem do estado gravado, e não do texto da resposta, para
     * que a numeração do texto e o `id` do botão apontem para a mesma escolha.
     * Um estado sem opções (etapa de recepção, cadastro, confirmação) devolve
     * `null`: nem toda resposta é um menu.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>|null
     */
    public function paraEtapa(?string $etapa, array $dados, string $nomeClinica = ''): ?array
    {
        $opcoes = match ($etapa) {
            'consentimento' => [
                ['id' => '1', 'titulo' => 'Sim, continuar', 'descricao' => 'Aceito o uso dos meus dados para atendimento.'],
                ['id' => '2', 'titulo' => 'Falar com a recepção', 'descricao' => 'Quero atendimento humano.'],
            ],
            'menu' => [
                ['id' => '1', 'titulo' => 'Agendar consulta', 'descricao' => 'Escolher especialidade, profissional e horário.'],
                ['id' => '2', 'titulo' => 'Minhas consultas', 'descricao' => 'Ver consultas agendadas.'],
                ['id' => '3', 'titulo' => 'Falar com a recepção', 'descricao' => 'Quero atendimento humano.'],
            ],
            'especialidade' => $this->dasEspecialidades($dados),
            'profissional' => $this->dasProfissionais($dados),
            'procedimento' => $this->dosProcedimentos($dados),
            'data' => $this->dosDias($dados),
            'horario' => $this->dosHorarios($dados),
            default => [],
        };

        if ($opcoes === []) {
            return null;
        }

        return $this->montar(
            $etapa,
            $this->tituloDaEtapa((string) $etapa, $dados),
            $this->descricaoDaEtapa((string) $etapa),
            $opcoes,
            $nomeClinica,
        );
    }

    /**
     * Limites de um menu recebido de fora (n8n ou painel).
     *
     * O que chega por API é normalizado aqui, e não confiado: um menu com 30
     * linhas ou título vazio é recusado pelo WhatsApp e derrubaria o turno
     * inteiro. Textos longos são cortados; estrutura inválida estoura como
     * `InvalidArgumentException`, que o controller converte em 422.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function normalizar(array $dados): array
    {
        $tipo = $dados['tipo'] ?? self::TIPO_BOTOES;

        if (! in_array($tipo, [self::TIPO_BOTOES, self::TIPO_LISTA], true)) {
            throw new InvalidArgumentException('Tipo de menu interativo inválido.');
        }

        $opcoes = $dados['opcoes'] ?? [];

        if (! is_array($opcoes) || $opcoes === []) {
            throw new InvalidArgumentException('O menu interativo precisa de pelo menos uma opção.');
        }

        $maximo = $tipo === self::TIPO_BOTOES
            ? $this->maximoBotoes()
            : $this->maximoLinhas();

        if (count($opcoes) > $maximo) {
            throw new InvalidArgumentException("O menu do tipo {$tipo} aceita no máximo {$maximo} opções.");
        }

        $ids = [];
        $normalizadas = [];

        foreach (array_values($opcoes) as $indice => $opcao) {
            if (! is_array($opcao)) {
                throw new InvalidArgumentException('Cada opção do menu precisa ser um objeto.');
            }

            $id = trim((string) ($opcao['id'] ?? ''));
            $titulo = trim((string) ($opcao['titulo'] ?? ''));

            if ($id === '' || $titulo === '') {
                throw new InvalidArgumentException('Cada opção precisa de id e titulo.');
            }

            if (isset($ids[$id])) {
                throw new InvalidArgumentException("O id de opção [{$id}] está repetido.");
            }

            $ids[$id] = true;

            $item = [
                'id' => Str::limit($id, 64, ''),
                'titulo' => Str::limit($titulo, $tipo === self::TIPO_BOTOES ? 20 : 24, ''),
            ];

            $descricao = trim((string) ($opcao['descricao'] ?? ''));

            if ($descricao !== '') {
                $item['descricao'] = Str::limit($descricao, 72, '');
            }

            $normalizadas[] = $item;
        }

        return $this->montar(
            (string) ($dados['etapa'] ?? 'menu'),
            (string) ($dados['titulo'] ?? 'Como posso ajudar?'),
            (string) ($dados['descricao'] ?? ''),
            $normalizadas,
            (string) ($dados['rodape'] ?? ''),
            $tipo,
        );
    }

    /**
     * Payload aceito por `POST /message/sendButtons` e `POST /message/sendList`.
     *
     * As chaves são as da Evolution 2.3.7, verificadas no schema instalado:
     * `footer` (botões) vs `footerText` (lista), `buttonText` obrigatório na
     * lista e `sections`/`rows` com `rowId`. Mandar o contrato interno direto
     * para a Evolution produz 400 sem explicar qual campo faltou.
     *
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    public function payload(array $menu): array
    {
        $titulo = (string) ($menu['titulo'] ?? '');
        $descricao = (string) ($menu['descricao'] ?? '');
        $rodape = (string) ($menu['rodape'] ?? '');
        $opcoes = is_array($menu['opcoes'] ?? null) ? $menu['opcoes'] : [];

        if (($menu['tipo'] ?? '') === self::TIPO_BOTOES) {
            return [
                'title' => $titulo,
                'description' => $descricao,
                'footer' => $rodape,
                'buttons' => array_map(static fn (array $opcao): array => [
                    'type' => 'reply',
                    'displayText' => (string) $opcao['titulo'],
                    'id' => (string) $opcao['id'],
                ], $opcoes),
            ];
        }

        return [
            'title' => $titulo,
            'description' => $descricao,
            // A Evolution exige `footerText` não vazio na lista.
            'footerText' => $rodape !== '' ? $rodape : 'Atendimento WhatsApp',
            'buttonText' => Str::limit((string) ($menu['botao_lista'] ?? 'Ver opções'), 20, ''),
            'sections' => [[
                'title' => Str::limit($titulo, 24, ''),
                'rows' => array_map(static function (array $opcao): array {
                    $linha = [
                        'title' => (string) $opcao['titulo'],
                        'rowId' => (string) $opcao['id'],
                    ];

                    if (($opcao['descricao'] ?? '') !== '') {
                        $linha['description'] = (string) $opcao['descricao'];
                    }

                    return $linha;
                }, $opcoes),
            ]],
        ];
    }

    /**
     * O mesmo menu em texto numerado, que é o fallback obrigatório.
     *
     * @param  array<string, mixed>  $menu
     */
    public function texto(array $menu): string
    {
        $linhas = collect(is_array($menu['opcoes'] ?? null) ? $menu['opcoes'] : [])
            ->values()
            ->map(fn (array $opcao, int $indice): string => ($indice + 1).'. '.$opcao['titulo'])
            ->implode("\n");

        $partes = array_filter([
            (string) ($menu['titulo'] ?? ''),
            (string) ($menu['descricao'] ?? ''),
            $linhas,
        ], static fn (string $parte): bool => trim($parte) !== '');

        return implode("\n\n", $partes)."\n\nResponda com o número.";
    }

    /**
     * Envia o menu pela Evolution conforme o tipo.
     *
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    public function enviar(EvolutionService $evolution, Clinic $clinica, string $telefone, array $menu): array
    {
        $payload = $this->payload($menu);

        return ($menu['tipo'] ?? '') === self::TIPO_BOTOES
            ? $evolution->sendButtons($clinica, $telefone, $payload)
            : $evolution->sendList($clinica, $telefone, $payload);
    }

    /**
     * @param  list<array<string, mixed>>  $opcoes
     * @return array<string, mixed>
     */
    protected function montar(
        string $etapa,
        string $titulo,
        string $descricao,
        array $opcoes,
        string $nomeClinica = '',
        ?string $tipoForcado = null,
    ): array {
        $tipo = $tipoForcado;

        if (! in_array($tipo, [self::TIPO_BOTOES, self::TIPO_LISTA], true)) {
            $tipo = count($opcoes) <= $this->maximoBotoes()
                ? self::TIPO_BOTOES
                : self::TIPO_LISTA;
        }

        $limiteTitulo = $tipo === self::TIPO_BOTOES ? 20 : 24;

        $vistos = [];

        $menu = [
            'etapa' => $etapa,
            'tipo' => $tipo,
            'titulo' => Str::limit(trim($titulo), 60, ''),
            'descricao' => Str::limit(trim($descricao), 1024, ''),
            'rodape' => Str::limit(trim($nomeClinica), 60, '') ?: 'Atendimento WhatsApp',
            'opcoes' => array_map(function (array $opcao) use ($limiteTitulo, &$vistos): array {
                $item = [
                    'id' => Str::limit((string) $opcao['id'], 64, ''),
                    'titulo' => $this->tituloUnico((string) $opcao['titulo'], $limiteTitulo, $vistos),
                ];

                if (($opcao['descricao'] ?? '') !== '') {
                    $item['descricao'] = Str::limit((string) $opcao['descricao'], 72, '');
                }

                return $item;
            }, $opcoes),
        ];

        if ($tipo === self::TIPO_LISTA) {
            $menu['botao_lista'] = 'Ver opções';
        }

        return $menu;
    }

    /**
     * O id da opção é a POSIÇÃO na lista, não o id do banco.
     *
     * O fluxo já escolhe por número (`itemEscolhido`), e usar a posição mantém
     * botão, lista e texto apontando para o mesmo item. O id do profissional
     * fica no estado; o `rowId` que volta no webhook é a posição que o paciente
     * viu.
     *
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    protected function dasProfissionais(array $dados): array
    {
        $profissionais = is_array($dados['profissionais'] ?? null) ? $dados['profissionais'] : [];

        return $this->mapear(
            array_slice(array_values($profissionais), 0, $this->maximoLinhas()),
            static fn (array $profissional, int $indice): array => [
                'id' => (string) ($indice + 1),
                'titulo' => (string) $profissional['nome'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    protected function dasEspecialidades(array $dados): array
    {
        $limite = $this->maximoLinhas();

        // Uma linha fica reservada para "Descrever o que sinto", que é a última
        // opção também na versão textual da etapa. Sem ela, quem usa o menu
        // interativo não teria como descrever o sintoma.
        $especialidades = Specialty::orderBy('name')
            ->take(max(1, $limite - 1))
            ->get()
            ->values();

        $opcoes = $especialidades
            ->map(fn (Specialty $e, int $i) => [
                'id' => (string) ($i + 1),
                'titulo' => (string) $e->name,
            ])
            ->all();

        $opcoes[] = [
            'id' => (string) (count($opcoes) + 1),
            'titulo' => 'Descrever o que sinto',
        ];

        return $this->mapear(
            $opcoes,
            static fn (array $opcao): array => $opcao,
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    protected function dosProcedimentos(array $dados): array
    {
        $procedimentos = is_array($dados['procedimentos'] ?? null) ? $dados['procedimentos'] : [];

        return $this->mapear(
            array_slice(array_values($procedimentos), 0, $this->maximoLinhas()),
            static fn (array $procedimento, int $indice): array => [
                'id' => (string) ($indice + 1),
                'titulo' => $procedimento['nome'].' ('.$procedimento['duracao'].' min)',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    protected function dosDias(array $dados): array
    {
        $dias = is_array($dados['dias'] ?? null) ? $dados['dias'] : [];

        return $this->mapear(
            array_slice(array_values($dias), 0, $this->maximoLinhas()),
            fn (array $dia, int $indice): array => [
                'id' => (string) ($indice + 1),
                'titulo' => $this->diaCurto((string) $dia['data'], (string) $dia['dia_semana']),
                'descricao' => $dia['quantidade'].' horário(s) livre(s)',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    protected function dosHorarios(array $dados): array
    {
        $horarios = is_array($dados['horarios'] ?? null) ? $dados['horarios'] : [];

        return $this->mapear(
            array_slice(array_values($horarios), 0, $this->maximoLinhas()),
            static fn (string $hora, int $indice): array => [
                'id' => (string) ($indice + 1),
                'titulo' => $hora,
            ],
        );
    }

    /**
     * @param  list<mixed>  $itens
     * @param  callable(mixed, int): array<string, mixed>  $construtor
     * @return list<array<string, mixed>>
     */
    protected function mapear(array $itens, callable $construtor): array
    {
        $opcoes = [];

        foreach ($itens as $indice => $item) {
            $opcoes[] = $construtor($item, $indice);
        }

        return $opcoes;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function tituloDaEtapa(string $etapa, array $dados): string
    {
        return match ($etapa) {
            'consentimento' => 'Antes de começar',
            'menu' => 'Como posso ajudar?',
            'especialidade' => 'Escolha a especialidade',
            'profissional' => 'Escolha o profissional',
            'procedimento' => 'Escolha o procedimento',
            'data' => 'Escolha o dia',
            'horario' => 'Escolha o horário',
            default => 'Escolha uma opção',
        };
    }

    protected function descricaoDaEtapa(string $etapa): string
    {
        return match ($etapa) {
            'consentimento' => 'Usamos o WhatsApp para atendimento e agendamento. Seus dados são tratados conforme a LGPD.',
            'data' => 'Toque em um dia para ver os horários.',
            default => 'Toque em uma opção abaixo.',
        };
    }

    /**
     * Títulos repetidos fazem a Evolution recusar o menu INTEIRO.
     *
     * Dois profissionais com o mesmo nome, ou dois nomes que ficam iguais
     * depois do corte em 24 caracteres, não podem virar dois botões com o mesmo
     * texto. O sufixo numérico mantém a mensagem válida sem esconder que são
     * opções diferentes — e o `id` é quem carrega a escolha de verdade.
     *
     * @param  array<string, true>  $vistos
     */
    protected function tituloUnico(string $titulo, int $limite, array &$vistos): string
    {
        $base = Str::limit(trim($titulo), $limite, '');
        $candidato = $base;
        $sequencia = 2;

        while (isset($vistos[$candidato])) {
            $sufixo = ' ·'.$sequencia;
            $candidato = Str::limit($base, max(1, $limite - mb_strlen($sufixo)), '').$sufixo;
            $sequencia++;
        }

        $vistos[$candidato] = true;

        return $candidato;
    }

    protected function diaCurto(string $data, string $diaSemana): string
    {
        $formatada = date_create($data);
        $rotulo = $formatada !== false ? $formatada->format('d/m') : $data;

        return Str::limit(trim($rotulo.' · '.$diaSemana), 24, '');
    }

    protected function maximoBotoes(): int
    {
        return max(1, (int) config('whatsapp.interactive.max_buttons', 3));
    }

    protected function maximoLinhas(): int
    {
        return max(1, (int) config('whatsapp.interactive.max_list_rows', 10));
    }
}
