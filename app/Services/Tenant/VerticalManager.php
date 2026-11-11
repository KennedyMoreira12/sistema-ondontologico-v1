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
    /**
     * A clínica pode acessar este módulo?
     *
     * Como o sistema é vertical-agnóstico (atende clínicas de qualquer ramo),
     * todos os módulos do catálogo estão liberados por padrão. A única
     * restrição é a lista de módulos desativados explicitamente para a
     * clínica (`clinics.modulos_desativados`).
     */
    public function __construct(protected CatalogoModulos $catalogo) {}

    /**
     * Módulos que a clínica pode acessar.
     *
     * @return list<string>
     */
    public function modulosVisiveis(?Clinic $clinic = null): array
    {
        $modulos = array_merge(['dashboard'], array_keys($this->catalogo->todos()));

        $desativados = $clinic?->modulos_desativados ?? [];

        return array_values(array_filter($modulos, function (string $m): bool {
            return ! in_array($m, $desativados, true);
        }));
    }

    /**
     * Módulos que a clínica NÃO pode acessar.
     *
     * @return list<string>
     */
    public function modulosBloqueados(?Clinic $clinic = null): array
    {
        $desativados = $clinic?->modulos_desativados ?? [];

        return array_values(array_filter(
            array_keys($this->catalogo->todos()),
            fn (string $m): bool => in_array($m, $desativados, true)
        ));
    }

    /**
     * A clínica pode acessar este módulo?
     *
     * Sempre retorna true para módulos do catálogo, exceto se o módulo
     * estiver na lista de desativados da clínica.
     */
    public function permite(string $modulo, ?Clinic $clinic = null): bool
    {
        $desativados = $clinic?->modulos_desativados ?? [];

        return ! in_array($modulo, $desativados, true);
    }

    /**
     * Verifica se uma vertical é válida (qualquer string não vazia é válida).
     */
    public function verticalValida(?string $vertical): bool
    {
        return is_string($vertical) && trim($vertical) !== '';
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
        $desativados = $clinic?->modulos_desativados ?? [];

        return array_values(array_filter(
            array_keys($this->catalogo->todos()),
            fn (string $m): bool => in_array($m, $desativados, true)
        ));
    }
}
