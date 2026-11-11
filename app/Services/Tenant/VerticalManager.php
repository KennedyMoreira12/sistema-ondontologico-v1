<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use App\Support\Modulos\CatalogoModulos;

/**
 * Vertical da clínica: odontologia, fisioterapia, pilates, depilação a laser.
 *
 * Uma clínica pode ter MÚLTIPLAS verticais habilitadas simultaneamente
 * (ex.: a Clínica Daiane ativa fisioterapia, pilates e laser ao mesmo
 * tempo), enquanto a Malheiro continua apenas com odontologia.
 *
 * A vertical NÃO remove nada do banco. Ela apenas decide quais MÓDULOS a
 * clínica enxerga no menu e quais rotas ficam acessíveis. A clínica
 * odontológica continua enxergando o odontograma; a de fisioterapia enxerga
 * avaliação, evolução e pacotes de sessões.
 *
 * A regra é sempre: a vertical do CENTRAL decide o que a clínica vê, e a
 * decisão é tomada no servidor. O navegador nunca informa a vertical.
 */
class VerticalManager
{
    public const ODONTOLOGIA = 'odontologia';

    public const FISIOTERAPIA = 'fisioterapia';

    public const PILATES = 'pilates';

    public const LASER = 'laser';

    /**
     * Verticais conhecidas.
     *
     * @var list<string>
     */
    public const VERTICAIS = [
        self::ODONTOLOGIA,
        self::FISIOTERAPIA,
        self::PILATES,
        self::LASER,
    ];

    /**
     * Nome de exibição de cada vertical.
     *
     * @var array<string, string>
     */
    public const ROTULOS = [
        self::ODONTOLOGIA => 'Odontologia',
        self::FISIOTERAPIA => 'Fisioterapia',
        self::PILATES => 'Pilates',
        self::LASER => 'Depilação a laser',
    ];

    public static function rotulo(string $vertical): string
    {
        return self::ROTULOS[$vertical] ?? $vertical;
    }

    public function __construct(protected CatalogoModulos $catalogo) {}

    /**
     * Módulos que só existem para odontologia.
     *
     * A lista vem do catálogo de módulos: é ele quem diz a que vertical cada
     * módulo pertence, e não duas listas paralelas que podem divergir.
     *
     * @return list<string>
     */
    public function somenteOdontologia(): array
    {
        return $this->catalogo->modulosDaVertical(self::ODONTOLOGIA);
    }

    /**
     * Módulos que só existem para fisioterapia.
     *
     * @return list<string>
     */
    public function somenteFisioterapia(): array
    {
        return $this->catalogo->modulosDaVertical(self::FISIOTERAPIA);
    }

    /**
     * Módulos que só existem para pilates.
     *
     * @return list<string>
     */
    public function somentePilates(): array
    {
        return $this->catalogo->modulosDaVertical(self::PILATES);
    }

    /**
     * Módulos que só existem para depilação a laser.
     *
     * @return list<string>
     */
    public function somenteLaser(): array
    {
        return $this->catalogo->modulosDaVertical(self::LASER);
    }

    /**
     * Vertical primária da clínica, com fallback seguro para odontologia.
     *
     * Mantida para compatibilidade com todo o código existente. Quando a
     * clínica tem várias verticais, esta retorna a primeira delas.
     */
    public function vertical(?Clinic $clinic = null): string
    {
        $verticals = $this->verticals($clinic);

        return $verticals[0] ?? self::ODONTOLOGIA;
    }

    /**
     * Todas as verticais habilitadas para a clínica.
     *
     * Lê a coluna JSON `verticals` quando preenchida; caso contrário, cai de
     * volta para `[vertical()]` — comportamento idêntico ao de antes.
     *
     * @return list<string>
     */
    public function verticals(?Clinic $clinic = null): array
    {
        $clinic ??= app(TenantManager::class)->current();

        $verticals = $this->normalizarVerticais($clinic?->verticals);

        if ($verticals !== []) {
            return $verticals;
        }

        $primaria = $clinic?->vertical;

        return in_array($primaria, self::VERTICAIS, true) ? [$primaria] : [self::ODONTOLOGIA];
    }

    /**
     * Normaliza o valor da coluna `verticals`, que pode chegar como array
     * (cast do Eloquent) ou como string JSON (banco sem cast, importação).
     *
     * Valores desconhecidos são descartados e duplicatas são removidas.
     *
     * @return list<string>
     */
    private function normalizarVerticais(mixed $valor): array
    {
        if (is_string($valor) && $valor !== '') {
            $valor = json_decode($valor, true);
        }

        if (! is_array($valor)) {
            return [];
        }

        $validas = array_filter(
            $valor,
            fn (mixed $vertical): bool => is_string($vertical) && in_array($vertical, self::VERTICAIS, true),
        );

        return array_values(array_unique($validas));
    }

    public function isOdontologia(?Clinic $clinic = null): bool
    {
        return in_array(self::ODONTOLOGIA, $this->verticals($clinic), true);
    }

    public function isFisioterapia(?Clinic $clinic = null): bool
    {
        return in_array(self::FISIOTERAPIA, $this->verticals($clinic), true);
    }

    public function isPilates(?Clinic $clinic = null): bool
    {
        return in_array(self::PILATES, $this->verticals($clinic), true);
    }

    public function isLaser(?Clinic $clinic = null): bool
    {
        return in_array(self::LASER, $this->verticals($clinic), true);
    }

    /**
     * A clínica pode acessar este módulo?
     *
     * O parâmetro pode ser um MÓDULO (`odontograma`, `avaliacao`) ou o nome da
     * VERTICAL (`fisioterapia`): no segundo caso a pergunta é "esta clínica tem
     * a vertical fisioterapia habilitada?", e o módulo inteiro vem junto.
     *
     * Um módulo é liberado quando pelo menos UMA das verticais habilitadas
     * para a clínica é a vertical do módulo (ou o módulo é comum a todas).
     */
    public function permite(string $modulo, ?Clinic $clinic = null): bool
    {
        $clinic ??= app(TenantManager::class)->current();

        $verticalsClinica = $this->verticals($clinic);

        if (in_array($modulo, self::VERTICAIS, true)) {
            return in_array($modulo, $verticalsClinica, true);
        }

        // Módulo desativado explicitamente para esta clínica
        $desativados = $clinic?->modulos_desativados ?? [];
        if (in_array($modulo, $desativados, true)) {
            return false;
        }

        $verticalDoModulo = $this->catalogo->verticalDoModulo($modulo);

        // Módulo fora do catálogo (ou comum às duas verticais): liberado,
        // como sempre foi.
        if ($verticalDoModulo === null || $verticalDoModulo === CatalogoModulos::COMUM) {
            return true;
        }

        return in_array($verticalDoModulo, $verticalsClinica, true);
    }

    /**
     * Lista de módulos visíveis no menu para a clínica.
     *
     * @return list<string>
     */
    public function modulosVisiveis(?Clinic $clinic = null): array
    {
        $modulos = array_merge(['dashboard'], array_keys($this->catalogo->todos()));

        $desativados = $clinic?->modulos_desativados ?? [];

        return array_values(array_filter($modulos, function (string $m) use ($desativados, $clinic): bool {
            return ! in_array($m, $desativados, true) && $this->permite($m, $clinic);
        }));
    }

    /**
     * Módulos que a clínica NÃO pode acessar.
     *
     * @return list<string>
     */
    public function modulosBloqueados(?Clinic $clinic = null): array
    {
        $todos = array_merge(
            $this->somenteOdontologia(),
            $this->somenteFisioterapia(),
            $this->somentePilates(),
            $this->somenteLaser(),
        );

        return array_values(array_filter($todos, fn (string $m): bool => ! $this->permite($m, $clinic)));
    }
}
