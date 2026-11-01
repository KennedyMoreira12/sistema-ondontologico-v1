<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppQuickReply;
use Illuminate\Support\Collection;

/**
 * Respostas rápidas do atendimento humano.
 *
 * São textos fixos da clínica (endereço, preparo de exame, política de
 * cancelamento) que a recepção reaproveita. A classe só LÊ: quem escolhe a
 * resposta e manda é o atendente. Nenhum atalho responde sozinho — se
 * respondesse, seria uma regra automática disfarçada, e a diferença entre as
 * duas precisa continuar clara para quem opera.
 */
class RespostasRapidas
{
    /**
     * @return Collection<int, WhatsAppQuickReply>
     */
    public function listar(): Collection
    {
        return WhatsAppQuickReply::query()
            ->ativas()
            ->orderBy('titulo')
            ->get();
    }

    public function porAtalho(string $atalho): ?WhatsAppQuickReply
    {
        $atalho = ltrim(trim($atalho), '/');

        if ($atalho === '') {
            return null;
        }

        return WhatsAppQuickReply::query()
            ->ativas()
            ->where('atalho', $atalho)
            ->first();
    }

    /**
     * Conteúdo pronto para o atendente colar, ou `null` se o atalho não existe.
     */
    public function conteudo(string $atalho): ?string
    {
        return $this->porAtalho($atalho)?->conteudo;
    }
}
