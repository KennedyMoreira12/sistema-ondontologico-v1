# ETAPA 6 — Validação, Publicação e Rollback

## 6.1 Objetivo
Validar a substituição das planilhas da Daiane pelo LJ SmartClinic em um ambiente de demonstração controlado, registrar evidências de que as telas operam de ponta a ponta e preparar o procedimento de publicação e rollback para execução somente após autorização humana.

## 6.2 Conclusão da Implementação
A etapa 6 não introduziu funcionalidade nova de negócio. Ela criou um tenant de demonstração fiel à Daiane (`clinic_id=5` / `clinic_000005`), percorreu as telas e ações principais, corrigiu um defeito de período encontrado nessa validação e documentou o caminho de publicação. A publicação em produção **não foi executada**.

## 6.3 Ambiente de Demonstração

O ambiente usa a configuração `.env.demo` já existente, com SQLite fora do banco de produção:

- Central: `storage/app/demo/central.sqlite`
- Tenant: `storage/app/demo/tenants/clinic_000005.sqlite`
- Login: `daiane.demo@ljdemo.test` / `demo12345`

### 6.3.1 Preparação executada

```powershell
php artisan migrate --env=demo --force --no-interaction
php artisan tenant:create "Clínica Daiane Teixeira (demo)" --id=5 `
    --email=daiane.demo@ljdemo.test --admin="Daiane Teixeira" `
    --password=demo12345 --vertical=fisioterapia --plan=pro `
    --env=demo --no-interaction
php artisan tenants:seed --clinic=5 --demo-daiane --env=demo --no-interaction
```

A opção `--id=5` existe apenas para ambientes `local`, `demo` ou `testing`; o fluxo normal de onboarding continua deixando o banco escolher o próximo ID. O `--demo-daiane` reaproveita `DemoFisioterapiaSeeder` e acrescenta catálogo e despesas da operação da Daiane.

### 6.3.2 Dados semeados no tenant demo

| Entidade | Quantidade |
|----------|-----------:|
| Pacientes | 6 |
| Atendimentos | 19 |
| Pagamentos | 3 (2 vinculados + 1 importado sem vínculo) |
| Contas a receber | 3 |
| Contas a pagar | 6 |
| Baixas de contas a pagar | 3 |
| Pacotes de sessão | 5 |
| Consumos de sessão | 9 |
| Fornecedores | 4 |
| Procedimentos | 14 |

Os volumes reais da produção (767 pacientes, 1.362 atendimentos, 6.163 pagamentos, 94 fornecedores, 369 pacotes) foram apenas lidos na Etapa 2 e **não** foram copiados para o demo. O demo usa dados fictícios com os mesmos fluxos.

## 6.4 Validação Local Executada

### 6.4.1 Telas

Todas as rotas abaixo responderam HTTP 200 com o usuário da clínica demo:

- `/agenda`, `/agenda/dia`, `/agenda/mes`
- `/atendimentos`
- `/financeiro`, `/financeiro/pagamentos`, `/financeiro/contas-pagar`, `/financeiro/fornecedores`, `/financeiro/fluxo-caixa`, `/financeiro/duplicatas`
- `/relatorios`, `/relatorios/receitas`, `/relatorios/presenca-faltas`, `/relatorios/pacotes`

Também foi conferido o conteúdo renderizado: serviços de Pilates, massagem e laser na agenda; atendimentos do dia com pacote e saldo; despesas da planilha CONTAS; receitas do mês corrente; e o lançamento importado aparecendo como “não vinculado” para revisão.

### 6.4.2 Ações

- Presença: atendimento confirmado passou para `atendido`.
- Consumo de sessão: o pacote de 10 sessões passou de 3 para 4 usadas e o `consumo_sessao` foi criado para o atendimento.
- Baixa de conta a pagar: o aluguel de R$ 3.200,00 recebeu baixa parcial de R$ 100,00 e passou a exibir saldo de R$ 3.100,00.
- Revisão de pagamento: o lançamento importado foi vinculado a um paciente pela tela de revisão.

Depois da validação, o tenant demo foi restaurado ao estado produzido pelos seeders (presença, consumo, baixa e vínculo de teste foram desfeitos) para que a demonstração comece limpa.

### 6.4.3 Defeito encontrado e corrigido

O relatório de receitas comparava a coluna `data` com `Y-m-d`. No SQLite a coluna guarda data e hora, então um pagamento lançado às 18h30 do último dia do período ficava fora do relatório e do CSV. A correção passou a comparar com início e fim do dia (`startOfDay()` / `endOfDay()`) em `RelatorioController`, incluindo o resumo financeiro, o fluxo de caixa do relatório, as baixas de contas a pagar e os consumos de sessão. Foi adicionado teste de regressão em `RelatorioTest`.

## 6.5 Publicação em Produção
**Não executada.** Nenhuma alteração de schema, migration, deploy ou push foi feita em produção. A Etapa 2 foi somente leitura e a validação desta etapa ocorreu no ambiente demo em SQLite.

## 6.6 Rollback
**Preparado, não executado.** O procedimento está no relatório final. Ele parte de backup do banco do tenant e da tag anterior do código; nenhum comando destrutivo deve ser executado sem autorização da responsável pela clínica.

## 6.7 Pendências e Validações Humanas

- **Pendência (I):** A Daiane deve confirmar se os relatórios reproduzem os números que ela lia nas planilhas. A diferença entre 6.174 lançamentos extraídos e 6.163 pagamentos gravados depende do banco original e continua sem explicação fechada; não foi forçada igualdade.
- **Pendência (II):** Confirmar a regra de reposição de faltas. Hoje a falta não consome e não devolve sessão; a reposição é um ato explícito.
- **Pendência (III):** Confirmar como vincular pacientes antigos com nomes parecidos. Não há merge automático; cada vínculo é revisado por uma pessoa.
- **Pendência (IV):** Confirmar o fluxo fiscal NFSe (SendNF segue mock; nenhum documento fiscal real foi emitido).
- **Validação humana:** O protocolo de nove tarefas de `ETAPA_6_VALIDACAO.md` ainda precisa ser aplicado com a cliente.

## 6.8 Status
**Validação local concluída; publicação pendente de autorização humana.** O ambiente demo está reproduzível pelos comandos da seção 6.3.1.
