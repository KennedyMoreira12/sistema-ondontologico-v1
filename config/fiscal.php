<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SendNF Integration
    |--------------------------------------------------------------------------
    |
    | Configurações para integração com a API da SendNF. Nenhuma chave ou
    | certificado deve ser armazenado neste arquivo. Os valores devem vir
    | exclusivamente de variáveis de ambiente.
    |
    | Emissão real permanece desabilitada nesta fase estrutural.
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
