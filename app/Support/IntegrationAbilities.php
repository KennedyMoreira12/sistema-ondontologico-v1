<?php

namespace App\Support;

use RuntimeException;

/**
 * Quais operações da API cada token de integração pode executar.
 *
 * A coluna `integration_tokens.abilities` existe desde o começo e nunca foi
 * aplicada: qualquer token válido chegava a todos os endpoints, inclusive aos
 * que criam e cancelam consulta. Um token do n8n criado só para ler a agenda
 * podia, portanto, escrever nela.
 *
 * O mapa é declarado por NOME DA ROTA, e não dentro do controller, porque o
 * controller é o que se quer proteger. Toda rota de integração precisa de uma
 * entrada aqui: `requiredFor()` estoura em rota sem mapa, então uma rota nova
 * não entra em produção destravada por esquecimento.
 *
 * Os nomes seguem o vocabulário de `permissions` (`modulo.acao`) para que o
 * operador leia a mesma coisa na tela de permissões e na de tokens.
 */
final class IntegrationAbilities
{
    /**
     * Ability que libera tudo. É o que o operador marca para uma integração que
     * realmente precisa de todas as rotas — e é explícito, nunca implícito.
     */
    public const WILDCARD = '*';

    /**
     * Rota => ability exigida. `null` significa "sem requirement": liberada
     * para qualquer token autenticado.
     *
     * @var array<string, string|null>
     */
    public const MAP = [
        // Diagnóstico da integração: sem dado de paciente, serve para o n8n
        // confirmar que está com token válido. Liberado para todo token.
        'api.whatsapp.info' => null,

        'api.whatsapp.profissionais' => 'professionals.view',
        'api.whatsapp.paciente' => 'patients.view',
        'api.whatsapp.criar-paciente' => 'patients.create',

        'api.whatsapp.disponibilidade' => 'appointments.view',
        'api.whatsapp.agendamentos' => 'appointments.view',
        'api.whatsapp.agendamento' => 'appointments.create',

        // Confirmar e cancelar mudam o estado da consulta: quem pode mudar não é
        // automaticamente quem pode ler, e vice-versa.
        'api.whatsapp.confirmar' => 'appointments.update',
        'api.whatsapp.cancelar' => 'appointments.update',

        /**
         * Falar com o paciente é a única rota deste mapa que escreve FORA do banco
         * da clínica: o efeito dela chega na tela do WhatsApp de uma pessoa, e
         * não se desfaz apagando um registro. Por isso tem ability própria, e
         * não herda `appointments.create` — um token montado para agendar não
         * pode mandar mensagem em nome da clínica só porque já escreve na agenda.
         */
        'api.whatsapp.mensagem' => 'messages.send',
    ];

    /**
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        return self::MAP;
    }

    /**
     * Todas as abilities que um token pode receber, sem repetição e sem o
     * wildcard. É o vocabulário que o formulário do painel oferece: uma ability
     * fora daqui não existe no mapa, então marcá-la não liberaria nada.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        return array_values(array_unique(array_filter(self::MAP)));
    }

    /**
     * @return list<string>
     */
    public static function selectable(): array
    {
        return [self::WILDCARD, ...self::available()];
    }

    /**
     * Rótulo de uma ability para a tela de tokens. O nome segue o vocabulário
     * `modulo.acao` das permissões, então o operador lê a mesma coisa nos dois
     * lugares.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::WILDCARD => 'Acesso completo (todas as abilities)',
            'patients.view' => 'Consultar pacientes',
            'patients.create' => 'Cadastrar pacientes',
            'professionals.view' => 'Consultar profissionais',
            'appointments.view' => 'Consultar agenda e disponibilidade',
            'appointments.create' => 'Criar agendamentos',
            'appointments.update' => 'Confirmar e cancelar agendamentos',
            'messages.send' => 'Enviar mensagens de WhatsApp ao paciente',
        ];
    }

    /**
     * Ability exigida pela rota, ou `null` se ela não exigir nenhuma.
     *
     * @throws RuntimeException quando a rota não está no mapa
     */
    public static function requiredFor(?string $routeName): ?string
    {
        if ($routeName === null || ! array_key_exists($routeName, self::MAP)) {
            throw new RuntimeException(
                "A rota de integração [{$routeName}] não tem ability definida em "
                .self::class.'.MAP. Sem isso a rota ficaria liberada para qualquer token.'
            );
        }

        return self::MAP[$routeName];
    }
}
