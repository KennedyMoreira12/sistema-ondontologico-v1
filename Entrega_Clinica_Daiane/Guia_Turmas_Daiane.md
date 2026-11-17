# Guia de Turmas - Clínica Daiane

## Instruções Rápidas

1. Acesse o módulo **Turmas** pelo menu lateral do dashboard
2. Selecione o período desejado usando o selector de data no topo da página
3. Confira a grade semanal de turmas (segunda a sexta)
4. Clique em \"Presença\" para cada turma ao final da consulta
5. Verifique o contador de pacientes inscritos ao lado de cada turma
6. Use o botão \"Relatórios\" no topo para gerar relatórios

## Grade Semanal de Turmas

```text
┌─────────────────────────────────────────────────────┐
│  📅 SEMANA DE 14/11 A 20/11                          │
├─────────────────────────────────────────────────────┤
│  🪐 Segunda-feira 14/11                              │
│  ┌─────────────────────────────────────────────┐     │
│  │ 08:00 - 09:00 | Turma Iniciantes A | 5/10 │     │
│  │                                             |     │     │
│  │ 10:00 - 11:00 | Turma Intermediária  | 8/10 │     │
│  │                                             |     │     │
│  │ 14:00 - 15:00 | Turma Avançada     | 3/10 │     │
│  └─────────────────────────────────────────────┘     │
├─────────────────────────────────────────────────────┤
│  Terça-feira 15/11                                    │
│  (padrão: mesma estrutura da segunda-feira)          │
├─────────────────────────────────────────────────────┤
│  Quarta-feira 16/11                                   │
│  (padrão: mesma estrutura)                           │
├─────────────────────────────────────────────────────┤
│  Quinta-feira 17/11                                   │
│  (padrão: mesma estrutura)                           │
├─────────────────────────────────────────────────────┤
│  Sexta-feira 18/11                                    │
│  (padrão: mesma estrutura)                           │
├─────────────────────────────────────────────────────┤
│  📊 TOTAL: 12 turmas | 89 alunos | 8 com vagas | 4 cheias │
└─────────────────────────────────────────────────────┘
```

## Permissões Necessárias

| Ação | Permissão | Perfil |
|------|-----------|--------|
| Ver turmas | `turmas.view` | Todos os perfis |
| Marcar presença | `turmas.presenca` | Recepção, Fisioterapia |
| Criar turma | `turmas.create` | Admin, Instrutor |
| Editar turma | `turmas.update` | Admin |
| Remover turma | `turmas.delete` | Admin |

## Problemas Comuns

| Problema | Causa | Solução |
|----------|-------|---------|
| Tela vazia | Sem turmas cadastradas para o período | Cadastrar turmas no sistema |
| Botão "Presença" disabled | Sem permissão `turmas.presenca` | Solicitar permissão ao admin |
| Contador errado | Dados inconsistentes | Verificar cadastro de pacientes na turma |
| Grade não carrega | Erro de conexão | Recarregar página, limpar cache |

## Dicas de Utilização

- Verifique a consistência dos dados semanalmente
- Utilize o filtro por profissional para relatórios específicos
- Mantenha o cadastro de pacientes atualizado
- Use os relatórios para planejar novas turmas
- Observe o nível de ocupação (5/10, 8/10, etc.) para definir capacidade