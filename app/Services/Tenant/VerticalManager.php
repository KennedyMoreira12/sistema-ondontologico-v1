<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use App\Support\Modulos\CatalogoModulos;

/**
 * Vertical da clínica: odontologia ou fisioterapia.
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

    /**
     * Verticais conhecidas.
     *
     * @var list<string>
     */
    public const VERTICAIS = [
        self::ODONTOLOGIA,
        self::FISIOTERAPIA,
    ];

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
     * Vertical da clínica, com fallback seguro para odontologia.
     */
    public function vertical(?Clinic $clinic = null): string
    {
        $clinic ??= app(TenantManager::class)->current();

        $vertical = $clinic?->vertical;

        return in_array($vertical, [self::ODONTOLOGIA, self::FISIOTERAPIA], true)
            ? $vertical
            : self::ODONTOLOGIA;
    }

    public function isOdontologia(?Clinic $clinic = null): bool
    {
        return $this->vertical($clinic) === self::ODONTOLOGIA;
    }

    public function isFisioterapia(?Clinic $clinic = null): bool
    {
        return $this->vertical($clinic) === self::FISIOTERAPIA;
    }

    /**
     * A clínica pode acessar este módulo?
     *
     * O parâmetro pode ser um MÓDULO (`odontograma`, `avaliacao`) ou o nome da
     * VERTICAL (`fisioterapia`): no segundo caso a pergunta é "esta clínica é
     * de fisioterapia?", e o módulo inteiro vem junto.
     */
    public function permite(string $modulo, ?Clinic $clinic = null): bool
    {
        if (in_array($modulo, self::VERTICAIS, true)) {
            return $this->vertical($clinic) === $modulo;
        }

        $verticalDoModulo = $this->catalogo->verticalDoModulo($modulo);

        // Módulo fora do catálogo (ou comum às duas verticais): liberado,
        // como sempre foi.
        if ($verticalDoModulo === null || $verticalDoModulo === CatalogoModulos::COMUM) {
            return true;
        }

        return $this->vertical($clinic) === $verticalDoModulo;
    }

    /**
     * Lista de módulos visíveis no menu para a clínica.
     *
     * @return list<string>
     */
    public function modulosVisiveis(?Clinic $clinic = null): array
    {
        $modulos = array_merge(['dashboard'], array_keys($this->catalogo->todos()));

        return array_values(array_filter($modulos, fn (string $m): bool => $this->permite($m, $clinic)));
    }

    /**
     * Módulos que a clínica NÃO pode acessar.
     *
     * @return list<string>
     */
    public function modulosBloqueados(?Clinic $clinic = null): array
    {
        $todos = array_merge($this->somenteOdontologia(), $this->somenteFisioterapia());

        return array_values(array_filter($todos, fn (string $m): bool => ! $this->permite($m, $clinic)));
    }
}
