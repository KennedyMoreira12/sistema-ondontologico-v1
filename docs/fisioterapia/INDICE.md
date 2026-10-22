# LJ SmartClinic — Documentação do projeto

Documento de entrada da documentação do LJ SmartClinic. Cada fase executada
tem um documento próprio, e a ordem de leitura abaixo é a ordem em que o
trabalho foi feito.

**Estado atual:** fases A, B, C, D e E executadas em desenvolvimento local,
com **commits locais**. A branch `feature/fisioterapia` foi publicada no
GitHub em 22/10 (`origin/feature/fisioterapia`), mas **nada foi publicado em
`main` e nenhuma alteração foi feita em produção**. O checklist de publicação
e o relatório de limpeza estão prontos para quando o deploy for autorizado.

---

## Ordem de leitura

### 1. Diagnóstico

* [Auditoria funcional do LJ SmartClinic](AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md) —
  o que o produto tem, o que falta e o que está partido, com o tamanho de
  cada item.

### 2. Estabilização (Fase A)

* [Fase A — Estabilização](FASE_A_ESTABILIZACAO.md) — o escopo, as fases e o
  que **não** está autorizado (deploy, push, dados da Malheiro).
  * [A.1 — Backup externo](FASE_A1_BACKUP_EXTERNO.md) — cópia validada fora do
    servidor, com hash e teste de restauração.
  * [A.1-B — Backup externo automatizado](FASE_A1B_BACKUP_EXTERNO_AUTOMATIZADO.md)
    — cópia externa automática com verificação de hash e de conteúdo, e a
    correção do backup que saiu vazio por 14 noites em produção.
  * [A.2 — Organização Git](FASE_A2_ORGANIZACAO_GIT.md) — branch de trabalho,
    commits, bundle e patch.

### 3. Segurança multi-tenant (Fase B)

* [Fase B — Segurança](FASE_B_SEGURANCA.md) — isolamento por clínica,
  autorização no servidor, cache e fila por tenant, auditoria sem dado
  sensível em log.

### 4. Adaptação do domínio (Fase C)

* [Fase C — Adaptação à fisioterapia](FASE_C_ADAPTACAO_FISIOTERAPIA.md) —
  vertical por clínica, CREFITO sem renomear o CRO, ficha de avaliação,
  evolução por sessão, pacotes com consumo transacional, inventário de
  tabelas e identidade visual.

### 5. Padronização dos módulos (Fase D)

* [Fase D — Treinamento de módulos](FASE_D_TREINAMENTO.md) — catálogo único
  com os 12 módulos padronizados, tela padrão de "Funcionalidade planejada —
  ainda não disponível para uso.", menu gerado pelo catálogo, celular e
  acessibilidade.

### 6. Documentação e testes (Fase E)

* [Fase E — Documentação e testes](FASE_E_DOCUMENTACAO_E_TESTES.md) — inventário
  da documentação e da suíte permanente, como rodar, e o que continua
  pendente.

### 7. Encerramento

* [Checklist de publicação](CHECKLIST_PUBLICACAO.md) — a sequência conferida
  para publicar a branch quando isso for autorizado: as três travas, o passo a
  passo, o caminho de volta e o que não entra. **Preparado, não executado.**
* [Relatório de arquivos pendentes de limpeza](RELATORIO_LIMPEZA.md) — o que
  está sobrando, quanto ocupa, por que está lá e o que fazer com cada item.
  Inclui o diagnóstico dos **5,79 GB** de bancos de teste que a suíte deixava
  para trás. **Relatado, nada apagado.**

### Outros

* [Atendimento com IA e WhatsApp](../atendimento-ia-whatsapp.md) — o módulo
  de atendimento e as variáveis de ambiente.

---

## Convenções

* Os documentos são **UTF-8 sem BOM**.
* Nenhum documento contém **credencial, senha, token ou dado real** de
  paciente, clínica ou profissional.
* Tabelas e listas, sem imagem: o texto continua legível em terminal, em
  diff e no GitHub Pages.
* O estado de qualquer fase é sempre **o que foi feito, como foi provado e o
  que ficou de fora**.

---

## Suíte de testes

```powershell
php artisan test --compact
```

Estado na última execução: **515 testes · 514 passam · 1 falha conhecida**
(`SocialPostPanelImageTest`, anterior a este trabalho e fora do escopo
autorizado) · 2 *risky*. A suíte roda sem deixar banco de teste para trás:
o vazamento que ocupava 5,79 GB em `storage/framework/testing` foi
corrigido e travado por teste. Detalhes em
[Fase E](FASE_E_DOCUMENTACAO_E_TESTES.md).
