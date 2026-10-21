# LJ SmartClinic — Documentação do projeto

Documento de entrada da documentação do LJ SmartClinic. Cada fase executada
tem um documento próprio, e a ordem de leitura abaixo é a ordem em que o
trabalho foi feito.

**Estado atual:** fases A, B, C, D e E executadas em desenvolvimento local,
com **commits locais e sem publicação**. Nenhuma alteração foi feita em
produção.

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

Estado na última execução: **494 testes · 493 passam · 1 falha conhecida**
(`SocialPostPanelImageTest`, anterior a este trabalho e fora do escopo
autorizado) · 2 *risky*. Detalhes em
[Fase E](FASE_E_DOCUMENTACAO_E_TESTES.md).
