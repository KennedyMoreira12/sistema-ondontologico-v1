<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funil de vendas
    |--------------------------------------------------------------------------
    |
    | As chaves são gravadas em `prospects.status` e `prospect_interactions`.
    | Ficam em config (e não num enum em código) pelo mesmo motivo que as
    | features de plano: ajustar a nomenclatura comercial não deveria exigir
    | deploy, e o importador compara contra esta lista em vez de strings soltas.
    |
    */

    'statuses' => [
        'nao_contatado' => ['nome' => 'Não contatado', 'grupo' => 'aberto', 'ordem' => 1],
        'primeiro_contato' => ['nome' => 'Primeiro contato', 'grupo' => 'aberto', 'ordem' => 2],
        'respondeu' => ['nome' => 'Respondeu', 'grupo' => 'ativo', 'ordem' => 3],
        'interessado' => ['nome' => 'Interessado', 'grupo' => 'ativo', 'ordem' => 4],
        'reuniao_marcada' => ['nome' => 'Reunião marcada', 'grupo' => 'ativo', 'ordem' => 5],
        'proposta_enviada' => ['nome' => 'Proposta enviada', 'grupo' => 'ativo', 'ordem' => 6],
        'negociacao' => ['nome' => 'Negociação', 'grupo' => 'ativo', 'ordem' => 7],
        'cliente' => ['nome' => 'Cliente', 'grupo' => 'ganho', 'ordem' => 8],
        'sem_interesse' => ['nome' => 'Sem interesse', 'grupo' => 'perdido', 'ordem' => 9],
        'retornar_depois' => ['nome' => 'Retornar depois', 'grupo' => 'aberto', 'ordem' => 10],
    ],

    'default_status' => 'nao_contatado',

    'priorities' => [
        'alta' => 'Alta',
        'media' => 'Média',
        'baixa' => 'Baixa',
    ],

    'default_priority' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Validação de telefone
    |--------------------------------------------------------------------------
    |
    | `valid`      -> fixo (10 díg.) ou celular brasileiro completo (11 díg. com 9)
    | `incomplete` -> menos dígitos do que o esperado
    | `unconfirmed`-> sem número na fonte, ou formato que não casa com BR
    | `invalid`    -> lixo/número claramente impossível
    |
    */

    'phone_statuses' => [
        'valid' => 'Válido',
        'incomplete' => 'Incompleto',
        'unconfirmed' => 'Não confirmado',
        'invalid' => 'Inválido',
    ],

    'channels' => [
        'whatsapp' => 'WhatsApp',
        'telefone' => 'Telefone',
        'presencial' => 'Presencial',
        'email' => 'E-mail',
        'outro' => 'Outro',
    ],

    'default_city' => 'Januária',
    'default_state' => 'MG',

    /*
    |--------------------------------------------------------------------------
    | Modelos de mensagem comerciais
    |--------------------------------------------------------------------------
    |
    | Semeados uma vez; o corpo fica editável na tela. Placeholders aceitos:
    | {nome}, {categoria}, {cidade} e {remetente}.
    |
    */

    'templates' => [
        'primeiro_contato' => [
            'name' => 'Primeiro contato',
            'body' => 'Olá, {nome}! Aqui é da LJ Automação. Trabalhamos com automação de atendimento no WhatsApp, agendamento e reativação de pacientes em {cidade}. Posso te explicar em 1 minuto como funciona?',
            'sort_order' => 1,
        ],
        'apresentacao' => [
            'name' => 'Apresentação do LJ SmartClinic',
            'body' => 'Oi, {nome}! Somos a LJ Automação. Ajudamos {categoria} a responder mais rápido no WhatsApp, confirmar consultas e reduzir faltas, com atendimento por IA e agendamento automático. Faz sentido para a sua rotina?',
            'sort_order' => 2,
        ],
        'convite_demonstracao' => [
            'name' => 'Convite para demonstração',
            'body' => 'Olá, {nome}! Que tal uma demonstração rápida (15 min) do LJ SmartClinic aplicado ao seu negócio? Te mostro como ficaria o atendimento no seu WhatsApp. Qual o melhor dia e horário para você?',
            'sort_order' => 3,
        ],
        'retorno_interesse' => [
            'name' => 'Retorno após interesse',
            'body' => 'Oi, {nome}! Vi que você demonstrou interesse no LJ SmartClinic. Posso te enviar os detalhes e valores? Se preferir, agendo uma conversa rápida para alinhar o que faz sentido para você.',
            'sort_order' => 4,
        ],
        'envio_proposta' => [
            'name' => 'Envio de proposta',
            'body' => 'Olá, {nome}! Segue a proposta do LJ SmartClinic conforme conversamos. Qualquer dúvida estou à disposição. Posso te ligar para explicar os itens com calma?',
            'sort_order' => 5,
        ],
        'acompanhamento_proposta' => [
            'name' => 'Acompanhamento de proposta',
            'body' => 'Oi, {nome}! Passando para saber se você conseguiu ver a proposta. Ficou alguma dúvida? Consigo ajustar as condições para fecharmos.',
            'sort_order' => 6,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentos de origem
    |--------------------------------------------------------------------------
    |
    | Mapa `source_key` -> nome do PDF guardado em storage/app/prospeccao. É uma
    | lista fixa (allowlist) para que a rota de download escolha entre documentos
    | conhecidos e nunca sirva outro arquivo do servidor.
    |
    */

    'source_documents' => [
        'odontologia_januaria' => 'Lista_Prospecao_LJ_Automacao_Odontologia_Januaria.pdf',
        'saude_januaria_ampliada' => 'Prospecao_LJ_Automacao_Saude_Januaria_Ampliada.pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp de prospecção
    |--------------------------------------------------------------------------
    |
    | Uma instância Evolution EXCLUSIVA para prospecção, sem relação com as
    | instâncias das clínicas. O nome padrão só é usado como sugestão; antes de
    | provisionar, o serviço confere se já existe instância com esse nome.
    |
    */

    'whatsapp' => [
        'default_instance' => 'lj-prospeccao',
        'state_poll_throttle' => '10,1',
        'qrcode_throttle' => '10,1',
    ],
];
