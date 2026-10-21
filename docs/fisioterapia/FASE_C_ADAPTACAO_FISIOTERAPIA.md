# FASE C — ADAPTAÇÃO DO NÚCLEO À FISIOTERAPIA

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Ambiente:** Desenvolvimento local (somente) — **nenhuma alteração em produção**
**Status:** Concluída — commits locais, **sem push**

---

## 1. Sumário executivo

A Fase C adaptou o núcleo SaaS para a clínica de fisioterapia **sem reescrever
o núcleo** e **sem tocar na clínica odontológica**. A regra que valeu em todas
as etapas: a vertical é uma **flag** no registro central da clínica, e a
decisão sobre o que a clínica enxerga é sempre tomada **no servidor**.

| Etapa | Entrega | Commit |
|---|---|---|
| C1 | Flag de vertical por clínica (`clinics.vertical`) + bloqueio de módulos no servidor | `9c1e14e` |
| C2 | Registro no conselho (CREFITO) **sem renomear** o `cro` | `b7af01b` |
| C3 | Ficha de avaliação inicial, sem diagnóstico automático | `751f3a8` |
| C4 | Evolução por sessão, com assinatura e auditoria | `d980d48` |
| C5 | Pacotes de sessões com consumo transacional | `902d6f2` |
| C6 | Inventário de tabelas por vertical + comando de auditoria | `454897c` |
| C7 | Identidade visual configurável pela própria clínica | `8f4f821` |

---

## 2. C1 — a vertical é uma flag, e vale no servidor

`clinics.vertical` (registro **central**, migration
`2026_10_21_000001_add_vertical_to_clinics_table.php`) assume
`odontologia` por padrão — a clínica que já está em uso **não muda de
identidade** porque ganhou uma coluna.

* `App\Services\Tenant\VerticalManager` centraliza a regra;
* o middleware `EnsureVertical` (alias `vertical`) recusa no servidor o
  módulo que não é da vertical da clínica — inclusive quando a URL é montada
  à mão;
* o middleware aceita tanto o **nome do módulo** (`odontograma`) quanto o
  **nome da vertical** (`fisioterapia`);
* os helpers `vertical()` e `vertical_permite()` leem a vertical do contexto
  autenticado.

**O que a Malheiro ganhou:** nada. `odontologia` é o padrão, e o odontograma
continua exatamente onde estava.

---

## 3. C2 — conselho profissional sem renomear nada

`Professional::TIPOS` ganhou `fisioterapeuta`; `dentista` continua sendo o
padrão e o campo `cro` **continua existindo** (migration aditiva
`2026_10_21_000002_add_registro_profissional_to_professionals_table.php`).

* `registro_profissional` é **obrigatório** quando o tipo é `fisioterapeuta`;
* `Professional::registroConselho()` decide **o que aparece** na tela: o CRO
  do dentista, o CREFITO do fisioterapeuta.

Nada foi removido nem renomeado: quem já tem dados de dentista continua com
eles intactos.

---

## 4. C3 — ficha de avaliação inicial

Tabela nova `avaliacoes` (migration `2026_10_21_000003`), **sem tocar** em
nenhuma tabela de odontologia.

* A ficha **não diagnostica sozinha**: os campos de texto (`queixa_principal`,
  `exame_fisico`, `hipotese_clinica`, `objetivos`, `plano_terapeutico`) são
  livres e quem preenche é o profissional responsável. Não há score, não há
  conduta sugerida.
* A ficha **nasce `rascunho`** e só vira `finalizada` por ação explícita.
* **Finalizada é imutável**: editar ou apagar uma ficha finalizada devolve
  **403**, porque a auditoria depende dessa imutabilidade.
* Toda ação passa pelo `App\Services\AuditLogger` (quem fez, o quê, quando).

---

## 5. C4 — evolução por sessão

Tabela nova `evolucoes_sessao` (migration `2026_10_21_000004`).

* Uma linha por sessão atendida, com a mesma regra de rascunho → finalizada e
  a mesma imutabilidade da ficha.
* O vínculo com o paciente e com o profissional fica registrado na própria
  linha, e a auditoria registra quem assinou.

---

## 6. C5 — pacotes de sessões

Duas tabelas novas: `pacotes_sessao` (migration `2026_10_21_000005`) e
`consumo_sessao` (migration `2026_10_21_000006`).

O consumo é feito em **transação, na conexão do model**, com `lockForUpdate`
e com um `UPDATE` condicional (`whereColumn('sessoes_usadas', '<',
'total_sessoes')`). O resultado: **não existe saldo negativo** e **não
existe consumo sem débito**.

* A restrição `CHECK` é criada **só** em mysql/mariadb/pgsql — o SQLite de
  teste ignora a regra, e a garantia continua vindo do código;
* os erros viram exceções próprias (`SaldoDeSessoesInsuficiente`,
  `PacoteSessaoIndisponivel`) e chegam à tela como aviso (`flash error`), sem
  vazar stack trace.

### 6.1. Nomes de tabela fixados no model

O pluralizador do Eloquent gerava `pacote_sessaos`, `avaliacaos` etc., que
**não existem** no banco. Os quatro models fixaram o `$table`:
`avaliacoes`, `evolucoes_sessao`, `pacotes_sessao`, `consumo_sessao`.

---

## 7. C6 — inventário de tabelas por vertical

`App\Services\Tenant\InventarioTabelas` classifica cada tabela do banco da
clínica em `comum`, `odontologia` ou `fisioterapia`, e o comando
`php artisan tenants:auditar-tabelas` acusa três coisas:

1. tabela **sem classificação** — ninguém sabe de que vertical ela é;
2. tabela da vertical da clínica **ausente** — a functionality foi prometida e
   a tabela não existe;
3. tabela da **outra** vertical **removida** — sinal de que algo foi apagado
   por engano.

Prova: `tests/Feature/AuditoriaTabelasTest.php` (6 cenários).

---

## 8. C7 — identidade visual da clínica

A marca (logo + cor principal + cor secundária) mora no **registro central**
da clínica (`clinics.logo_path`, `clinics.primary_color`,
`clinics.secondary_color`) — a mesma fonte que o layout já lia, sem
regressão de cor para a clínica em uso.

* a clínica edita a **própria** marca em `admin/clinica/edit`;
* a plataforma edita vertical e ficha da clínica;
* o logo anterior é **apagado do disco** quando trocado ou removido;
* `clinic_logo_url()` resolve a URL, e o logo aparece no topo do menu.

**Nada de dado inventado**: a marca da Malheiro continua a que estava; a
fisioterapia entra com a marca que a própria clínica configurar.

---

## 9. Provas

| Arquivo | O que prova |
|---|---|
| `tests/Feature/VerticalTest.php` | a vertical decide no servidor; a fisioterapia não vê o odontograma |
| `tests/Feature/AvaliacaoTest.php` | ficha de avaliação, rascunho/finalizada, imutabilidade, auditoria |
| `tests/Feature/EvolucaoSessaoTest.php` | evolução por sessão com auditoria |
| `tests/Feature/PacoteSessaoTest.php` | consumo transacional, sem saldo negativo |
| `tests/Feature/AuditoriaTabelasTest.php` | inventário de tabelas por vertical |
| `tests/Feature/IdentidadeVisualTest.php` | logo e cores configuráveis pela clínica |
| `tests/Feature/MigrationsFisioterapiaTest.php` | o ciclo de subida **e descida** das migrations da Fase C |

**Migrations**: todas as migrations da Fase C são **aditivas** e têm `down()`
documentado — nada foi removido nem renomeado, e a prova de que o `down()`
funciona está em `MigrationsFisioterapiaTest`.

---

**Documentos relacionados:** [Fase A](FASE_A_ESTABILIZACAO.md) ·
[Fase B](FASE_B_SEGURANCA.md) · [Fase D](FASE_D_TREINAMENTO.md) ·
[Auditoria funcional](AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md)
