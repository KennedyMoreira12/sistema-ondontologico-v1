<?php

namespace App\Services\Fiscal;

use App\Contracts\FiscalProviderInterface;
use App\Services\Fiscal\Providers\FocusNFeProvider;
use App\Services\Fiscal\Providers\MockFiscalProvider;
use App\Services\Fiscal\Providers\NFeIoProvider;
use App\Services\Fiscal\Providers\PlugNotasProvider;
use App\Services\Fiscal\Providers\SendNfProvider;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolve o provedor fiscal configurado em `fiscal.default`.
 *
 * Usa o Manager do Laravel de propósito: assim é possível trocar de provedor
 * por configuração (`FISCAL_PROVIDER`) e, nos testes, `FiscalManager::fake()`
 * ou um `extend()` registram um dublê.
 */
class FiscalManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('fiscal.default', 'mock');
    }

    /**
     * Provedor pelo nome, sem depender do driver padrão.
     */
    public function provider(?string $nome = null): FiscalProviderInterface
    {
        $nome = $nome ?: $this->getDefaultDriver();

        $config = $this->config->get("fiscal.providers.{$nome}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Provedor fiscal [{$nome}] não configurado em config/fiscal.php.");
        }

        if (array_key_exists('enabled', $config) && ! $config['enabled']) {
            throw new FiscalException("O provedor fiscal [{$nome}] está desabilitado. Defina a variável de ambiente correspondente.");
        }

        /** @var FiscalProviderInterface $driver */
        $driver = $this->driver($nome);

        return $driver;
    }

    protected function createMockDriver(): FiscalProviderInterface
    {
        return new MockFiscalProvider;
    }

    protected function createSendnfDriver(): FiscalProviderInterface
    {
        return new SendNfProvider(
            (string) $this->config->get('fiscal.providers.sendnf.base_url'),
            (string) $this->config->get('fiscal.providers.sendnf.api_key'),
            $this->config->get('fiscal.providers.sendnf.company_id'),
        );
    }

    protected function createFocusnfeDriver(): FiscalProviderInterface
    {
        return new FocusNFeProvider(
            (string) $this->config->get('fiscal.providers.focusnfe.base_url'),
            (string) $this->config->get('fiscal.providers.focusnfe.token'),
        );
    }

    protected function createNfeioDriver(): FiscalProviderInterface
    {
        return new NFeIoProvider(
            (string) $this->config->get('fiscal.providers.nfeio.base_url'),
            (string) $this->config->get('fiscal.providers.nfeio.api_key'),
            $this->config->get('fiscal.providers.nfeio.company_id'),
        );
    }

    protected function createPlugnotasDriver(): FiscalProviderInterface
    {
        return new PlugNotasProvider(
            (string) $this->config->get('fiscal.providers.plugnotas.base_url'),
            (string) $this->config->get('fiscal.providers.plugnotas.api_key'),
        );
    }
}
