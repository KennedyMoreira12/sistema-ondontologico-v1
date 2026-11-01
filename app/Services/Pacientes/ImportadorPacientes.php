<?php

namespace App\Services\Pacientes;

use App\Models\Paciente;
use App\Rules\Cpf;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Importa as linhas de uma planilha para `pacientes`.
 *
 * Recebe as linhas já lidas pelo PlanilhaReader e o mapa "campo => coluna"
 * montado na tela de conferência. Não conhece request nem session de propósito:
 * o controller cuida do HTTP e esta classe cuida da regra de importação.
 *
 * A importação é parcial por natureza: uma linha com CPF inválido não impede as
 * outras de entrarem. O resumo separa "ignorados" (paciente que já existe) de
 * "erros" (dado que não dá para salvar), porque os dois casos pedem ações
 * diferentes de quem está importando.
 */
final class ImportadorPacientes
{
    public const LIMITE_DETALHES = 100;

    /**
     * Campos importáveis, na ordem em que aparecem na tela.
     *
     * @var array<string, array{rotulo: string, grupo: string, aliases: list<string>, obrigatorio?: bool}>
     */
    public const CAMPOS = [
        'nome' => [
            'rotulo' => 'Nome completo',
            'grupo' => 'Dados pessoais',
            'aliases' => ['nome', 'nome completo', 'nome do paciente', 'nome paciente', 'paciente', 'cliente'],
            'obrigatorio' => true,
        ],
        'cpf' => [
            'rotulo' => 'CPF',
            'grupo' => 'Dados pessoais',
            'aliases' => ['cpf', 'cpf do paciente', 'cpf paciente'],
        ],
        'rg' => [
            'rotulo' => 'RG',
            'grupo' => 'Dados pessoais',
            'aliases' => ['rg', 'identidade', 'documento de identidade', 'rg identidade'],
        ],
        'nascimento' => [
            'rotulo' => 'Data de nascimento',
            'grupo' => 'Dados pessoais',
            'aliases' => ['nascimento', 'data de nascimento', 'data nascimento', 'dt nascimento', 'data nasc', 'aniversario', 'data aniversario'],
        ],
        'sexo' => [
            'rotulo' => 'Sexo',
            'grupo' => 'Dados pessoais',
            'aliases' => ['sexo', 'genero'],
        ],
        'responsavel' => [
            'rotulo' => 'Responsável',
            'grupo' => 'Dados pessoais',
            'aliases' => ['responsavel', 'responsavel legal', 'nome do responsavel', 'mae', 'pai'],
        ],
        'email' => [
            'rotulo' => 'E-mail',
            'grupo' => 'Contato',
            'aliases' => ['email', 'e mail', 'correio eletronico', 'email do paciente'],
        ],
        'phone' => [
            'rotulo' => 'Telefone',
            'grupo' => 'Contato',
            'aliases' => ['telefone', 'telefone fixo', 'fone', 'tel', 'telefone residencial', 'residencial', 'telefone principal'],
        ],
        'celular' => [
            'rotulo' => 'Celular',
            'grupo' => 'Contato',
            'aliases' => ['celular', 'whatsapp', 'whats app', 'cel', 'movel', 'telefone celular'],
        ],
        'cep' => [
            'rotulo' => 'CEP',
            'grupo' => 'Endereço',
            'aliases' => ['cep', 'codigo postal', 'cod postal'],
        ],
        'endereco' => [
            'rotulo' => 'Endereço',
            'grupo' => 'Endereço',
            'aliases' => ['endereco', 'rua', 'logradouro', 'endereco completo'],
        ],
        'numero' => [
            'rotulo' => 'Número',
            'grupo' => 'Endereço',
            'aliases' => ['numero', 'num', 'n', 'numero do endereco'],
        ],
        'complemento' => [
            'rotulo' => 'Complemento',
            'grupo' => 'Endereço',
            'aliases' => ['complemento', 'apartamento', 'apto'],
        ],
        'bairro' => [
            'rotulo' => 'Bairro',
            'grupo' => 'Endereço',
            'aliases' => ['bairro', 'distrito'],
        ],
        'cidade' => [
            'rotulo' => 'Cidade',
            'grupo' => 'Endereço',
            'aliases' => ['cidade', 'municipio', 'localidade'],
        ],
        'estado' => [
            'rotulo' => 'UF',
            'grupo' => 'Endereço',
            'aliases' => ['uf', 'estado', 'sigla uf', 'estado uf', 'unidade federativa'],
        ],
        'observacoes' => [
            'rotulo' => 'Observações',
            'grupo' => 'Outros',
            'aliases' => ['observacoes', 'observacao', 'obs', 'notas', 'anotacoes', 'historico', 'historico do paciente'],
        ],
        'status' => [
            'rotulo' => 'Status',
            'grupo' => 'Outros',
            'aliases' => ['status', 'situacao'],
        ],
    ];

    private const UFS = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    /**
     * @param  list<string>  $cabecalho
     * @return array<string, int>
     */
    public function autoMapear(array $cabecalho): array
    {
        $normalizados = [];

        foreach ($cabecalho as $indice => $rotulo) {
            $normalizados[$indice] = $this->normalizarRotulo($rotulo);
        }

        $mapa = [];
        $usados = [];

        // Primeiro o casamento exato, depois o "contém" — assim uma coluna
        // "Nome" não é roubada por uma coluna "Nome do responsável".
        foreach ([true, false] as $exato) {
            foreach (self::CAMPOS as $campo => $definicao) {
                if (isset($mapa[$campo])) {
                    continue;
                }

                foreach ($normalizados as $indice => $rotulo) {
                    if (isset($usados[$indice]) || $rotulo === '') {
                        continue;
                    }

                    foreach ($definicao['aliases'] as $alias) {
                        $alias = $this->normalizarRotulo($alias);
                        $combina = $exato
                            ? $rotulo === $alias
                            : mb_strlen($alias) >= 5 && (str_contains($rotulo, $alias) || str_contains($alias, $rotulo));

                        if ($combina) {
                            $mapa[$campo] = $indice;
                            $usados[$indice] = true;

                            continue 3;
                        }
                    }
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  list<list<string>>  $linhas
     * @param  array<string, int|null>  $mapa
     * @return array{
     *     criados: int,
     *     ignorados: list<array{linha: int, nome: string, motivo: string}>,
     *     erros: list<array{linha: int, nome: string, mensagem: string}>,
     *     ignorados_total: int,
     *     erros_total: int
     * }
     */
    public function importar(array $linhas, array $mapa, int $clinicaId): array
    {
        [$cpfsExistentes, $chavesExistentes] = $this->indiceExistente();

        $criados = 0;
        $ignorados = [];
        $erros = [];

        foreach ($linhas as $posicao => $linha) {
            $numeroLinha = $posicao + 2; // a linha 1 é o cabeçalho

            $valor = function (string $campo) use ($linha, $mapa): string {
                $coluna = $mapa[$campo] ?? null;

                if (! is_int($coluna)) {
                    return '';
                }

                return trim((string) ($linha[$coluna] ?? ''));
            };

            $nome = $this->limitar($valor('nome'), 255);

            if ($nome === '') {
                $erros[] = ['linha' => $numeroLinha, 'nome' => '(sem nome)', 'mensagem' => 'Nome não informado.'];

                continue;
            }

            $cpf = $this->apenasDigitos($valor('cpf'));

            if ($cpf !== '') {
                $falhaCpf = null;

                (new Cpf)->validate('cpf', $cpf, function (string $mensagem) use (&$falhaCpf): void {
                    $falhaCpf = $mensagem;
                });

                if ($falhaCpf !== null) {
                    $erros[] = ['linha' => $numeroLinha, 'nome' => $nome, 'mensagem' => $falhaCpf];

                    continue;
                }
            }

            $nascimento = $this->normalizarData($valor('nascimento'));

            if ($valor('nascimento') !== '' && $nascimento === null) {
                $erros[] = [
                    'linha' => $numeroLinha,
                    'nome' => $nome,
                    'mensagem' => 'Data de nascimento inválida. Use dd/mm/aaaa.',
                ];

                continue;
            }

            $email = mb_strtolower($valor('email'));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $erros[] = ['linha' => $numeroLinha, 'nome' => $nome, 'mensagem' => 'E-mail inválido.'];

                continue;
            }

            $telefone = $this->limitar($this->apenasDigitos($valor('phone')), 20);
            $celular = $this->limitar($this->apenasDigitos($valor('celular')), 20);
            $chaveNome = $this->chaveNome($nome);
            $chaveData = $chaveNome !== '' && $nascimento !== null ? $chaveNome.'|d:'.$nascimento : null;

            $motivo = null;

            if ($cpf !== '' && isset($cpfsExistentes[$cpf])) {
                $motivo = 'CPF já cadastrado na clínica.';
            } elseif ($chaveData !== null && isset($chavesExistentes[$chaveData])) {
                $motivo = 'Paciente com o mesmo nome e data de nascimento já cadastrado.';
            } else {
                foreach (array_filter([$celular, $telefone]) as $telefoneExistente) {
                    $chaveTelefone = $chaveNome !== '' ? $chaveNome.'|t:'.$telefoneExistente : null;

                    if ($chaveTelefone !== null && isset($chavesExistentes[$chaveTelefone])) {
                        $motivo = 'Paciente com o mesmo nome e telefone já cadastrado.';

                        break;
                    }
                }
            }

            if ($motivo !== null) {
                $ignorados[] = ['linha' => $numeroLinha, 'nome' => $nome, 'motivo' => $motivo];

                continue;
            }

            $dados = [
                'clinica_id' => $clinicaId,
                'nome' => $nome,
                'cpf' => $cpf !== '' ? $cpf : null,
                'rg' => $this->limitar($valor('rg'), 30) ?: null,
                'nascimento' => $nascimento,
                'sexo' => $this->normalizarSexo($valor('sexo')),
                'email' => $email !== '' ? $this->limitar($email, 191) : null,
                'phone' => $telefone !== '' ? $telefone : null,
                'celular' => $celular !== '' ? $celular : null,
                'cep' => $this->limitar($this->apenasDigitos($valor('cep')), 10) ?: null,
                'endereco' => $this->limitar($valor('endereco'), 255) ?: null,
                'numero' => $this->limitar($valor('numero'), 20) ?: null,
                'complemento' => $this->limitar($valor('complemento'), 255) ?: null,
                'bairro' => $this->limitar($valor('bairro'), 255) ?: null,
                'cidade' => $this->limitar($valor('cidade'), 255) ?: null,
                'estado' => $this->normalizarUf($valor('estado')),
                'responsavel' => $this->limitar($valor('responsavel'), 255) ?: null,
                'observacoes' => $this->limitar($valor('observacoes'), 5000) ?: null,
                'status' => $this->normalizarStatus($valor('status')),
            ];

            try {
                Paciente::create($dados);
                $criados++;
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    $ignorados[] = ['linha' => $numeroLinha, 'nome' => $nome, 'motivo' => 'CPF já cadastrado na clínica.'];

                    continue;
                }

                report($e);
                $erros[] = ['linha' => $numeroLinha, 'nome' => $nome, 'mensagem' => 'Não foi possível salvar este paciente.'];

                continue;
            } catch (Throwable $e) {
                report($e);
                $erros[] = ['linha' => $numeroLinha, 'nome' => $nome, 'mensagem' => 'Não foi possível salvar este paciente.'];

                continue;
            }

            // Registra na memória para que a mesma planilha não crie dois
            // pacientes iguais quando o CPF só aparece na segunda linha.
            if ($cpf !== '') {
                $cpfsExistentes[$cpf] = true;
            }

            if ($chaveData !== null) {
                $chavesExistentes[$chaveData] = true;
            }

            foreach (array_filter([$celular, $telefone]) as $telefoneExistente) {
                if ($chaveNome !== '') {
                    $chavesExistentes[$chaveNome.'|t:'.$telefoneExistente] = true;
                }
            }
        }

        return [
            'criados' => $criados,
            'ignorados' => array_slice($ignorados, 0, self::LIMITE_DETALHES),
            'erros' => array_slice($erros, 0, self::LIMITE_DETALHES),
            'ignorados_total' => count($ignorados),
            'erros_total' => count($erros),
        ];
    }

    /**
     * Carrega CPF/nome+nascimento/nome+telefone de todos os pacientes, inclusive
     * os excluídos: o índice único de CPF continua valendo para o soft delete.
     *
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    private function indiceExistente(): array
    {
        $pacientes = Paciente::withTrashed()->get(['id', 'cpf', 'nome', 'nascimento', 'celular', 'phone']);

        $cpfs = [];
        $chaves = [];

        foreach ($pacientes as $paciente) {
            $cpf = $this->apenasDigitos((string) $paciente->cpf);

            if ($cpf !== '') {
                $cpfs[$cpf] = true;
            }

            $nome = $this->chaveNome((string) $paciente->nome);

            if ($nome === '') {
                continue;
            }

            if ($paciente->nascimento !== null) {
                $chaves[$nome.'|d:'.$paciente->nascimento->format('Y-m-d')] = true;
            }

            foreach (array_filter([
                $this->apenasDigitos((string) $paciente->celular),
                $this->apenasDigitos((string) $paciente->phone),
            ]) as $telefone) {
                $chaves[$nome.'|t:'.$telefone] = true;
            }
        }

        return [$cpfs, $chaves];
    }

    private function normalizarData(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        // Serial do Excel que escapou da detecção de estilo (ex.: CSV exportado
        // do Excel). A faixa evita confundir um ano digitado sozinho com data.
        if (preg_match('/^\d{5}([.,]\d+)?$/', $valor) === 1) {
            $serial = (int) floor((float) str_replace(',', '.', $valor));

            if ($serial >= 20000 && $serial <= 60000) {
                return (new DateTimeImmutable('1899-12-30'))->modify("+{$serial} days")->format('Y-m-d');
            }
        }

        $valor = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $valor) ?? $valor;

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $formato) {
            $data = DateTimeImmutable::createFromFormat('!'.$formato, $valor);
            $erros = DateTimeImmutable::getLastErrors();

            if ($data === false) {
                continue;
            }

            if (is_array($erros) && ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) {
                continue;
            }

            $ano = (int) $data->format('Y');

            if ($ano < 1900 || $ano > (int) date('Y') + 1) {
                continue;
            }

            return $data->format('Y-m-d');
        }

        return null;
    }

    private function normalizarSexo(string $valor): ?string
    {
        $valor = $this->normalizarRotulo($valor);

        return match (true) {
            $valor === '' => null,
            in_array($valor, ['m', 'masc', 'masculino', 'male', 'homem'], true) => 'masculino',
            in_array($valor, ['f', 'fem', 'feminino', 'female', 'mulher'], true) => 'feminino',
            default => null,
        };
    }

    private function normalizarUf(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $sigla = strtoupper($valor);

        if (isset(self::UFS[$sigla])) {
            return $sigla;
        }

        $nome = $this->normalizarRotulo($valor);

        foreach (self::UFS as $uf => $nomeUf) {
            if ($this->normalizarRotulo($nomeUf) === $nome) {
                return $uf;
            }
        }

        return null;
    }

    private function normalizarStatus(string $valor): string
    {
        return str_contains(mb_strtolower($valor), 'inativ') ? 'inativo' : 'ativo';
    }

    private function chaveNome(string $nome): string
    {
        return $this->normalizarRotulo($nome);
    }

    private function normalizarRotulo(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        if (is_string($convertido) && $convertido !== '') {
            $valor = $convertido;
        }

        return preg_replace('/[^a-z0-9]+/', '', $valor) ?? '';
    }

    private function apenasDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    private function limitar(string $valor, int $limite): string
    {
        return mb_substr(trim($valor), 0, $limite);
    }
}
