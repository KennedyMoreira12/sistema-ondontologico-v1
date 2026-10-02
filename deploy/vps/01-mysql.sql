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
-- POR QUE O GRANT É `clinic_%` E NÃO `saas_central`.* MAIS TUDO
-- ----------------------------------------------------------------------------
--
-- O usuário só enxerga o banco central pelo nome e os bancos das clínicas pelo
-- prefixo. Assim, `DROP DATABASE saas_central` não é algo que ele consiga
-- executar por engano digitando o nome errado.

-- --- Banco central ---------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `saas_central`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- --- Usuário da aplicação --------------------------------------------------
-- A senha abaixo é PLACEHOLDER. Troque antes de executar.
CREATE USER IF NOT EXISTS 'saas'@'127.0.0.1'
    IDENTIFIED BY 'TROQUE-ESTA-SENHA-FORTE';

-- --- Permissões ------------------------------------------------------------
--
-- GRANT, ALL PRIVILEGES: o app precisa criar banco (onboarding de clínica),
-- migrar o schema dela e, em um rollback, derrubar o banco. Sem isso o painel
-- quebra na hora de cadastrar o segundo cliente.
GRANT ALL PRIVILEGES ON `saas_central`.* TO 'saas'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `clinic\_%`.* TO 'saas'@'127.0.0.1';

-- O usuário do app também precisa poder CRIAR os bancos `clinic_*`.
-- Note que este privilégio é global no MySQL 8 — não existe "CREATE DATABASE
-- só para o padrão clinic_*". Ele é o preço do banco-por-clínica.
GRANT CREATE, DROP ON *.* TO 'saas'@'127.0.0.1';

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
