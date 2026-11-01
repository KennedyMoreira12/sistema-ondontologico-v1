<?php

namespace App\Services\WhatsApp;

use App\Models\Central\Clinic;
use App\Models\Central\ClinicFeatureFlag;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Leitura e escrita das flags de recurso por clínica.
 *
 * O padrão vem de `config/whatsapp.php` e é sempre desligado. Uma clínica sem
 * nenhuma linha na tabela continua com o comportamento atual — o que permite
 * entregar o código novo sem ligar nada em produção.
 *
 * O cache é por clínica e guarda o mapa inteiro: o painel lê seis flags de uma
 * vez e o atendimento lê uma por mensagem, então uma consulta por flag seria
 * seis queries no caminho quente. `set()` invalida o mapa na hora.
 */
class ClinicFeatures
{
    /**
     * @return array<string, bool>
     */
    public function all(Clinic|int|null $clinic, bool $fresh = false): array
    {
        $padroes = $this->defaults();
        $id = $this->id($clinic);

        if ($id === null) {
            return $padroes;
        }

        $ler = function () use ($id, $padroes): array {
            $salvas = ClinicFeatureFlag::query()
                ->where('clinic_id', $id)
                ->pluck('enabled', 'flag')
                ->all();

            $resultado = $padroes;

            foreach ($salvas as $flag => $enabled) {
                // Flag desconhecida não entra no mapa: typo no banco não vira
                // recurso ligado por acidente.
                if (array_key_exists($flag, $resultado)) {
                    $resultado[$flag] = (bool) $enabled;
                }
            }

            return $resultado;
        };

        if ($fresh) {
            return $ler();
        }

        return Cache::remember($this->cacheKey($id), now()->addMinutes(5), $ler);
    }

    public function enabled(Clinic|int|null $clinic, string $flag): bool
    {
        $this->assertKnown($flag);

        return $this->all($clinic)[$flag] ?? false;
    }

    public function set(Clinic|int $clinic, string $flag, bool $enabled): ClinicFeatureFlag
    {
        $this->assertKnown($flag);

        $registro = ClinicFeatureFlag::query()->updateOrCreate(
            ['clinic_id' => $this->id($clinic), 'flag' => $flag],
            ['enabled' => $enabled],
        );

        $this->forget($clinic);

        return $registro;
    }

    public function forget(Clinic|int|null $clinic): void
    {
        $id = $this->id($clinic);

        if ($id !== null) {
            Cache::forget($this->cacheKey($id));
        }
    }

    /**
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        return array_map(
            static fn (mixed $valor): bool => (bool) $valor,
            config('whatsapp.flags', []),
        );
    }

    /**
     * @return list<string>
     */
    public function known(): array
    {
        return array_keys($this->defaults());
    }

    protected function assertKnown(string $flag): void
    {
        if (! array_key_exists($flag, $this->defaults())) {
            throw new InvalidArgumentException("Flag de WhatsApp desconhecida: [{$flag}].");
        }
    }

    protected function id(Clinic|int|null $clinic): ?int
    {
        if ($clinic instanceof Clinic) {
            return (int) $clinic->getKey();
        }

        return $clinic === null ? null : (int) $clinic;
    }

    protected function cacheKey(int $clinicId): string
    {
        return "whatsapp:features:{$clinicId}";
    }
}
