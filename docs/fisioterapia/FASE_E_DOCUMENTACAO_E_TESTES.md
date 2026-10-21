# FASE E — DOCUMENTAÇÃO E TESTES PERMANENTES

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Ambiente:** Desenvolvimento local (somente) — **nenhuma alteração em produção**
**Status:** Concluída — commits locais, **sem push**

---

## 1. Sumário executivo

A Fase E fecha o ciclo de documentação e prova do trabalho: **cada fase
entregue tem um documento** e **cada decisão de segurança ou de domínio tem
um teste que falha se a decisão for desfeita**.

O que mudou nesta fase:

1. a Fase C, que tinha sido executada sem documento próprio, foi documentada
   (`FASE_C_ADAPTACAO_FISIOTERAPIA.md`);
2. a Fase D ganhou documento (`FASE_D_TREINAMENTO.md`);
3. a documentação ganhou um **índice** (`INDICE.md`) com os links corrigidos —
   o único link quebrado do repositório (o nome do repositório, com um `d` a
   mais) foi corrigido;
4. a suíte de testes permanente foi conferida e está **verde**, com uma única
   falha conhecida e **já mapeada**.

---

## 2. Inventário da documentação

| Documento | Cobre |
|---|---|
| [INDICE.md](INDICE.md) | mapa da documentação e ordem de leitura |
| [AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md](AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md) | diagnóstico do produto: o que existe, o que falta, o que está partido |
| [FASE_A_ESTABILIZACAO.md](FASE_A_ESTABILIZACAO.md) | o ponto de partida: escopo, fases e o que **não** foi autorizado |
| [FASE_A1_BACKUP_EXTERNO.md](FASE_A1_BACKUP_EXTERNO.md) | backup validado fora do servidor (pendrive), com hash e teste de restauração |
| [FASE_A2_ORGANIZACAO_GIT.md](FASE_A2_ORGANIZACAO_GIT.md) | branch de trabalho, commits, bundle e patch; **link do repositório corrigido** |
| [FASE_B_SEGURANCA.md](FASE_B_SEGURANCA.md) | isolamento por clínica, autorização no servidor, cache/fila, auditoria |
| [FASE_C_ADAPTACAO_FISIOTERAPIA.md](FASE_C_ADAPTACAO_FISIOTERAPIA.md) | vertical, CREFITO, avaliação, evolução, pacotes, inventário de tabelas, marca |
| [FASE_D_TREINAMENTO.md](FASE_D_TREINAMENTO.md) | catálogo de módulos, tela padrão de "planejado", menu, celular e acessibilidade |

**Convenção de escrita:** os documentos são UTF-8 sem BOM, com tabelas e
listas — nada de capture de tela ou de segredo de produção. Nenhum documento
contém credencial, senha, token ou dado real de paciente.

---

## 3. Inventário dos testes permanentes

Cada teste existe para uma decisão. Se a decisão voltar atrás, o teste
aponta a mudança — e não um erro de digitação.

### 3.1. Isolamento e segurança (Fase B)

| Arquivo | O que prova |
|---|---|
| `TenantIsolationTest.php` | dados de uma clínica não aparecem na outra |
| `IsolamentoCacheTest.php` | a chave de cache é por clínica; nada é reaproveitado de outro contexto |
| `OrdemMiddlewareTenantOrderTest.php` | a resolução do tenant roda antes de qualquer outro middleware de dados |
| `ProvisionamentoClinicaTest.php` | clínica nova nasce com banco, papéis e permissões certainos |
| `MigrationsFisioterapiaTest.php` | o ciclo de **subida e descida** das migrations da Fase C |

### 3.2. Domínio da fisioterapia (Fase C)

| Arquivo | O que prova |
|---|---|
| `VerticalTest.php` | a vertical vem do registro central; a fisioterapia **não vê** o odontograma; o bloqueio é no servidor |
| `AvaliacaoTest.php` | ficha nasce rascunho, finaliza por ação explícita, finalizada é imutável, auditoria registrada |
| `EvolucaoSessaoTest.php` | evolução por sessão com assinatura e auditoria |
| `PacoteSessaoTest.php` | consumo em transação, sem saldo negativo, erro tratado como aviso |
| `AuditoriaTabelasTest.php` | inventário de tabelas por vertical |
| `IdentidadeVisualTest.php` | logo e cores configuráveis pela própria clínica |

### 3.3. Produto e navegação (Fase D)

| Arquivo | O que prova |
|---|---|
| `CatalogoModulosTest.php` | os **12 módulos padronizados**, a mensagem padrão de módulo planejado, o módulo de outra vertical não abre, o menu acessível |
| `DashboardTest.php` | o endereço antigo do menu continua apontando para a tela certa |

### 3.4. Regressão dos módulos que já existiam

`ContratoTest`, `ConvenioTest`, `EstoqueTest`, `FinanceiroTest`,
`OdontogramaTest`, `OrcamentoTest`, `ProcedimentoTest`, `ProfissionalTest`,
`RelatorioTest`, `SocialPanelTest`, `WhatsApp*Test` etc. — cobrem os módulos
que **não** foram mexidos pelas fases B, C e D. É a rede de segurança da
odontologia (a Malheiro).

---

## 4. Como rodar

```powershell
# Suíte completa
php artisan test --compact

# Só as provas de uma fase
php artisan test --compact tests/Feature/VerticalTest.php
php artisan test --compact tests/Feature/CatalogoModulosTest.php

# Uma prova específica
php artisan test --compact --filter=test_modulo_planejado_mostra_a_mensagem_padronizada
```

Os testes rodam em **SQLite**: não tocam no MySQL local nem em nenhum banco
de produção. As clínicas dos testes são fictícias ("Clínica Odontológica" e
"Clínica de Fisioterapia").

### 4.1. Estado atual da suíte

```
494 testes · 493 passam · 1 falha conhecida · 2 risky
```

A falha conhecida é **anterior** a este trabalho e **não foi corrigida de
propósito**:

```
SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook
  esperado: http://localhost:8000/social/99
  obtido:   http://localhost:8000
```

Corrigir esse teste não estava no escopo autorizado, e mexer nele às cegas
correria o risco de **mascarar** o defeito que ele existe para denunciar.

---

## 5. O que a documentação NÃO promete

* **Não há promessa de data.** As fases A–E foram executadas; o que falta
  para o produto comercial são as decisões da cliente (item 7).
* **Não há dado real.** Nenhuma clínica, paciente ou profissional real foi
  criado, alterado ou importado.
* **Não há publicação.** Nenhum `push`, nenhum deploy, nenhuma alteração em
  produção, nenhuma migration em banco de produção.
* **A falha conhecida continua aberta**, e está documentada aqui e no
  documento da Fase D.

---

## 6. Estado do branch de trabalho

Branch: `feature/fisioterapia` — **23 commits locais, sem `push`**.
`origin/main` continua em `7cb2f05`.

```
git log --oneline origin/main..HEAD
```

Os quatro commits desta fase:

```
f668620 feat(modulos): catalogo unico com os 12 modulos padronizados e tela padronizada
d5f6edb feat(menu): menu lateral gerado pelo catalogo, com celular e acessibilidade
d468565 docs: corrige o link quebrado do repositorio e documenta as fases C, D e E
abb38e3 test: cobertura da fase D (catalogo, tela padronizada e acessibilidade)
```

O trabalho está em commits separados por funcionalidade (por exemplo: um
commit para o catálogo, outro para a tela padrão, outro para o menu, outro
para a documentação, outro para os testes), o que permite revisar e, se for
preciso, desfazer cada parte isoladamente.

---

## 7. Pendências — o que ainda depende de decisão

Continuam **abertas** (não são bloqueios técnicos, são decisões de
produto e de cliente):

1. **Os 10 pontos de confirmação da cliente**: contratos obrigatórios?;
   pacote, sessão avulsa ou convênio?; convênios entram na v1?; ficha de
   avaliação fixa ou configurável?; múltiplas unidades/salas?; dados oficiais
   de marca; CREFITO e especialidades; texto jurídico do contrato;
   repositório do GitHub Pages; a Malheiro fica intocada?
2. **Plano de tratamento da fisioterapia** — enquanto não existir modelo
   próprio, o módulo fica **planejado** para essa vertical (e não é exibido
   como tratamento odontológico).
3. **Documentos já existentes** em `storage/app/public/documentos/` ainda não
   migrados para o caminho isolado por clínica.
4. **Backup externo automatizado** — hoje a cópia é manual, em destino
   removível; o agendamento do servidor cobre o backup interno, não o externo.
5. **Limpeza pendente**: 15 arquivos `.gz` de 20 bytes e `test_feature.out`
   (0 byte) aguardam decisão — foram deixados intocados.

---

## 8. Como continuar

1. Ler o [índice](INDICE.md) e a [auditoria funcional](AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md);
2. confirmar os 10 pontos com a cliente;
3. implementar o modelo de plano de tratamento da fisioterapia e marcar o
   módulo como disponível **no catálogo** (uma linha, em
   `app/Support/Modulos/CatalogoModulos.php`);
4. revisar e **publicar** o branch (push + deploy), que é a única etapa
   ainda **não autorizada**.

---

**Documentos relacionados:** [Índice](INDICE.md) · [Fase A](FASE_A_ESTABILIZACAO.md) ·
[Fase B](FASE_B_SEGURANCA.md) · [Fase C](FASE_C_ADAPTACAO_FISIOTERAPIA.md) ·
[Fase D](FASE_D_TREINAMENTO.md)
