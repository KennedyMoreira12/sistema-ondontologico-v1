<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provedor de emissão
    |--------------------------------------------------------------------------
    |
    | `mock` funciona sem credenciais e é o padrão em desenvolvimento. Em
    | produção troque para um provedor real e preencha as chaves abaixo.
    |
    | Provedores disponíveis: mock, sendnf, focusnfe, nfeio, plugnotas.
    |
    */

    'default' => env('FISCAL_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Ambiente e padrões do emitente
    |--------------------------------------------------------------------------
    |
    | Estes valores são o fallback quando o perfil da clínica (clinic_profile)
    | ainda não tem o dado preenchido. Em homologação nenhum documento tem
    | validade fiscal.
    |
    */

    'ambiente' => env('FISCAL_AMBIENTE', 'homologacao'),

    'serie' => env('FISCAL_SERIE', '1'),

    'regime_tributario' => env('FISCAL_REGIME_TRIBUTARIO', 'simples'),

    'aliquota_iss' => (float) env('FISCAL_ALIQUOTA_ISS', 5.0),

    'codigo_servico' => env('FISCAL_CODIGO_SERVICO', '4.03'),

    'descricao_servico' => env('FISCAL_DESCRICAO_SERVICO', 'Serviços odontológicos prestados'),

    'codigo_municipio' => env('FISCAL_CODIGO_MUNICIPIO'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    */

    'timeout' => [
        'connect' => (int) env('FISCAL_CONNECT_TIMEOUT', 10),
        'request' => (int) env('FISCAL_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provedores
    |--------------------------------------------------------------------------
    |
    | Nenhuma credencial deve ser escrita neste arquivo. Tudo vem do ambiente.
    |
    */

    'providers' => [

        'mock' => [
            // Nada a configurar: gera chave, protocolo e XML localmente.
        ],

        'sendnf' => [
            'base_url' => env('SENDFN_BASE_URL'),
            'api_key' => env('SENDFN_API_KEY'),
            'company_id' => env('SENDFN_COMPANY_ID'),
            'enabled' => (bool) env('SENDFN_ENABLED', false),
        ],

        'focusnfe' => [
            'base_url' => env('FOCUSNFE_BASE_URL', 'https://api.focusnfe.com.br'),
            'token' => env('FOCUSNFE_TOKEN'),
            'enabled' => (bool) env('FOCUSNFE_ENABLED', false),
        ],

        'nfeio' => [
            'base_url' => env('NFEIO_BASE_URL', 'https://api.nfe.io'),
            'api_key' => env('NFEIO_API_KEY'),
            'company_id' => env('NFEIO_COMPANY_ID'),
            'enabled' => (bool) env('NFEIO_ENABLED', false),
        ],

        'plugnotas' => [
            'base_url' => env('PLUGNOTAS_BASE_URL', 'https://api.plugnotas.com.br'),
            'api_key' => env('PLUGNOTAS_API_KEY'),
            'enabled' => (bool) env('PLUGNOTAS_ENABLED', false),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SendNF (cliente direto usado pelo painel admin da clínica)
    |--------------------------------------------------------------------------
    |
    | Bloco mantido em paralelo a `providers.sendnf` porque o cliente direto
    | da SendNF lê `fiscal.sendnf.*`. Continua desligado por padrão.
    |
    */

    'sendnf' => [
        'base_url' => env('SENDFN_BASE_URL'),
        'api_key' => env('SENDFN_API_KEY'),
        'timeout' => [
            'connect' => (int) env('SENDFN_CONNECT_TIMEOUT', 10),
            'request' => (int) env('SENDFN_TIMEOUT', 30),
        ],
        'enabled' => (bool) env('SENDFN_ENABLED', false),
    ],

];
