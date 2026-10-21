# FASE D — TREINAMENTO: MÓDULOS PADRONIZADOS, CELULAR E ACESSIBILIDADE

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Ambiente:** Desenvolvimento local (somente) — **nenhuma alteração em produção**
**Status:** Concluída — commits locais, **sem push**

---

## 1. Sumário executivo

A Fase D padroniza o que a clínica vê no dia a dia: o menu deixou de ser uma
lista de `<a>` escritos à mão e passou a ser **gerado a partir de um catálogo
único de módulos**; todo módulo que ainda não foi entregue para a vertical da
clínica abre **a mesma tela padrão**, com a mensagem

> **Funcionalidade planejada — ainda não disponível para uso.**

O catálogo também passou a ser a fonte da vertical: o `VerticalManager` deixou
de manter duas listas paralelas de módulos exclusivos e agora lê essa
informação do próprio catálogo, para as duas listas não divergirem com o
tempo.

Além disso, o menu lateral foi corrigido para celular e teclado (o menu
ficava preso aberto atrás do botão que o abre) e o link quebrado do GitHub
Pages foi corrigido.

| Item | Antes | Depois |
|---|---|---|
| Menu lateral | 17 `<a>` escritos à mão | gerado do `CatalogoModulos` |
| Tela de módulo indisponível | "Este módulo está em desenvolvimento…" (view quebrada) | "Funcionalidade planejada — ainda não disponível para uso." |
| Vertical x módulos | duas listas no `VerticalManager` | uma, no catálogo |
| Menu no celular | abria e não fechava | abre, fecha, fecha ao tocar fora, fecha com `Esc` |
| Teclado | sem atalho para o conteúdo | "Pular para o conteúdo" + foco visível |
| Link do repositório nos documentos | `sistema-odontontologico-v1.git` (quebrado) | `sistema-ondontologico-v1.git` |

---

## 2. O catálogo de módulos

`app/Support/Modulos/CatalogoModulos.php` é a **fonte única** de verdade. Para
cada módulo o produto declara: nome, ícone, seção do menu, para onde ele leva,
a que vertical pertence, em que situação está em cada vertical e o que o
módulo faz.

### 2.1. Os 12 módulos padronizados

O **núcleo clínico** da clínica — os módulos que toda clínica tem, seja de
odontologia seja de fisioterapia — e que por isso recebem o mesmo tratamento:

| # | Módulo | Rota | Odontologia | Fisioterapia |
|---|---|---|---|---|
| 1 | Pacientes | `pacientes.index` | disponível | disponível |
| 2 | Agenda | `agenda.index` | disponível | disponível |
| 3 | Prontuário | `pacientes.index` | disponível | disponível |
| 4 | Odontograma | `pacientes.index` | disponível | **planejado** |
| 5 | Avaliação | `pacientes.index` | **planejado** | disponível |
| 6 | Evolução | `pacientes.index` | **planejado** | disponível |
| 7 | Pacotes de sessões | `pacientes.index` | **planejado** | disponível |
| 8 | Tratamentos | `pacientes.index` | disponível | **planejado** |
| 9 | Orçamentos | `pacientes.index` | disponível | disponível |
| 10 | Financeiro | `financeiro.index` | disponível | disponível |
| 11 | Convênios | `convenios.index` | disponível | disponível |
| 12 | Profissionais | `profissionais.index` | disponível | disponível |

Os **5 módulos de apoio** — Procedimentos, Estoque, Contratos, Relatórios e
Documentos — também estão no catálogo e recebem o mesmo tratamento; entram
na seção "Gestão" do menu.

### 2.2. Por que "Tratamentos" está planejado na fisioterapia

O plano de tratamento atual é **odontológico**: o item do plano liga o
procedimento a um **dente** (`treatment_plan_items.dente`, de 11 a 48), e o
próprio formulário sugere "Reabilitação dentária". Mostrar esse formulário
para uma clínica de fisioterapia seria entregar uma tela odontológica como se
fosse fisioterapia.

Por isso, para a vertical de fisioterapia o módulo fica **planejado** — e a
clínica vê a tela padrão. Nada foi removido: as tabelas continuam existindo,
os dados continuam guardados e, para a odontologia (a Malheiro), **o módulo
continua exatamente como estava**.

### 2.3. Por que o odontograma continua escondido da fisioterapia

A regra do produto é que a fisioterapia **não vê** o odontograma. Isso não
mudou: o item continua fora do menu e a URL digitada é recusada no servidor
(com aviso), sem abrir nenhuma tela do odontograma. O catálogo apenas passa a
**descrever** essa situação em vez de repeti-la espalhada por vários arquivos.

---

## 3. A tela padrão de módulo planejado

Uma tela só, para todos os casos:

* rota `GET /modulos/{modulo}` (`modulos.planejado`), dentro do grupo
  `auth` + `tenant`;
* view `resources/views/modulos/planejado.blade.php`;
* mensagem fixa: **"Funcionalidade planejada — ainda não disponível para uso."**

O endereço antigo do menu (`/dashboard?modulo=...`) continua funcionando e
passa pelo mesmo resolvedor (`App\Services\Modulos\ResolutorModulo`), de modo
que **link interno, URL digitada e item de menu nunca discordam entre si**.

A view antiga `em-desenvolvimento.blade.php` foi removida: ela usava
`<x-app-layout>`, componente que **não existe** no projeto — a tela só
"funcionava" porque nenhum teste a renderizava de verdade.

### 3.1. Regras do resolvedor

| Situação | O que acontece |
|---|---|
| Módulo de outra vertical | recusa no servidor (aviso + volta ao dashboard) |
| Módulo `disponivel` na vertical | redireciona para a tela do módulo |
| Módulo `planejado` na vertical | mostra a tela padrão |
| Slug fora do catálogo | mostra a tela padrão, com título genérico |

---

## 4. Celular e acessibilidade

| Mudança | Motivo |
|---|---|
| Botão de fechar dentro do menu (visível só no celular) | o menu ficava preso aberto **atrás** do botão que o abre |
| Cortina escurecida ao abrir o menu | dava para ver e tocar no conteúdo que estava por baixo |
| Fechar ao tocar fora, ao escolher um item e com `Esc` | teclado e dedo saem do menu |
| `aria-expanded` / `aria-controls` no botão | o estado do menu passou a ser lido por leitor de tela |
| `aria-current="page"` no item ativo | o item atual da página é anunciado |
| `aria-label="Menu principal"` no `<nav>` | o menu é identificado por nome |
| "Pular para o conteúdo" | teclado não precisa percorrer o menu inteiro |
| `id="scoConteudo"` e `tabindex="-1"` no `<main>` | o atalho leva o foco para o conteúdo |
| Foco visível (`focus-visible`) | navegar só pelo teclado passou a ser visível |

O JavaScript do menu fica **inline no layout**: assim o celular funciona sem
depender de recompilar o bundle. O CSS novo (atalho, cortina, foco) está em
`resources/scss/app.scss` e **precisa de `npm run build`** para aparecer.

---

## 5. Documentação: link quebrado do GitHub Pages

O documento da Fase A.2 registrava o repositório com um `d` a mais:

```
https://github.com/KennedyMoreira12/sistema-odontontologico-v1.git   ← quebrado
https://github.com/KennedyMoreira12/sistema-ondontologico-v1.git    ← correto
```

O link foi corrigido e conferido contra o remote real (`git remote -v`). A
varredura do repositório inteiro não achou nenhum outro link do projeto
apontando para o GitHub.

---

## 6. Provas (testes)

`tests/Feature/CatalogoModulosTest.php` — 11 cenários:

1. o catálogo declara os **12 módulos padronizados**;
2. todo módulo do catálogo tem os campos esperados e **rota existente**;
3. módulo planejado mostra a **mensagem padrão**;
4. módulo disponível redireciona para a própria tela;
5. módulo de outra vertical **não abre**;
6. o endereço antigo do menu continua apontando para a tela certa;
7. o item planejado do menu aponta para a tela padrão;
8. a clínica odontológica **continua vendo o odontograma** (e nada planejado);
9. a clínica de fisioterapia **não vê o odontograma**;
10. o layout tem atalho de teclado e menu acessível;
11. o item ativo do menu marca a página atual.

`tests/Feature/DashboardTest.php` foi atualizado: o teste do placeholder
agora confere a tela padrão e a mensagem exata.

### 6.1. Suíte completa

**494 testes · 493 passam · 1 falha conhecida** (a mesma de antes desta fase:
`SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook`,
que **não** foi corrigida por estar fora do escopo autorizado) · 2 *risky*.

---

## 7. O que NÃO foi feito (de propósito)

* **Nada foi publicado**: sem `push`, sem deploy, sem alteração em produção.
* **Nenhuma migration** nesta fase — nenhuma tabela mudou.
* **Nenhum dado real**: as duas clínicas dos testes são fictícias
  ("Clínica Odontológica" e "Clínica de Fisioterapia").
* **A Malheiro não foi tocada**: na vertical de odontologia nenhum módulo
  mudou de situação, de rota ou de tela.
* **A falha conhecida de `SocialPostPanelImageTest` segue aberta** — corrigir
  esse teste não estava autorizado.

---

## 8. Pendências para a Fase E / para a cliente

Continuam em aberto, e **bloqueiam parte do escopo comercial**:

1. os 10 pontos de confirmação da cliente (contratos obrigatórios, pacote ou
   sessão avulsa, convênio na v1, ficha fixa ou configurável, múltiplas
   unidades/salas, dados oficiais de marca, CREFITO e especialidades, texto
   jurídico do contrato, repositório do GitHub Pages, Malheiro intocada);
2. o modelo de **plano de tratamento da fisioterapia** — enquanto não existir,
   o módulo fica planejado para essa vertical;
3. a migração dos documentos já existentes em
   `storage/app/public/documentos/` para o novo caminho isolado por clínica;
4. backup externo automatizado (hoje a cópia é manual, em destino removível).

---

**Documentos relacionados:** [Fase A](FASE_A_ESTABILIZACAO.md) ·
[Fase B](FASE_B_SEGURANCA.md) · [Auditoria funcional](AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md)
