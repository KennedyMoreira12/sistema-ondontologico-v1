-- ============================================================================
-- Banco central da LJ SmartClinic — criação do usuário de aplicação
-- ============================================================================
--
-- Execute uma vez, como root do MySQL:
--
--     mysql -u root -p < 01-mysql.sql
--
-- TROQUE A SENHA antes de rodar. Este arquivo é versionado no git; a senha que
-- for digitada aqui não deve ser a mesma de nenhum outro ambiente.
--
-- ----------------------------------------------------------------------------
-- POR QUE UM USUÁRIO DEDICADO, E NÃO root
-- ----------------------------------------------------------------------------
--
-- O `.env` de desenvolvimento usa root porque é prático. Em produção isso
-- significa que qualquer SQL que escape do prepared statement — uma query
-- montada por concatenação, uma migration com typo — executa com privilégio
-- máximo no servidor. Com um usuário dedicado, o pior caso é o banco do app.
--
-- ----------------------------------------------------------------------------
-- POR QUE ESSE USUÁRIO PRECISA DE CREATE DATABASE
-- ----------------------------------------------------------------------------
--
-- Este SaaS dá um banco por clínica. `TenantDatabaseProvisioner::createDatabase`
-- roda `CREATE DATABASE IF NOT EXISTS clinic_000001` quando o painel cadastra
-- uma clínica nova, e `TenantProvisioner` grava em `tenant_connections` que ela
-- herda `TENANT_DB_USERNAME`/`TENANT_DB_PASSWORD` — ou seja, TODAS as clínicas
-- dividem o mesmo usuário. Ver `app/Services/Tenant/TenantProvisioner.php:127`.
--
-- Isso é uma decisão de arquitetura (banco-por-schema num MySQL só), não uma
-- falha. As alternativas seriam um MySQL por clínica ou schema por clínica com
-- um usuário por clínica; ambas custam muito mais para operar.
--
-- ----------------------------------------------------------------------------
-- POR QUE O GRANT É `clinic_%` E NÃO TUDO MAIS
-- ----------------------------------------------------------------------------
--
-- O usuário só enxerga o banco central pelo nome e os bancos das clínicas pelo
-- prefixo. Não tem nada em `mysql.*`, em `performance_schema`, nem em qualquer
-- outro banco do servidor. Ver:
--
--     mysql -N -B -e "SELECT Db FROM mysql.db WHERE User='saas'"
--
-- Isso significa que um erro de digitação não derruba o schema do MySQL.

-- --- Banco central ---------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `saas_central`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- --- Usuário da aplicação --------------------------------------------------
-- A senha abaixo é PLACEHOLDER. Troque antes de executar.
CREATE USER IF NOT EXISTS 'saas'@'127.0.0.1'
    IDENTIFIED BY 'TROQUE-ESTA-SENHA-FORTE';

-- --- Permissões ------------------------------------------------------------
--
-- GRANT, ALL PRIVILEGES: o app precisa migrar o schema central e o de cada
-- clínica, além de ler e escrever os dados dos dois.
GRANT ALL PRIVILEGES ON `saas_central`.* TO 'saas'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `clinic\_%`.* TO 'saas'@'127.0.0.1';

-- O usuário do app também precisa poder CRIAR os bancos `clinic_*`.
-- Note que este privilégio é global no MySQL 8 — não existe "CREATE DATABASE
-- só para o padrão clinic_*". Ele é o preço do banco-por-clínica, e é o
-- único privilégio que este usuário tem fora dos bancos da aplicação.
GRANT CREATE ON *.* TO 'saas'@'127.0.0.1';

-- ----------------------------------------------------------------------------
-- POR QUE NÃO TEMOS GRANT DROP GLOBAL
-- ----------------------------------------------------------------------------
--
-- Derrubar banco de clínica NÃO precisa de privilégio global. `ALL PRIVILEGES`
-- em `clinic\_%`.* já inclui o DROP nesse banco, e é esse o único lugar onde
-- o app derruba algo — o rollback de uma clínica removida. Verificar:
--
--     mysql -N -B -e "SELECT Db, Drop_priv FROM mysql.db WHERE User='saas'"
--
-- Um `GRANT DROP ON *.*` aqui daria ao usuário o direito de derrubar
-- `mysql.user` ou `mysql.db` — inclusive por um erro de digitação numa
-- concatenação de SQL, que é exatamente o caminho pelo qual uma query malformada
-- vira privilégio máximo. CREATE global já é o preço inevitável do modelo;
-- DROP global seria um segundo, e nenhum dos dois é necessário.
--
-- ----------------------------------------------------------------------------
-- O QUE ESTE USUÁRIO AINDA CONSEGUE FAZER
-- ----------------------------------------------------------------------------
--
-- `Drop_priv = Y` em `saas_central`. Ou seja: o usuário do app PODE executar
-- `DROP DATABASE saas_central`, e um `DROP DATABASE` acidental — uma migration
-- com typo, um `db:wipe` disparado no ambiente errado — leva junto TODAS as
-- clínicas, assinaturas e o mapa de conexões. Não há dado clínico nesse banco,
-- mas o cadastro inteiro do SaaS está nele.
--
-- Não dá para fechar isso, e é importante entender por quê: no MySQL o
-- privilégio DROP no nível do banco é o mesmo para `DROP TABLE` e para
-- `DROP DATABASE`. E o app PRECISA de `DROP TABLE` — é o que faz o rollback de
-- migration. Não existe grant que permita derrubar uma tabela e proíba derrubar
-- o banco que a contém.
--
-- O código já fecha o vetor mais óbvio, e isso foi verificado:
--
--   - `TenantDatabaseProvisioner::generateDatabaseName()` monta o nome a partir
--     de `tenancy.database_prefix` + o ID numérico da clínica. O nome nunca vem
--     do request.
--   - `assertSafeIdentifier()` aplica `^[A-Za-z0-9_]+$` antes de QUALQUER
--     interpolação, tanto no `CREATE DATABASE` quanto no `DROP DATABASE`.
--     Sem isso, um backtick no nome já seria injeção de SQL.
--
-- Ou seja: o caminho de código que chegaria a `DROP DATABASE` não existe com
-- entrada de usuário. O risco restante é humano (comando errado na mão), e a
-- mitigação disso é:
--
--   1. Backup testado FORA desta máquina. O dump local protege de erro de
--      comando, não de perder o servidor — ver 03-backup.sh.
--   2. Nunca rodar `migrate:fresh`, `db:wipe` ou `migrate:reset` em produção.
--      Nenhum deles tem guarda; a proteção é não executá-los.
--   3. Se um dia o risco for inaceitável: um usuário por clínica resolve, porque
--      derrubar a clínica derruba só a clínica. Custa uma grants por onboarding e
--      uma tabela de credenciais. É decisão de arquitetura, não conserto.

FLUSH PRIVILEGES;

-- ============================================================================
-- VERIFICAÇÃO
-- ============================================================================
--
--     mysql -u saas -p -e "SHOW GRANTS FOR 'saas'@'127.0.0.1';"
--
-- ============================================================================
-- BACKUP
-- ============================================================================
--
-- Um banco por clínica significa N bancos para fazer backup. O dump da central
-- sozinho não recupera nada de clínico. Instale o cron em 03-backup.sh e teste
-- a restauração ANTES do primeiro cliente — backup não verificado não é backup.
--
-- ============================================================================
-- TROCA DE SENHA DO BANCO
-- ============================================================================
--
--     ALTER USER 'saas'@'127.0.0.1' IDENTIFIED BY 'nova-senha';
--
-- Depois atualize DB_PASSWORD e TENANT_DB_PASSWORD no .env da VPS.
--
-- TENANT_DB_PASSWORD importa tanto quanto DB_PASSWORD: é a senha que o app
-- grava em `tenant_connections` e que passa a ser usada para conectar em cada
-- banco de clínica. Se as duas divergirem, o painel cadastra a clínica e a
-- primeira requisição dela falha na conexão.
