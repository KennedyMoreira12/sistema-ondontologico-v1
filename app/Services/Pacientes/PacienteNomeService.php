<?php

namespace App\Services\Pacientes;

use App\Models\Paciente;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Busca e comparação de nomes de pacientes.
 *
 * A planilha PAGAMENTOS traz o nome como texto livre, e o mesmo paciente
 * aparece como GISELE, GISELE ALVES e GISELE LVES. Este serviço APROXIMA os
 * nomes, mas nunca funde cadastros: a saída é sempre uma lista de candidatos
 * para uma pessoa confirmar. Vínculo de pagamento e merge de paciente são
 * operações diferentes — o pagamento pode ser vinculado a um paciente que já
 * existe, sem apagar nem renomear ninguém.
 */
class PacienteNomeService
{
    /**
     * Termos de serviço/forma de pagamento que aparecem colados ao nome nas
     * planilhas. São removidos como palavras inteiras, nunca como pedaço de
     * nome (CONCEIÇÃO, por exemplo, não é tocada).
     *
     * @var list<string>
     */
    private const RUIDOS = [
        'CARTAO CREDITO',
        'CARTAO DE CREDITO',
        'CARTAO DEBITO',
        'CARTAO DE DÉBITO',
        'PIX',
        'DINHEIRO',
        'ALMOCO',
        'ALMOÇO',
        'MEIA PERNA',
        'ORNAMENTACAO',
        'ORNAMENTAÇÃO',
        'MASSAGEM',
        'PILATES',
        'FISIOTERAPIA',
        'DEPILACAO A LASER',
        'DEPILAÇÃO A LASER',
        'DEPILACAO',
        'DEPILAÇÃO',
        'LASER',
        'CONSULTA',
    ];

    public function normalizar(string $nome): string
    {
        $nome = Str::upper(Str::ascii($nome));
        $nome = preg_replace('/[^A-Z0-9 ]+/', ' ', $nome) ?? '';
        $nome = preg_replace('/\s+/', ' ', $nome) ?? '';

        return trim($nome);
    }

    /**
     * Nome sem os termos de serviço que vieram colados na planilha.
     */
    public function limparNome(string $nome): string
    {
        $limpo = $this->normalizar($nome);

        foreach (self::RUIDOS as $ruido) {
            $padrao = '/\b'.preg_quote($this->normalizar($ruido), '/').'\b/u';
            $limpo = preg_replace($padrao, ' ', $limpo) ?? $limpo;
        }

        $limpo = preg_replace('/\s+/', ' ', $limpo) ?? $limpo;

        return trim($limpo);
    }

    /**
     * Candidatos ordenados por semelhança, do mais provável ao menos.
     *
     * @return Collection<int, array{paciente: Paciente, score: float}>
     */
    public function candidatos(string $nome, int $limite = 10): Collection
    {
        $alvo = $this->limparNome($nome);

        if ($alvo === '') {
            return collect();
        }

        return Paciente::query()
            ->select(['id', 'nome', 'status'])
            ->get()
            ->map(function (Paciente $paciente) use ($alvo): array {
                return [
                    'paciente' => $paciente,
                    'score' => $this->similaridade($alvo, $paciente->nome),
                ];
            })
            ->filter(fn (array $candidato): bool => $candidato['score'] >= 0.78)
            ->sortByDesc('score')
            ->take($limite)
            ->values();
    }

    /**
     * Semelhança de 0 a 1 entre dois nomes.
     *
     * Combina três sinais: igualdade exata, palavras em comum e similaridade
     * textual. O primeiro nome funciona como âncora: sem ele, dois "SILVA"
     * diferentes seriam sugeridos como duplicata.
     */
    public function similaridade(string $a, string $b): float
    {
        $a = $this->limparNome($a);
        $b = $this->limparNome($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $tokensA = explode(' ', $a);
        $tokensB = explode(' ', $b);
        $mesmoPrimeiroNome = $tokensA[0] === $tokensB[0];

        similar_text($a, $b, $percentual);
        $textual = $percentual / 100;

        $intersecao = count(array_intersect($tokensA, $tokensB));
        $uniao = count(array_unique(array_merge($tokensA, $tokensB)));
        $jaccard = $uniao > 0 ? $intersecao / $uniao : 0.0;

        $score = max($textual, $jaccard * 0.95);

        if ($mesmoPrimeiroNome) {
            $score = min(1.0, $score + 0.05);
        } elseif ($textual < 0.85) {
            // Nomes com primeiro nome diferente só passam se forem muito
            // parecidos no todo (erro de digitação), não por sobrenome comum.
            $score = min($score, 0.5);
        }

        return round($score, 4);
    }

    /**
     * Grupos exatos + pares semelhantes para a tela de revisão humana.
     *
     * @return array{
     *     exatos: list<array{chave: string, pacientes: list<Paciente>}>,
     *     semelhantes: list<array{a: Paciente, b: Paciente, score: float}>
     * }
     */
    public function possiveisDuplicatas(): array
    {
        $pacientes = Paciente::query()->select(['id', 'nome', 'status'])->orderBy('nome')->get();

        $exatos = $pacientes
            ->groupBy(fn (Paciente $paciente): string => $this->limparNome($paciente->nome))
            ->filter(fn (Collection $grupo, string $chave): bool => $chave !== '' && $grupo->count() > 1)
            ->map(fn (Collection $grupo, string $chave): array => [
                'chave' => $chave,
                'pacientes' => $grupo->values()->all(),
            ])
            ->values()
            ->all();

        $semelhantes = [];
        $porPrimeiroNome = $pacientes->groupBy(function (Paciente $paciente): string {
            $tokens = explode(' ', $this->limparNome($paciente->nome));

            return $tokens[0] ?? '';
        });

        foreach ($porPrimeiroNome as $grupo) {
            $lista = $grupo->values();
            $total = $lista->count();

            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    $a = $lista[$i];
                    $b = $lista[$j];

                    if ($this->limparNome($a->nome) === $this->limparNome($b->nome)) {
                        continue;
                    }

                    $score = $this->similaridade($a->nome, $b->nome);

                    if ($score >= 0.86) {
                        $semelhantes[] = ['a' => $a, 'b' => $b, 'score' => $score];
                    }
                }
            }
        }

        usort($semelhantes, fn (array $x, array $y): int => $y['score'] <=> $x['score']);

        return ['exatos' => $exatos, 'semelhantes' => $semelhantes];
    }

    /**
     * Tenta extrair o nome do paciente das observações de um pagamento
     * importado. Só reconhece rótulos explícitos; o restante fica para a
     * revisão humana ou para o CSV de apoio.
     */
    public function nomeDoPagamento(Payment $pagamento): ?string
    {
        $texto = trim((string) $pagamento->observacoes);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/(?:paciente|cliente|nome)\s*[:\-]\s*([^;|]+)/iu', $texto, $achado) === 1) {
            $nome = $this->limparNome($achado[1]);

            return $nome !== '' ? $nome : null;
        }

        return null;
    }

    /**
     * Vincula um pagamento a um paciente existente. Não cria, renomeia nem
     * apaga paciente; o vínculo é uma decisão humana sobre um lançamento.
     */
    public function vincularPagamento(Payment $pagamento, Paciente $paciente): Payment
    {
        $pagamento->forceFill(['paciente_id' => $paciente->id])->save();

        return $pagamento->refresh();
    }
}
