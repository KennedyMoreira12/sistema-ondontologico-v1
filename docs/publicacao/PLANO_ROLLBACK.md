# PLANO DE ROLLBACK — LJ SmartClinic

**Versão de referência:** commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Ambiente:** preparação exclusivamente local  
**Destino:** VPS `minha-vps` (179.236.238.61), projeto `/var/www/ljsmartclinic`

---

## 1. Objetivo

Definir o procedimento detalhado de retorno à versão anterior em caso de falha durante ou após a publicação, preservando dados e disponibilidade do sistema.

## 2. Pressupostos

- Uma versão anterior do código está preservada (tag ou branch anterior na VPS).
- Backups válidos existem (consulte `docs/publicacao/PLANO_BACKUP_RESTAURACAO.md`).
- Não existe comando de rollback de migrations por tenant neste projeto.

## 3. Quando executar o rollback

Executar se houver:
- Falha na inicialização da aplicação após a troca da versão.
- Erros de migration que corrompam dados ou schemas.
- Perda de isolamento entre tenants (vazamento de dados entre clínicas).
- Falha crítica em financeiro, agenda ou atendimentos que afete produção.
- Qualquer risco não resolvido de perda de dados ou indisponibilidade.

## 4. Procedimento de rollback

### 4.1 Fase imediata (manutenção)

1. Colocar a aplicação em manutenção:
   ```bash
   php artisan down
   ```
2. Interromper workers de fila, se aplicável.
3. Impedir novo acesso enquanto o rollback está em andamento.

### 4.2 Restauração do código

1. Voltar o código para a versão anterior (tag ou commit anterior estável):
   ```bash
   git checkout <tag-anterior>  # ou git reset --hard <commit-anterior> se aprovado
   ```
   > Não usar `git checkout` destrutivo se houver alterações locais não versionadas que precisem ser preservadas. Nesse caso, fazer `git stash` primeiro e documentar.

2. Restaurar dependências, se necessário:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Rebuildar assets, se houver alteração:
   ```bash
   npm run build
   ```

4. Limpar cache:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   composer dump-autoload
   ```

### 4.3 Restauração de bancos (somente quando necessário e após avaliação)

> **Não presumir que rollback de migration é sempre seguro.** O rollback seguro de banco é restaurar o backup, não reverter migrations.

1. Se migrations foram executadas e causaram problemas:
   - Restaurar o banco central do backup (consulte `PLANO_BACKUP_RESTAURACAO.md`).
   - Restaurar os bancos de tenants afetados do backup.
   - Não confiar em `php artisan migrate:rollback` para restauração de dados.

2. Se apenas o código foi alterado (sem migrations), a restauração de código é suficiente.

### 4.4 Recuperação de serviços

1. Reiniciar o servidor web (ex.: `systemctl reload nginx` ou `systemctl reload apache2`, conforme ambiente).
2. Reiniciar workers de fila (ex.: `systemctl restart ljsmartclinic-worker` ou equivalente).
3. Verificar que o agendador (cron) está ativo.

### 4.5 Validação após rollback

1. Acessar a aplicação e confirmar login.
2. Verificar identificação de tenant (Daiane `clinic_000005`, Malheiro `clinic_000001`, demais tenants).
3. Confirmar que agenda, financeiro, atendimentos e relatórios funcionam.
4. Verificar isolamento entre tenants (sem vazamento de dados).
5. Confirmar integridade da aplicação (sem erros 500 em rotas críticas).

### 4.6 Encerramento do rollback

1. Tirar a aplicação da manutenção:
   ```bash
   php artisan up
   ```
2. Registrar o ocorrido (data, motivo, arquivos afetados, bancos restaurados).
3. Apenas então planejar nova tentativa de publicação.

## 4. O que NÃO fazer no rollback

- Não confiar exclusivamente em `migrate:rollback` para recuperar dados perdidos.
- Não executar migrations de rollback sem backup válido.
- Não remover dados inseridos após a publicação sem avaliar impacto.
- Não alterar DNS, SSL ou credenciais durante o rollback.

## 5. Rollback parcial (apenas código, sem banco)

Se apenas o código apresentou problemas e nenhuma migration foi executada:

1. Voltar o código para a versão anterior.
2. Limpar cache e rebuildar autoload/assets.
3. Validar e tirar da manutenção.

Este é o rollback mais seguro e rápido.

## 6. Registro do rollback

Documentar sempre:
- Data e hora do rollback.
- Motivo (erro encontrado).
- Commit/tag para o qual retornou.
- Bancos restaurados (se houver).
- Arquivos restaurados (se houver).
- Resultado da validação pós-rollback.
- Responsável pela execução.
