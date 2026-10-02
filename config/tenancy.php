<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prefixo dos bancos das clínicas
    |--------------------------------------------------------------------------
    |
    | O nome do banco de uma clínica é gerado como `prefixo` + `clinica_` +
    | o id da clínica preenchido à esquerda com zeros, por exemplo
    | `clinic_000001`. O prefixo nunca deve mudar depois que a primeira
    | clínica entrar em produção: mudá-lo deixa as conexões registradas em
    | `tenant_connections` apontando para bancos inexistentes.
    |
    */

    'database_prefix' => env('TENANT_DB_PREFIX', 'clinic_'),

    'database_padding' => (int) env('TENANT_DB_PADDING', 6),

    /*
    |--------------------------------------------------------------------------
    | Onde os bancos das clínicas são criados
    |--------------------------------------------------------------------------
    |
    | Diretório, relativo a `database_path()`, em que o arquivo sqlite de cada
    | clínica é criado. No MySQL o valor é ignorado: lá o que importa é o nome do
    | schema, não o caminho.
    |
    | Existe como config para que os testes apontem para um diretório temporário
    | em vez de escrever dentro de `database/tenants` durante a execução. O
    | valor é um caminho completo, e não relativo a `database_path()`, para que
    | o teste possa apontar para fora do projeto.
    |
    */

    'tenants_directory' => env('TENANT_DB_DIRECTORY') ?: database_path('tenants'),

    /*
    |--------------------------------------------------------------------------
    | Estados possíveis de uma clínica
    |--------------------------------------------------------------------------
    */

    'clinic_states' => [
        'active' => 'Ativa',
        'trial' => 'Em avaliação',
        'suspended' => 'Suspensa',
        'past_due' => 'Pagamento em atraso',
        'cancelled' => 'Cancelada',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados possíveis de uma assinatura
    |--------------------------------------------------------------------------
    */

    'subscription_states' => [
        'trialing' => 'Avaliação',
        'active' => 'Ativa',
        'past_due' => 'Em atraso',
        'suspended' => 'Suspensa',
        'cancelled' => 'Cancelada',
        'expired' => 'Expirada',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados de assinatura que deixam a clínica usar o sistema
    |--------------------------------------------------------------------------
    |
    | Estados fora desta lista bloqueiam o painel em EnsureTenantAccess. Estado
    | `past_due` dá uma janela de carência antes do bloqueio — durante ela a
    | clínica ainda entra no sistema, mas os recursos pagos do plano são
    | bloqueados por CheckPlanFeature.
    |
    */

    'accessible_subscription_states' => ['trialing', 'active'],

    /*
    |--------------------------------------------------------------------------
    | Estados de clínica que deixam o painel abrir
    |--------------------------------------------------------------------------
    |
    | Lista separada de propósito: `clinics.status` e `subscriptions.status`
    | descrevem coisas diferentes e não podem compartilhar constantes. Uma
    | clínica em avaliação está em `trial`; a assinatura dela está em
    | `trialing`. Comparar uma com a outra bloqueia toda clínica em avaliação.
    |
    | `past_due` entra aqui porque o bloqueio da assinatura em atraso é feito
    | por EnsureTenantAccess depois de expirar o prazo de carência, e não aqui.
    */

    'accessible_clinic_states' => ['trial', 'active', 'past_due'],

    'grace_period_days' => (int) env('TENANT_GRACE_PERIOD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Recursos comercializáveis
    |--------------------------------------------------------------------------
    |
    | Cada chave é um recurso bloqueável por plano. O valor é apenas
    | documentação: quem decide o que está liberado é a tabela `plan_features`
    | do banco central, nunca este arquivo.
    |
    */

    'features' => [
        'ai_agent' => 'Agente de IA',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'scheduled_posts' => 'Publicação automática',
        'appointment_ai' => 'Agendamento pela IA',
        'external_api' => 'API externa',
        'advanced_automations' => 'Automações avançadas',
        'priority_support' => 'Suporte prioritário',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites aceitos por recurso
    |--------------------------------------------------------------------------
    |
    | Cada chave é o nome do limite como ele é gravado em `plan_features.limits`
    | e lido por `Plan::featureLimits()`. O painel usa esta lista para montar os
    | campos de um recurso, de modo que o valor digitado no formulário chegue ao
    | banco com o mesmo nome que o job de consumo procura.
    |
    | Recurso fora desta lista é um recurso sem contagem: entra no plano ligado
    | e pronto.
    |
    */

    'feature_limits' => [
        'ai_agent' => ['ai_messages_per_month' => 'Mensagens de IA por mês'],
        'scheduled_posts' => ['posts_per_month' => 'Publicações por mês'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados de uma conversa com o bot
    |--------------------------------------------------------------------------
    |
    | `human` significa que um atendente assumiu a conversa: enquanto o status
    | for humano, o bot não responde automaticamente.
    |
    */

    'conversation_states' => ['bot', 'waiting_customer', 'human', 'closed'],

    /*
    |--------------------------------------------------------------------------
    | Canais da Meta
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caminho base das uploaded files
    |--------------------------------------------------------------------------
    |
    | Documentos são gravados em `uploads/{clinica_id}/{paciente_id}`. O id do
    | paciente se repete em todos os bancos de clínica, então prefixar pelo
    | id da clínica é o que impede a colisão entre arquivos de clínicas
    | diferentes.
    |
    */

    'uploads_path' => env('TENANT_UPLOADS_PATH', 'uploads'),
];
