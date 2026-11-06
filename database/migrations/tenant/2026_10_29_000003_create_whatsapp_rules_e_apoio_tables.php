<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras automáticas de resposta e as tabelas de apoio ao atendimento humano.
 *
 * As quatro tabelas vivem no banco da clínica porque descrevem o atendimento
 * DELA: a resposta padrão sobre convênio, o horário de funcionamento, a nota
 * que a recepção escreveu sobre um paciente e o alerta de "tem gente
 * esperando". Nada disso é configuração da plataforma.
 *
 * `whatsapp_rules` é o que torna o bot útil sem IA: um texto exato, um trecho
 * ou uma expressão regular devolvem uma resposta fixa, na ordem de prioridade.
 * Não há flag própria porque a tabela nasce vazia — sem regra cadastrada, o
 * comportamento é exatamente o de antes.
 *
 * A separação entre `whatsapp_notes` e `whatsapp_messages` é deliberada: nota
 * interna NUNCA sai para o paciente. Guardar as duas na mesma tabela com um
 * `from_me` diferente seria pedir para um relatório vazar nota para o histórico
 * da conversa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_rules', function (Blueprint $table) {
            $table->id();

            $table->string('nome', 100);
            // exact | contains | regex — validado em WhatsAppRule::TIPOS.
            $table->string('tipo', 20);
            $table->string('gatilho', 500);
            $table->text('resposta');

            // Menor número responde primeiro. Duas regras com a mesma
            // prioridade são desempatadas pelo id, para a resposta não depender
            // da ordem que o banco devolveu.
            $table->unsignedSmallInteger('prioridade')->default(100);

            // menu | livre. `menu` só vale na etapa de menu; `livre` alcança
            // qualquer etapa que não tenha contrato próprio (cadastro,
            // seleção de horário). Ver MotorDeRegras.
            $table->string('escopo', 20)->default('menu');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'prioridade'], 'whatsapp_rules_ativas_prioridade_index');
        });

        Schema::create('whatsapp_quick_replies', function (Blueprint $table) {
            $table->id();

            // O atalho é o que a recepção digita ("/endereco"); único porque
            // dois atalhos iguais tornariam a busca ambígua.
            $table->string('atalho', 40)->unique();
            $table->string('titulo', 120);
            $table->text('conteudo');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'titulo'], 'whatsapp_quick_replies_ativas_index');
        });

        Schema::create('whatsapp_notes', function (Blueprint $table) {
            $table->id();

            $table->string('remote_jid', 191);
            // Id do usuário no banco CENTRAL. Sem FK de propósito: a tabela está
            // no banco da clínica e o usuário não.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('conteudo');

            $table->timestamps();

            $table->index(['remote_jid', 'created_at'], 'whatsapp_notes_conversa_index');
        });

        Schema::create('whatsapp_alerts', function (Blueprint $table) {
            $table->id();

            $table->string('remote_jid', 191);
            // handoff | fora_expediente | pausa — o que a recepção precisa saber.
            $table->string('tipo', 30);
            $table->string('motivo', 191)->nullable();

            // Nulo enquanto ninguém assumiu: é a fila de espera da recepção.
            $table->timestamp('atendido_em')->nullable();
            $table->unsignedBigInteger('atendido_por')->nullable();

            $table->timestamps();

            $table->index(['atendido_em', 'created_at'], 'whatsapp_alerts_fila_index');
            $table->index(['remote_jid', 'tipo', 'atendido_em'], 'whatsapp_alerts_conversa_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_alerts');
        Schema::dropIfExists('whatsapp_notes');
        Schema::dropIfExists('whatsapp_quick_replies');
        Schema::dropIfExists('whatsapp_rules');
    }
};
