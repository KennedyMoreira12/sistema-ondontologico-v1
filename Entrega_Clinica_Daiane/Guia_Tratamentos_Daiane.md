# Guia de Tratamentos - Clínica Daiane

## Instruções Rápidas

1. Acesse o módulo **Tratamentos** pelo menu lateral do dashboard
2. Note que para fisioterapia, o módulo aparece como "Funcionalidade planejada"
3. Mesmo em modo planejado, a estrutura existe no sistema
4. Para odontologia: acesso completo aos planos e itens
5. Para fisioterapia: cadastro de planos disponível, tela de interface em adaptação

## O Que Está Disponível ✅

| Funcionalidade | Status | Descrição |
|---------------|--------|-----------|
| Cadastro de Planos | Implementado | Criar planos de tratamento por paciente |
| Adicionar Itens/Procedimentos | Implementado | Adicionar procedimentos ao plano |
| Status do Tratamento | Implementado | Rascunho → Em andamento → Concluído |
| Histórico de Evolução | Implementado | Registro de evolução clínica |
| Visualizar Planos do Paciente | Implementado | Ver planos existentes do paciente |

## O Que Está Planejado 🟸

| Funcionalidade | Status | Observação |
|---------------|--------|------------|
| Tela de interface adaptada para fisioterapia | Planejado | Em desenvolvimento futuro |
| Novos procedimentos específicos de fisioterapia | Planejado | A serem adicionados |
| Relatórios específicos de evolução | Planejado | Futura atualização |

## Como Acessar (Modo Planejado)

1. Clique no menu **Tratamentos** no dashboard
2. Mesmo aparecendo como "planejado", você pode:
   - Ver planos existentes do paciente
   - Criar novos planos (requer permissão admin)
   - Editar planos existentes
   - Mudar status dos tratamentos (rascunho → em andamento → concluído)

## Telas Disponíveis

### 1. Lista de Planos do Paciente
- Visualizar planos existentes
- Filtrar por status (rascunho, em andamento, concluído)
- Editar título e descrição do plano

### 2. Detalhe do Plano
- Ver itens/procedimentos adicionados
- Ver valores totais e breakdown (quantidade × valor unitário)
- Mudar status do tratamento

### 3. Adicionar Procedimento
- Selecionar procedimento da base de dados
- Informar dente/área (se aplicável - cirurgia, dentição)
- Definir quantidade e valor unitário
- Adicionar observação clínica

## Permissões

| Ação | Permissão | Perfil |
|------|-----------|--------|
| Ver planos | `tratamentos.view` | Fisioterapia, Admin |
| Criar plano | `tratamentos.create` | Admin, Fisioterapia (planejado) |
| Editar plano | `tratamentos.update` | Admin |
| Mudar status | `tratamentos.status` | Admin, Fisioterapia |

## Solução de Problemas

| Problema | Causa | Solução |
|----------|-------|---------|
| Módulo aparece como "planejado" | Configuração do catálogo para vertical fisioterapia | Comportamento esperado, aguardar atualização |
| Não consigo criar plano | Permissão `tratamentos.create` faltando | Solicitar permissão ao admin |
| Tela em branco ao acessar | Interface ainda não adaptada para fisioterapia | Aguardar atualização ou usar modo admin |
| Dados não salvam | Conexão com banco ou validação inválida | Verificar conexão, recarregar página |

## Dicas

- A estrutura completa já está no banco de dados
- Para odontologia (clínicas 1 e 6), o módulo funciona totalmente
- Fisioterapia terá interface completa em futura atualização
- Use a função "Visualizar planos" para conferir planos cadastrados mesmo em modo planejado