# Guia de Atendimentos - Clínica Daiane

## Instruções Rápidas

1. Acesse o módulo **Atendimentos** pelo menu lateral do dashboard
2. Confirme a data do atendimento (padrão: "hoje")
3. Visualize a grade de consultas do dia na área central
4. Clique em "Presença" para registrar a presença do paciente
5. Se houver pacote ativo, clique em "Consumir sessão"
6. Selecione o pacote disponível e confirme

## Status de Consulta e Cores

| Status | Cor | Quando Usar |
|--------|-----|-------------|
| 🔵 **Agendado** | Azul | Consulta marcada, ainda não aconteceu |
| 🟢 **Confirmado** | Verde | Paciente confirmou comparecimento |
| 🟧 **Em Atendimento** | Laranja | Profissional já com o paciente |
| 🟩 **Atendido** | Verde escuro | Consulta finalizada com sucesso |
| 🟥 **Faltou** | Vermelho | Paciente não compareceu |

## Consumir Sessão do Pacote

Após marcar presença:

1. ✅ Verificar se paciente tem pacote ativo
2. 📦 Clicar em "Consumir sessão"
3. 📋 Selecionar pacote disponível
4. ✅ Confirmar operação
5. 🔄 Saldo do pacote atualizado automaticamente

## Alertas Automáticos

- 🟢 **Pacotes com sessões acabando:** Avisa quando restam poucas sessões
- ⚠️ **Consulta sem pacote ativo:** Alerta vermelho para consulta sem pacote
- 🚨 **Pacote expirado:** Alerta crítico quando pacote venceu

## Problemas Comuns

| Problema | Causa | Solução |
|----------|-------|---------|
| Tela vazia | Não há consultas agendadas para a data selecionada | Verificar agendamentos ou selecionar data diferente |
| Botão "Presença" disabled | Usuário não tem permissão `agenda.presenca` | Solicitar permissão ao admin |
| Pacote não aparece | Paciente não tem pacote ativo ou pacote expirado | Verificar cadastro do paciente |
| Erro ao consumir sessão | Dados inconsistentes | Recarregar página, limpar cache |

## Dicas de Utilização

- Confirme o status de cada consulta (Agendado/Confirmado/Em Atendimento/Atendido/Faltou)
- Acompanhe o consumo de sessões dos pacotes durante o dia
- Atente-se aos alertas de pacotes com sessões acabando
- Utilize o relatório diário para fechar o expediente
- Marque faltos prontamente quando paciente não comparecer