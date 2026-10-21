<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'api_token' => env('WHATSAPP_API_TOKEN'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
    ],

    'whatsapp_cloud' => [
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evolution API
    |--------------------------------------------------------------------------
    |
    | `url` é a instalação da Evolution que hospeda as instâncias das clínicas.
    | O token NÃO vive aqui: cada instância tem o seu, guardado em
    | `clinics.evolution_token` (cifrado) e enviado pelo `EvolutionService` que
    | recebe a clínica. Uma chave global daria a mesma autoridade sobre todas as
    | instâncias para qualquer código que conseguisse ler o .env.
    |
    | `webhook_secret` é o que autentica a Evolution chamando esta aplicação. A
    | rota do webhook é pública — a Evolution não tem token de clínica para
    | mandar — então ela manda o token da instância no corpo e a rota compara com
    | o da clínica resolvida pela instância. Esta variável é a segunda porta:
    | serve para os eventos que não carregam mensagem (conexão, QR code) e para
    | barrar quem tentar forjar um corpo. Vazio = webhook fechado.
    |
    */

    'evolution' => [
        /**
         * Chave ADMINISTRATIVA da Evolution (a mesma `AUTHENTICATION_API_KEY` do
         * servidor). É o único segredo que cria instância e lista as existentes.
         * Fica só aqui, no backend: nunca vai ao navegador nem à resposta HTTP.
         * As operações por instância (estado, QR) usam o token da própria
         * instância, guardado cifrado em `prospect_whatsapp_settings`.
         */
        'admin_key' => env('EVOLUTION_API_KEY'),

        /**
         * Legado: consumido por `Api\EvolutionQrController`, que existe apenas
         * no servidor. Mantido apontando para a mesma chave administrativa para
         * não quebrar aquele fluxo; código novo deve usar `admin_key`.
         */
        'api_key' => env('EVOLUTION_API_KEY'),

        'url' => env('EVOLUTION_API_URL'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        'timeout' => (int) env('EVOLUTION_API_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n
    |--------------------------------------------------------------------------
    |
    | O webhook do workflow que executa o atendimento. O Laravel entrega o
    | evento já resolvido — clínica, instância, mensagem — para que o n8n não
    | precise manter o `CLINICAS_MAP` nem adivinhar de quem é a mensagem.
    |
    */

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'webhook_token' => env('N8N_WEBHOOK_TOKEN'),
        'timeout' => (int) env('N8N_WEBHOOK_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gerador de conteúdo social (n8n)
    |--------------------------------------------------------------------------
    |
    | O webhook do workflow que gera o conteúdo editorial (LJSocialAI01). O
    | formulário "Novo Post" do painel entrega os dados da solicitação a este
    | webhook; o workflow chama o modelo e só então cria o SocialPost pela API
    | interna de posts, que autentica com a credencial de integração que já
    | existe. O formulário web não envia token nenhum.
    |
    | O timeout é maior que o do atendimento porque a resposta só volta depois
    | da chamada ao modelo.
    |
    */

    'social_ai' => [
        'webhook_url' => env('SOCIAL_AI_WEBHOOK_URL'),
        'timeout' => (int) env('SOCIAL_AI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Atendimento com IA (OpenRouter)
    |--------------------------------------------------------------------------
    |
    | O agente que interpreta a mensagem do paciente no WhatsApp. A IA PROPÕE
    | (intenção, texto, dados) e o Laravel DECIDE: nenhuma ação de agenda acontece
    | por escolha do modelo, só pela validação da máquina de estados.
    |
    | `enabled` nasce `false` de propósito. O bot do atendimento fica pausado até
    | a credencial existir e um operador ligar explicitamente; com a chave ausente
    | o `ClienteIA` se declara não configurado e o fluxo determinístico assume,
    | sem que nenhuma mensagem precise ser respondida pela IA.
    |
    | `api_key` é a chave do provedor. Ela NÃO fica no banco nem no código — só no
    | .env da aplicação, como a de qualquer outro serviço. Sem ela, silêncio.
    |
    | `max_por_conversa` limita quantas vezes a IA é chamada na MESMA conversa,
    | como trava de custo adicional à cota mensal do plano: uma conversa que não
    | termina nunca para de consumir tokens, e o teto por conversa é o que impede
    | um loop de mensagens de gastar a cota do mês inteira.
    |
    */

    'atendimento_ia' => [
        'enabled' => (bool) env('ATENDIMENTO_IA_ENABLED', false),
        'api_key' => env('AI_API_KEY'),
        'base_url' => rtrim((string) env('AI_BASE_URL', 'https://openrouter.ai/api/v1'), '/'),
        'model' => env('AI_MODEL', 'meta-llama/llama-3.3-70b-instruct'),
        'timeout' => (int) env('ATENDIMENTO_IA_TIMEOUT', 30),
        'max_tokens' => (int) env('ATENDIMENTO_IA_MAX_TOKENS', 500),
        'temperature' => (float) env('ATENDIMENTO_IA_TEMPERATURE', 0.3),
        'max_por_conversa' => (int) env('ATENDIMENTO_IA_MAX_POR_CONVERSA', 30),
    ],

];
