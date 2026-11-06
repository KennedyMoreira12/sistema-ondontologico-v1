<?php

namespace App\Support\Modulos;

/**
 * Catálogo único dos módulos do LJ SmartClinic.
 *
 * Este é o lugar onde o produto descreve UM módulo: como ele se chama, qual
 * ícone usa, em qual seção do menu aparece, para qual tela leva e em que
 * situação está para cada vertical.
 *
 * O menu lateral, a rota padrão de módulo e a tela de "funcionalidade
 * planejada" leem todos daqui. Isso evita o estado em que o menu promete uma
 * coisa e a rota entrega outra.
 *
 * SITUAÇÃO DE UM MÓDULO
 * ---------------------
 * - `disponivel`: existe tela funcionando para a vertical da clínica.
 * - `planejado`:  o módulo faz parte do produto, mas a tela ainda não foi
 *   adaptada para a vertical da clínica. Nesse caso o menu leva para a tela
 *   padrão "Funcionalidade planejada — ainda não disponível para uso."
 *
 * A vertical de um módulo nunca vem do navegador: ela é lida do registro
 * central da clínica (ver `VerticalManager`).
 */
class CatalogoModulos
{
    /** Módulo com tela funcionando para a vertical da clínica. */
    public const DISPONIVEL = 'disponivel';

    /** Módulo previsto no produto, ainda sem tela para a vertical da clínica. */
    public const PLANEJADO = 'planejado';

    /** Módulo disponível para as duas verticais. */
    public const COMUM = 'comum';

    /** Módulo exclusivo da vertical odontológica. */
    public const ODONTOLOGIA = 'odontologia';

    /** Módulo exclusivo da vertical de fisioterapia. */
    public const FISIOTERAPIA = 'fisioterapia';

    /** Seção "Principal" do menu. */
    public const GRUPO_PRINCIPAL = 'principal';

    /** Seção "Gestão" do menu. */
    public const GRUPO_GESTAO = 'gestao';

    /**
     * Os 12 módulos padronizados: o núcleo clínico da clínica.
     *
     * São os módulos que toda clínica tem, independentemente da vertical, e
     * que por isso recebem o mesmo tratamento (menu, estado e tela padrão).
     *
     * @var list<string>
     */
    public const MODULOS_PADRONIZADOS = [
        'pacientes',
        'agenda',
        'prontuario',
        'odontograma',
        'avaliacao',
        'evolucao',
        'pacotes',
        'tratamentos',
        'orcamentos',
        'financeiro',
        'convenios',
        'profissionais',
    ];

    /**
     * Todos os módulos do menu da clínica.
     *
     * A ordem é a ordem em que os módulos aparecem no menu.
     *
     * @var array<string, array{titulo: string, icone: string, grupo: string, tipo: string, rota: string, padrao: string, vertical: string, situacao: array<string, string>, descricao: string}>
     */
    public const MODULOS = [
        'pacientes' => [
            'titulo' => 'Pacientes',
            'icone' => 'bi-people',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'lista',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Cadastro, ficha clínica e histórico de atendimento dos pacientes.',
        ],
        'agenda' => [
            'titulo' => 'Agenda',
            'icone' => 'bi-calendar3',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'lista',
            'rota' => 'agenda.index',
            'padrao' => 'agenda.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Consultas, sessões e lista de espera.',
        ],
        'atendimentos' => [
            'titulo' => 'Atendimentos',
            'icone' => 'bi-clipboard2-check',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'lista',
            'rota' => 'atendimentos.index',
            'padrao' => 'atendimentos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Recepção do dia: presença, falta e consumo de sessão do pacote.',
        ],
        'prontuario' => [
            'titulo' => 'Prontuário',
            'icone' => 'bi-journal-medical',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.prontuario',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Anamnese, alertas clínicos e notas de atendimento por paciente.',
        ],
        'odontograma' => [
            'titulo' => 'Odontograma',
            'icone' => 'bi-grid-3x3-gap',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.odontograma.*',
            'vertical' => self::ODONTOLOGIA,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::PLANEJADO,
            ],
            'descricao' => 'Mapa dentário, condições e histórico de procedimentos por paciente.',
        ],
        'avaliacao' => [
            'titulo' => 'Avaliação',
            'icone' => 'bi-clipboard2-pulse',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.avaliacoes.*',
            'vertical' => self::FISIOTERAPIA,
            'situacao' => [
                self::ODONTOLOGIA => self::PLANEJADO,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Ficha de avaliação inicial, com registro próprio e auditoria.',
        ],
        'evolucao' => [
            'titulo' => 'Evolução',
            'icone' => 'bi-activity',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.evolucoes-sessao.*',
            'vertical' => self::FISIOTERAPIA,
            'situacao' => [
                self::ODONTOLOGIA => self::PLANEJADO,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Evolução por sessão de fisioterapia, com registro imutável após o cierre.',
        ],
        'pacotes' => [
            'titulo' => 'Pacotes de sessões',
            'icone' => 'bi-box-seam',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.pacotes.*',
            'vertical' => self::FISIOTERAPIA,
            'situacao' => [
                self::ODONTOLOGIA => self::PLANEJADO,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Pacotes de sessões contratados, com consumo transacional e sem saldo negativo.',
        ],
        'tratamentos' => [
            'titulo' => 'Tratamentos',
            'icone' => 'bi-clipboard2-pulse',
            'grupo' => self::GRUPO_PRINCIPAL,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.planos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                // O plano de tratamento atual é odontológico (o item liga o
                // procedimento a um dente). Para a fisioterapia ele ainda não
                // foi adaptado, então aparece como planejado.
                self::FISIOTERAPIA => self::PLANEJADO,
            ],
            'descricao' => 'Plano de tratamento do paciente, com itens, valores e evolução do status.',
        ],
        'orcamentos' => [
            'titulo' => 'Orçamentos',
            'icone' => 'bi-file-earmark-text',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.orcamentos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Orçamentos e propostas de tratamento por paciente.',
        ],
        'financeiro' => [
            'titulo' => 'Financeiro',
            'icone' => 'bi-cash-stack',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'financeiro.index',
            'padrao' => 'financeiro.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Contas a receber, baixa de pagamento e contas da clínica.',
        ],
        'convenios' => [
            'titulo' => 'Convênios',
            'icone' => 'bi-shield-check',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'convenios.index',
            'padrao' => 'convenios.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Operadoras de convênio e regras de atendimento.',
        ],
        'profissionais' => [
            'titulo' => 'Profissionais',
            'icone' => 'bi-person-badge',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'profissionais.index',
            'padrao' => 'profissionais.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Equipe da clínica, com o registro do conselho profissional.',
        ],
        'procedimentos' => [
            'titulo' => 'Procedimentos',
            'icone' => 'bi-journal-code',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'procedimentos.index',
            'padrao' => 'procedimentos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Catálogo de procedimentos e valores praticados.',
        ],
        'estoque' => [
            'titulo' => 'Estoque',
            'icone' => 'bi-box-seam',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'estoque.index',
            'padrao' => 'estoque.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Produtos, materiais e movimentações de estoque.',
        ],
        'contratos' => [
            'titulo' => 'Contratos',
            'icone' => 'bi-file-earmark-check',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'contratos.index',
            'padrao' => 'contratos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Contratos com pacientes e responsáveis.',
        ],
        'relatorios' => [
            'titulo' => 'Relatórios',
            'icone' => 'bi-graph-up-arrow',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'lista',
            'rota' => 'relatorios.index',
            'padrao' => 'relatorios.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Indicadores de atendimento, financeiro e estoque.',
        ],
        'documentos' => [
            'titulo' => 'Documentos',
            'icone' => 'bi-folder2-open',
            'grupo' => self::GRUPO_GESTAO,
            'tipo' => 'paciente',
            'rota' => 'pacientes.index',
            'padrao' => 'pacientes.documentos.*',
            'vertical' => self::COMUM,
            'situacao' => [
                self::ODONTOLOGIA => self::DISPONIVEL,
                self::FISIOTERAPIA => self::DISPONIVEL,
            ],
            'descricao' => 'Documentos do paciente, guardados por clínica e com download controlado.',
        ],
    ];

    /**
     * Todos os módulos do catálogo.
     *
     * @return array<string, array<string, mixed>>
     */
    public function todos(): array
    {
        return self::MODULOS;
    }

    /**
     * Os 12 módulos padronizados (núcleo clínico).
     *
     * @return array<string, array<string, mixed>>
     */
    public function padronizados(): array
    {
        return array_intersect_key(self::MODULOS, array_flip(self::MODULOS_PADRONIZADOS));
    }

    /**
     * Módulos de um grupo do menu, na ordem do catálogo.
     *
     * @return array<string, array<string, mixed>>
     */
    public function doGrupo(string $grupo): array
    {
        return array_filter(self::MODULOS, fn (array $modulo): bool => $modulo['grupo'] === $grupo);
    }

    /**
     * O módulo existe no catálogo?
     */
    public function existe(string $slug): bool
    {
        return isset(self::MODULOS[$slug]);
    }

    /**
     * Dados de um módulo (null quando o slug é desconhecido).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return self::MODULOS[$slug] ?? null;
    }

    /**
     * A vertical à qual o módulo pertence (`comum` para os dois).
     *
     * Null quando o slug não está no catálogo.
     */
    public function verticalDoModulo(string $slug): ?string
    {
        return self::MODULOS[$slug]['vertical'] ?? null;
    }

    /**
     * Módulos exclusivos de uma vertical.
     *
     * @return list<string>
     */
    public function modulosDaVertical(string $vertical): array
    {
        return array_values(array_keys(array_filter(
            self::MODULOS,
            fn (array $modulo): bool => $modulo['vertical'] === $vertical,
        )));
    }

    /**
     * Situação do módulo para a vertical da clínica.
     *
     * Um módulo desconhecido ou de vertical incompatível fica `disponivel`
     * aqui: quem decide se a clínica pode abrir o módulo é o `VerticalManager`,
     * e ele é consultado antes.
     */
    public function situacao(string $slug, string $vertical): string
    {
        return self::MODULOS[$slug]['situacao'][$vertical] ?? self::DISPONIVEL;
    }

    /**
     * Rota para onde o módulo leva.
     */
    public function rota(string $slug): ?string
    {
        return self::MODULOS[$slug]['rota'] ?? null;
    }

    /**
     * Nome legível do módulo.
     */
    public function titulo(string $slug): string
    {
        return self::MODULOS[$slug]['titulo'] ?? self::tituloGenerico($slug);
    }

    /**
     * Descrição do que o módulo faz, quando o catálogo a descreve.
     */
    public function descricao(string $slug): ?string
    {
        return self::MODULOS[$slug]['descricao'] ?? null;
    }

    /**
     * Título para um módulo que não está no catálogo.
     */
    public function tituloGenerico(string $slug): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $slug));
    }
}
