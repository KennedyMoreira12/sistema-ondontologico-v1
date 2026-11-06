<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags por clínica
    |--------------------------------------------------------------------------
    |
    | Os valores abaixo são o PADRÃO de uma clínica que nunca foi configurada.
    | Todos nascem desligados: o comportamento atual continua valendo até que
    | um operador ligue o recurso para uma clínica específica em
    | `clinic_feature_flags` (banco central).
    |
    | Ler sempre por `ClinicFeatures::enabled()`. Consultar a tabela direto
    | ignora o fallback e transforma "flag ausente" em "recurso desligado por
    | acidente" — que é o mesmo que um recurso novo entrar em produção sem
    | ninguém decidir.
    |
    */

    'flags' => [
        'whatsapp_outbox' => false,
        'interactive_menus' => false,
        'reminders' => false,
        'inbox' => false,
        'n8n_optional' => false,
        'ai_summary' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Menus interativos (Fase 3)
    |--------------------------------------------------------------------------
    |
    | Os limites abaixo são do WhatsApp, não escolha de produto: três botões por
    | mensagem, dez linhas por lista e títulos curtos. Passar disso faz a
    | Evolution recusar o menu inteiro — e o paciente não recebe nem o menu nem
    | o fallback. O texto numerado continua sendo gerado em qualquer caso.
    |
    | A flag `interactive_menus` é quem decide se o fluxo devolve o contrato
    | interativo. Desligada, a resposta é exatamente a de antes.
    |
    */

    'interactive' => [
        'max_buttons' => 3,
        'max_list_rows' => 10,
        'button_title' => 20,
        'row_title' => 24,
        'row_description' => 72,
        'button_text' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox de mensagens
    |--------------------------------------------------------------------------
    |
    | A outbox vive no banco da clínica. O job guarda apenas ids; ao rodar,
    | reconecta no banco da clínica dona da mensagem. Isso é o que permite um
    | worker sem contexto de tenant processar a fila de todas as clínicas sem
    | cruzar dados.
    |
    | `QUEUE_CONNECTION=sync` continua funcionando: o envio acontece inline,
    | mas a linha persistida, as tentativas e a DLQ são as mesmas. Em produção
    | com worker, a recuperação periódica cobre job perdido/reinício.
    |
    */

    'outbox' => [
        'queue' => env('WHATSAPP_OUTBOX_QUEUE', 'default'),
        'connection' => env('WHATSAPP_OUTBOX_CONNECTION'),

        'max_attempts' => (int) env('WHATSAPP_OUTBOX_MAX_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('WHATSAPP_OUTBOX_RETRY_BASE_SECONDS', 15),
        'retry_max_seconds' => (int) env('WHATSAPP_OUTBOX_RETRY_MAX_SECONDS', 300),

        // Mensagem presa em `processing` por mais que isto é considerada órfã
        // (worker morreu no meio do envio) e volta para a fila.
        'stale_minutes' => (int) env('WHATSAPP_OUTBOX_STALE_MINUTES', 10),

        // Quantas linhas a recuperação processa por clínica em cada execução.
        // Evita que um backlog de uma clínica monopolize o comando.
        'recover_limit' => (int) env('WHATSAPP_OUTBOX_RECOVER_LIMIT', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Healthcheck da conexão
    |--------------------------------------------------------------------------
    */

    'health' => [
        'cache_seconds' => (int) env('WHATSAPP_HEALTH_CACHE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras automáticas e horário de funcionamento
    |--------------------------------------------------------------------------
    |
    | A precedência é documentada em docs/whatsapp/ARQUITETURA.md. Em resumo:
    | opt-out/silêncio > pausa/handoff > expediente > regra automática > IA >
    | fluxo determinístico.
    |
    */

    'rules' => [
        'max_regex_length' => 300,
        'cache_seconds' => 60,
    ],

    'business_hours' => [
        // Uma clínica sem configuração continua 24/7, que é o comportamento
        // atual. A ausência de linha em `whatsapp_settings` não pode fazer o
        // bot parar de responder.
        'default_open' => true,
        'absence_reply_cooldown_minutes' => (int) env('WHATSAPP_ABSENCE_COOLDOWN_MINUTES', 60),

        // Texto padrao quando a clinica esta fechada. A clinica pode trocar em
        // `clinic_settings` (`whatsapp.mensagem_ausencia`), e este valor so vale
        // para quem nunca configurou.
        'absence_message' => env(
            'WHATSAPP_ABSENCE_MESSAGE',
            'A clinica esta fechada no momento. Assim que abrirmos, a equipe continua o atendimento por aqui. Se preferir, deixe sua mensagem que responderemos na sequencia.',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-out do paciente
    |--------------------------------------------------------------------------
    |
    | Pedir para parar e um direito do titular, nao uma palavra-chave qualquer:
    | a conversa fica pausada sem prazo e so volta com retomada explicita. A
    | comparacao e por palavra inteira (ver Triagem::pediuParaParar()), entao
    | "comparar" nao dispara "parar".
    |
    */

    'opt_out' => [
        'palavras' => [
            'parar',
            'pare',
            'cancelar mensagens',
            'nao quero receber',
            'não quero receber',
            'descadastrar',
            'opt out',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lembretes
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        'day_before_time' => env('WHATSAPP_REMINDER_DAY_BEFORE_TIME', '09:00'),
        'day_of_time' => env('WHATSAPP_REMINDER_DAY_OF_TIME', '08:00'),
        'quiet_start' => env('WHATSAPP_QUIET_START', '20:00'),
        'quiet_end' => env('WHATSAPP_QUIET_END', '08:00'),
        'max_per_run' => (int) env('WHATSAPP_REMINDER_MAX_PER_RUN', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbox
    |--------------------------------------------------------------------------
    */

    'inbox' => [
        'per_page' => (int) env('WHATSAPP_INBOX_PER_PAGE', 25),
        'max_search_length' => 100,
    ],

];
