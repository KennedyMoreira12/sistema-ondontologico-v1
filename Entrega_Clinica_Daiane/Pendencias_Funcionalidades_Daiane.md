# Pendências e Funcionalidades Planejadas - Clínica Daiane

## Data: 09/11/2026
## Prioridade: Baixa/Média

---\n\n## 1. Pendências Técnicas\n\n| ID | Funcionalidade | Descrição | Status | Prioridade | Responsável |
|----|---------------|-----------|--------|------------|-------------|
| TRAT-01 | Adaptar tela de tratamentos para fisioterapia | Desenvolver interface específica para vertical fisioterapia no módulo de tratamentos. O módulo atual aparece como "planejado" devido à configuração do catálogo. | 🟸 Planejado | Média | Time de Desenvolvimento |
| TRAT-02 | Relatório de evolução de tratamentos | Criar relatório específico com histórico de evolução clínica, itens aplicados e progresso por paciente. | ⏳ Pendente | Baixa | Time de Desenvolvimento |
| TURM-01 | Relatório avançado de turmas | Desenvolver relatório com analytics de ocupação, taxa de presença por profissional e projeção de novas turmas. | ⏳ Pendente | Baixa | Time de Desenvolvimento |
| ATEND-01 | Integração com sistema de lembrete | Integrar sistema de lembretes automáticos (SMS/email) para consultas e sessões de pacote. | ⏳ Pendente | Média | Time de Desenvolvimento |

---\n\n## 2. Funcionalidades Futuras (Roadmap)\n\n| ID | Funcionalidade | Descrição | Status Estimado | Prioridade |
|----|---------------|-----------|----------------|------------|
| FUT-01 | Aplicativo móvel nativo | Aplicativo para iOS/Android com acesso aos módulos Turmas, Atendimentos e Pacientes. | ⏰ Futuro | Média |
| FUT-02 | Integração com prontuário eletrônico | Conexão com sistema de prontuário para integração completa do histórico do paciente. | ⏰ Futuro | Alta |
| FUT-03 | Relatórios personalizáveis | Editor de relatórios arrastar-e-soltar para criar relatórios gerenciais sob medida. | ⏰ Futuro | Média |
| FUT-04 | Pagamento integrado | Integração com gateway de pagamento para consumíveis e planos adicionais. | ⏰ Futuro | Baixa |
| FUT-05 | API pública | Exposição de API REST para integração com sistemas externos (faturamento, convênios). | ⏰ Futuro | Média |

---\n\n## 3. Itens Validados e Confirmados ✅

| Item | Status | Comentário |
|------|--------|------------|
| Turmas liberadas para fisioterapia | ✅ Confirmado | Permissão `pacotes.view` concedida via banco de dados |
| Atendimentos funcionando | ✅ Confirmado | Tela 200 OK, pode ficar vazia sem agendamentos |
| Tratamentos por catálogo | ✅ Confirmado | FISIOTERAPIA => PLANEJADO (comportamento esperado) |
| Permissões clinic_id=5 vs clinic_id=1 | ✅ Confirmado | Isolamento total, nenhum vazamento |
| Testes automatizados (764 testes) | ✅ Confirmado | 0 falhas, todos passando |
| Backup 20261109-142351 | ✅ Confirmado | 69 MB, integridade verificada |
| Manual de treinamento | ✅ Confirmado | 7 seções completas |
| Página HTML treinamento | ✅ Confirmado | Recuperada do commit 93802c0, corrigida para local |

---\n\n## 4. Restrições Manteridas ✅

- [x] Trabalhar no ambiente local somente
- [x] Não executar novo deploy
- [x] Não fazer Git push
- [x] Não modificar dados de produção
- [x] Não executar migrations em produção
- [x] Não excluir arquivos ou backups
- [x] Não executar reset/clean/checkout destrutivo
- [x] Não alterar configurações de outros tenants (clinic_id=1 preservado)

---\n\n## 5. Próximos Passos Recomendados

| Prioridade | Ação | Prazo |
|------------|------|-------|
| Alta | Validar página HTML treinamento em navegadores (Chrome, Edge, Firefox, Mobile) | Imediato |
| Média | Testar permissões em ambiente de staging antes de qualquer produção | Próxima semana |
| Baixa | Implementar TRAT-01 (tela fisioterapia) quando disponível | Próximo sprint |
| Baixa | Solicitar relatórios avançados TURM-01 e ATEND-01 | Próximo mês |
| Média | Confirmar backup restauraciónem ambiente de teste | Próximo trimestre |

---\n\n---\n\n**Fim do Documento de Pendências e Funcionalidades** 📋