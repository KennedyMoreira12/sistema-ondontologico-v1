<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Camadas do odontograma
    |--------------------------------------------------------------------------
    | "Existente" é a situação clínica já registrada e "planejado" é o
    | tratamento a ser realizado. As cores são configuráveis por variável de
    | ambiente e lidas na interface e na geração de imagem/PDF futura.
    */
    'camadas' => [
        'existente' => [
            'label' => 'Existente',
            'cor' => env('ODONTOGRAMA_CAMADA_EXISTENTE', '#dc3545'),
        ],
        'planejado' => [
            'label' => 'Planejado',
            'cor' => env('ODONTOGRAMA_CAMADA_PLANEJADO', '#0d6efd'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dentições e numeração FDI
    |--------------------------------------------------------------------------
    | Permanente: 11–18, 21–28, 31–38, 41–48.
    | Decídua:    51–55, 61–65, 71–75, 81–85.
    | A dentição "mista" é uma visualização que combina as duas arcadas.
    */
    'denticoes' => [
        'permanente' => [
            'label' => 'Permanente',
            'dentes' => [
                11, 12, 13, 14, 15, 16, 17, 18,
                21, 22, 23, 24, 25, 26, 27, 28,
                31, 32, 33, 34, 35, 36, 37, 38,
                41, 42, 43, 44, 45, 46, 47, 48,
            ],
        ],
        'decidua' => [
            'label' => 'Decídua',
            'dentes' => [
                51, 52, 53, 54, 55,
                61, 62, 63, 64, 65,
                71, 72, 73, 74, 75,
                81, 82, 83, 84, 85,
            ],
        ],
        'mista' => [
            'label' => 'Mista',
            'dentes' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notações de numeração
    |--------------------------------------------------------------------------
    | A notação é persistida como FDI; Universal e Palmer são conversões de
    | exibição sobre o mesmo dado.
    */
    'notacoes' => [
        'FDI' => 'FDI',
        'Universal' => 'Universal',
        'Palmer' => 'Palmer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Superfícies
    |--------------------------------------------------------------------------
    | As superfícies aplicáveis dependem da posição do dente no quadrante FDI:
    |   incisivo/canino (posição 1–3) → incisal;
    |   pré-molar e molar (posição 4–8) → oclusal;
    |   arcada superior (quadrante 1, 2, 5, 6) → palatina;
    |   arcada inferior (quadrante 3, 4, 7, 8) → lingual.
    */
    'superficies' => [
        'vestibular' => 'Vestibular',
        'mesial' => 'Mesial',
        'distal' => 'Distal',
        'oclusal' => 'Oclusal',
        'incisal' => 'Incisal',
        'palatina' => 'Palatina',
        'lingual' => 'Lingual',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status do dente
    |--------------------------------------------------------------------------
    */
    'status_dente' => [
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'erupcionado' => 'Em erupção',
    ],
];
