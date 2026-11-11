# LJ SmartClinic

## Manual Completo de Divulgação — Para Clínicas de Qualquer Ramo

---

### Sumário

1. [Apresentação](#apresentação)
2. [Quem Deve Usar](#quem-deve-usar)
3. [Diferenciais Competitivos](#diferenciais-competitivos)
4. [Módulos e Funcionalidades](#módulos-e-funcionalidades)
   - 4.1. Gestão de Pacientes
   - 4.2. Prontuário Clínico e Odontograma
   - 4.3. Agenda de Atendimentos
   - 4.4. Documentos Odontológicos (Módulo Malheiro)
   - 4.5. Planos de Tratamento e Orçamentos
   - 4.6. Financeiro
   - 4.7. Convênios e Profissionais
   - 4.8. Relatórios e Administração
   - 4.9. Comunicação e Redes Sociais (WhatsApp, Facebook e Instagram)
5. [Arquitetura e Tecnologia](#arquitetura-e-tecnologia)
6. [Multi-Tenancy e Isolamento de Dados](#multi-tenancy-e-isolamento-de-dados)
7. [Segurança e Permissões](#segurança-e-permissões)
8. [Benefícios para a Sua Clínica](#benefícios-para-a-sua-clínica)
9. [Casos de Uso](#casos-de-uso)
10. [Requisitos e Implantação](#requisitos-e-implantação)
11. [Contato](#contato)

---

## 1. Apresentação

O **LJ SmartClinic** é uma plataforma completa de gestão para clínicas de qualquer ramo, desenvolvida com foco em organização, segurança e eficiência. Ele centraliza em um único sistema todo o fluxo de trabalho de uma clínica: do cadastro do paciente ao prontuário, da agenda ao financeiro, dos planos de tratamento aos documentos profissionais.

Foi construído pensando no dia a dia real de profissionais e gestores de clínica, com módulos independentes que se comunicam entre si, eliminando a necessidade de planilhas, prontuários em papel e softwares desconectados.

O sistema é **vertical-agnóstico**: não importa se sua clínica é odontológica, de fisioterapia, pilates, depilação a laser, médica ou de qualquer outro ramo da saúde — todos os módulos estão disponíveis para qualquer clínica, e você ativa apenas os que fizer sentido para o seu negócio.

---

## 2. Quem Deve Usar

| Perfil | Como se beneficia |
|--------|-------------------|
| **Profissionais de saúde e proprietários de clínica** | Controle total da clínica, prontuário digital, documentos profissionais e ferramentas de atendimento |
| **Gestores e administradores** | Relatórios gerenciais, financeiro, controle de usuários, perfis e permissões |
| **Recepção** | Agendamento, confirmação de consultas, lista de espera, cadastro rápido de pacientes |
| **Equipe clínica** | Acesso rápido ao prontuário, anamnese, evoluções e alertas do paciente |

O sistema é ideal para clínicas de pequeno, médio e grande porte de **qualquer ramo da saúde** — odontologia, fisioterapia, pilates, depilação a laser, medicina, estética e outras — que desejam padronizar processos, reduzir erros e oferecer uma experiência mais profissional aos pacientes.

---

## 3. Diferenciais Competitivos

| Diferencial | O que significa na prática |
|-------------|---------------------------|
| **Multi-tenancy com isolamento total** | Cada clínica tem seu próprio banco de dados. Os dados de uma clínica nunca são acessíveis por outra. |
| **Documentos Odontológicos em PDF** | Papel timbrado, guia de cuidados e atestado odontológico gerados automaticamente com dados do profissional e da clínica |
| **Odontograma Digital** | Mapa dentário completo para registros clínicos e acompanhamento de tratamentos |
| **Permissões granulares por papel** | Controle exato de quem pode ver, criar, editar ou excluir cada informação |
| **Soft delete e auditoria** | Exclusões são registradas e o histórico é preservado para fins legais e de auditoria |
| **Tecnologia moderna** | Laravel 13, PHP 8.5, MySQL, Bootstrap 5, interface responsiva |
| **Qualquer ramo da saúde** | Sistema vertical-agnóstico: odontologia, fisioterapia, pilates, laser, medicina, estética e qualquer outra especialidade |

---

## 4. Módulos e Funcionalidades

### 4.1. Gestão de Pacientes

O módulo de pacientes é o coração da clínica. Nele você encontra:

- **Cadastro rápido**: Nome, CPF, RG, data de nascimento, sexo, telefone, e-mail, endereço completo e responsável
- **Ficha do paciente**: Todas as informações em uma única tela, com status (ativo/inativo)
- **Histórico completo**: Consultas, orçamentos, planos de tratamento, documentos e alertas clínicos
- **Busca e filtros**: Encontre pacientes por nome, CPF, e-mail, telefone ou status
- **Soft delete**: Pacientes excluídos não somem — são inativados e mantêm seu histórico para auditoria

> **Para quem:** Recepção, dentistas e gestores

---

### 4.2. Prontuário Clínico e Odontograma

O prontuário digital substitui papéis e planilhas com segurança e organização:

- **Evoluções**: Registre cada atendimento com data, hora, profissional e conteúdo clínico
- **Anamnese**: Questionário estruturado com respostas do paciente sobre histórico médico
- **Alertas clínicos**: Marque alertas importantes (alergias, condições médicas, restrições) que aparecem na ficha do paciente
- **Prescrições**: Controle de medicamentos e orientações prescritas
- **Odontograma**: Mapa dentário interativo para registro de dentes, condições e procedimentos (disponível para clínicas odontológicas)

> **Para quem:** Profissionais de saúde e equipe clínica

---

### 4.3. Agenda de Atendimentos

Gerencie o fluxo de pacientes da clínica com eficiência:

- **Visualização de horários**: Veja a agenda por dia, semana ou mês
- **Confirmação de consultas**: Marque atendimentos como confirmados ou não comparecidos
- **Lista de espera**: Gerencie pacientes aguardando vagas ou cancelamentos
- **Integração com pacientes**: Vincule cada atendimento ao prontuário do paciente automaticamente

> **Para quem:** Recepção e dentistas

---

### 4.4. Documentos Profissionais em PDF

O sistema gera documentos profissionais em PDF, prontos para imprimir ou enviar ao paciente. Exemplo de documentos disponíveis:

| Documento | O que inclui |
|-----------|--------------|
| **Papel Timbrado** | Timbrado personalizado com dados da clínica (nome, frase institucional, cores, logo) |
| **Guia de Cuidados** | Orientações pós-procedimento com itens numerados e dados do profissional |
| **Atestado** | Atestado completo com dados do paciente, profissional (nome, registro do conselho, especialidades), data, horas, dias de repouso e local |

**Como funciona:**
1. O usuário acessa o módulo de Documentos
2. Seleciona o tipo de documento (papel timbrado, guia de cuidados ou atestado)
3. Para o atestado, preenche paciente, profissional, data do atendimento, dias de repouso e local
4. O sistema gera o PDF instantaneamente com todos os dados da clínica e do profissional

> **Disponibilidade:** Este módulo está disponível para a Clínica Malheiro (clínica odontológica). Clínicas de outras especialidades (fisioterapia, pilates, laser) não têm acesso a estes documentos específicos.

---

### 4.5. Planos de Tratamento e Orçamentos

Planeje e apresente tratamentos com transparência:

- **Planos de tratamento**: Crie planos com múltiplos itens (procedimentos, sessões, valores)
- **Orçamentos**: Gere orçamentos detalhados para os pacientes
- **Status de plano**: Acompanhe se o plano está ativo, concluído ou cancelado
- **Vinculação a pacientes**: Cada plano e orçamento fica registrado na ficha do paciente

> **Para quem:** Dentistas e gestores

---

### 4.6. Financeiro

Controle financeiro da clínica em um só lugar:

- **Contas a receber**: Lançamento e acompanhamento de valores a receber de pacientes e convênios
- **Parcelamentos**: Controle de parcelas de tratamentos e orçamentos
- **Fluxo de caixa**: Visão dos recebimentos e pagamentos da clínica
- **Relatórios financeiros**: Demonstros de faturamento e inadimplência

> **Para quem:** Gestores, financeiro e recepção

---

### 4.7. Convênios e Profissionais

Gerencie a parte operacional da clínica:

- **Convênios**: Cadastre convênios odontológicos com seus dados, tabelas e carências
- **Profissionais**: Cadastre dentistas e equipe com CRO, especialidades, tipo (dentista, auxiliar, etc.) e comissão
- **Especialidades**: Defina as especialidades atendidas pela clínica (implantes, dentística, ortodontia, etc.)

> **Para quem:** Gestores e administradores

---

### 4.8. Relatórios e Administração

Controle total sobre a operação da clínica:

- **Relatórios gerenciais**: Faturamento, atendimentos, estoque e outros indicadores
- **Gestão de usuários**: Cadastre e gerencie quem acessa o sistema
- **Perfis e permissões**: Defina quais módulos e ações cada papel (admin, dentista, recepção, financeiro, estoque, auxiliar, fisioterapeuta, instrutor de pilates, operador de laser) pode executar
- **Dados da clínica**: Cadastre nome, razão social, CNPJ, endereço, telefone, e-mail, cores da marca e logo
- **Auditoria**: Log de todas as ações realizadas no sistema (quem, quando, o quê)

> **Para quem:** Gestores, administradores e proprietários

---

### 4.9. Comunicação e Redes Sociais (WhatsApp, Facebook e Instagram)

O LJ SmartClinic integra sua clínica aos principais canais de comunicação e redes sociais, permitindo que você fique perto do paciente onde ele já está:

| Canal | O que você pode fazer |
|-------|----------------------|
| **WhatsApp** | Integração com Evolution API para envio e recebimento de mensagens automáticas. Confirmação de consultas, lembretes de atendimento, envio de atestados e guias de cuidados diretamente pelo WhatsApp do paciente. Webhook com n8n para automação de fluxos (ex.: paciente confirma consulta → sistema atualiza automaticamente). |
| **Facebook** | Gestão de páginas e postagens direto do sistema. Publique conteúdos da clínica, promovendo atendimentos, novos serviços e conteúdos educativos de saúde bucal. |
| **Instagram** | Publique fotos, vídeos e stories da clínica, do dia a dia e de cases de sucesso (com autorização do paciente). Mantenha o engajamento com seguidores e atraia novos pacientes pelo visual. |

**Benefícios da integração com redes sociais:**

- **Confirmação automática de consultas**: O paciente recebe uma mensagem pelo WhatsApp e confirma com um clique, reduzindo faltas e otimizando a agenda
- **Envio de documentos por WhatsApp**: O atestado odontológico, o guia de cuidados e o papel timbrado podem ser enviados direto para o celular do paciente
- **Presença digital fortalecida**: Sua clínica fica visível no Facebook e Instagram, canais onde pacientes buscam profissionais de saúde
- **Automação com n8n**: Fluxos de comunicação personalizáveis (ex.: "7 dias antes da consulta → enviar lembrete no WhatsApp")

> **Para quem:** Recepção, dentistas, gestores e equipe de marketing da clínica

---

## 5. Arquitetura e Tecnologia

O LJ SmartClinic é construído com tecnologias modernas e robustas:

| Camada | Tecnologia |
|--------|------------|
| **Backend** | Laravel 13.33.0 (PHP 8.5) |
| **Banco de Dados** | MySQL / MariaDB |
| **Frontend** | Bootstrap 5, Sass, Vite, Bootstrap Icons |
| **Geração de PDF** | barryvdh/laravel-dompdf + Dompdf v3.1.6 |
| **Testes** | PHPUnit |
| **Servidor** | Ubuntu 26.04, Nginx, PHP-FPM |

### Estrutura de Banco

O sistema utiliza **multi-tenancy com banco por clínica**:

- **Banco central (`saas_central`)**: Cadastro de clínicas, usuários, planos, assinaturas, conexões de tenant, branding e configurações globais
- **Banco por clínica (`clinic_000001`, `clinic_000002`, etc.)**: Pacientes, prontuário, agenda, financeiro, profissionais e todos os dados clínicos

Isso garante que cada clínica tenha seus dados isolados e seguros, sem risco de vazamento entre clínicas.

---

## 6. Multi-Tenancy e Isolamento de Dados

### Como funciona

Cada clínica é um "tenant" (inquilino) independente no sistema:

1. Quando um usuário faz login, o sistema identifica a clínica a que ele pertence
2. O middleware `ResolveTenant` conecta a aplicação ao banco de dados específico daquela clínica
3. Todas as consultas são feitas apenas no banco da clínica atual
4. O `VerticalManager` controla quais módulos a clínica pode acessar com base em sua especialidade (odontologia, fisioterapia, pilates, depilação a laser)

### Benefícios do isolamento

- **Segurança**: Dados da Clínica A nunca ficam acessíveis para a Clínica B
- **Performance**: Cada clínica consulta apenas seu próprio banco, mais leve e rápido
- **Backup independente**: É possível fazer backup de uma clínica sem afetar as outras
- **Customização**: Cada clínica pode ter suas próprias cores, logo, frase institucional e módulos ativados/desativados

---

## 7. Segurança e Permissões

O sistema utiliza controle de acesso baseado em papéis (RBAC):

### Papéis disponíveis

| Papel | Principais permissões |
|-------|----------------------|
| **Admin** | Todas as permissões de todos os módulos |
| **Dentista** | Pacientes (ver/atualizar), prontuário, anamnese, alertas, odontograma, documentos, tratamentos, orçamentos, financeiro (ver), contratos |
| **Recepção** | Pacientes (tudo), prontuário (ver), agenda (tudo), convênios, orçamentos (ver), financeiro (ver/receber), estoque (ver), documentos (ver/criar) |
| **Financeiro** | Pacientes (ver), agenda (ver), tratamentos (ver), orçamentos (tudo), financeiro (tudo), documentos (ver) |
| **Estoque** | Estoque (tudo), pacientes (ver), agenda (ver), financeiro (ver), relatórios (ver) |
| **Auxiliar** | Pacientes (ver), agenda (ver), prontuário (ver) |
| **Fisioterapeuta** | Pacientes (ver/criar/atualizar), avaliação, evolução, agenda, procedimentos (ver), pacotes (ver) |
| **Instrutor de Pilates** | Pacientes (ver/criar/atualizar), prontuário (ver/criar), agenda, turmas de pilates (tudo) |
| **Operador de Laser** | Pacientes (ver/criar/atualizar), prontuário (ver/criar), agenda, regiões de laser (tudo) |

### Proteções adicionais

- **Soft delete**: Exclusões são registradas e reversíveis (dados nunca são perdidos acidentalmente)
- **Auditoria**: Todas as ações de criar, atualizar e excluir são registradas com usuário, data e horário
- **Isolamento por conexão**: O banco de cada clínica só é acessível durante a requisição do usuário autenticado dessa clínica
- **Validação de pertencimento**: Documentos e prontuários só podem ser acessados se pertencerem ao paciente da clínica atual

---

## 8. Benefícios para a Sua Clínica

### Para o dentista
- Prontuário sempre disponível, em qualquer computador da clínica
- Odontograma digital que não se perde nem rasga
- Atestados e documentos odontológicos gerados em segundos
- Histórico completo do paciente em um clique

### Para o gestor
- Relatórios de faturamento e inadimplência em tempo real
- Controle de quem acessa o sistema e o que cada pessoa pode fazer
- Backup automático dos dados de cada clínica
- Redução de erros operacionais e retrabalho

### Para a recepção
- Agendamento rápido e sem conflitos de horário
- Lista de espera organizada
- Confirmação de consultas simplificada
- Cadastro de pacientes em menos de 2 minutos

### Para o paciente
- Atendimento mais ágil e organizado
- Documentos profissionais (atestado, guia de cuidados) entregues rapidamente
- Histórico clínico completo e seguro

---

## 9. Casos de Uso

### Caso 1: Primeiro atendimento de um novo paciente
1. Recepção cadastra o paciente no sistema (nome, CPF, telefone, endereço)
2. Dentista acessa a ficha do paciente e preenche a anamnese
3. Durante a consulta, registra a evolução no prontuário e marca dentes no odontograma
4. Ao final, emite um atestado odontológico em PDF para o paciente entregar no trabalho/escola
5. Gera um orçamento do tratamento planejado

### Caso 2: Controle financeiro mensal
1. Gestor acessa o módulo Financeiro
2. Visualiza todas as contas a receber do mês
3. Verifica quais pacientes têm parcelas pendentes de planos de tratamento
4. Emite relatório de faturamento para a contabilidade
5. Acompanha o fluxo de caixa da clínica

### Caso 3: Acompanhamento de tratamento de implante
1. Dentista cria um plano de tratamento com múltiplos itens (consulta, cirurgia, prótese, retornos)
2. Vincula o plano ao paciente
3. A cada sessão, registra a evolução no prontuário
4. Marca as consultas de retorno na agenda
5. Acompanha o status do plano (ativo, em andamento, concluído)

---

## 10. Requisitos e Implantação

### Requisitos técnicos (servidor)

| Item | Requisito |
|------|-----------|
| **Sistema Operacional** | Ubuntu 22.04+ ou 26.04 |
| **PHP** | 8.3+ (com extensão pdo_mysql) |
| **Banco de Dados** | MySQL 8.0+ ou MariaDB 10.6+ |
| **Composer** | 2.10+ |
| **Node.js / npm** | Para build de assets (Sass, Vite) |
| **Servidor Web** | Nginx ou Apache com PHP-FPM |
| **Memória RAM** | Mínimo 2 GB (recomendado 4 GB) |
| **Armazenamento** | Mínimo 20 GB SSD |

### Processo de implantação

1. **Provisionamento do servidor**: Instalação do PHP, MySQL, Composer e Nginx
2. **Criação do banco central**: Tabelas de clínicas, usuários, planos e conexões de tenant
3. **Deploy do código**: Upload dos arquivos do sistema e `composer install`
4. **Configuração do `.env`**: Credenciais do banco, APP_URL, APP_KEY e SMTP
5. **Migrações e seeders**: `php artisan migrate`, `php artisan db:seed`, `php artisan tenants:seed`
6. **Build do frontend**: `npm ci && npm run build`
7. **Cache de produção**: `config:cache`, `route:cache`, `view:cache`
8. **Certificado SSL**: Certbot para HTTPS

### Backup e recuperação

O sistema recomenda backup diário de:
- Banco central (`saas_central`)
- Banco de cada clínica ativa (`clinic_000001`, `clinic_000002`, etc.)
- Arquivos enviados (logo, documentos, anexos)

---

## 11. Contato

Para saber mais sobre o LJ SmartClinic, solicitar uma demonstração ou iniciar a implantação na sua clínica:

- **Sistema**: LJ SmartClinic
- **Versão**: Laravel 13.33.0
- **Domínio de produção**: https://xn--ljaltomao-s2a7b.com.br
- **E-mail**: contato@lj smartclinic.com.br
- **WhatsApp**: (16) 99999-9999

---

### Sobre este manual

Este documento foi elaborado com base na versão de produção do LJ SmartClinic, branch `release/daiane-vps-20261107`. Funcionalidades e interfaces podem sofrer atualizações. Consulte sempre a versão mais recente do sistema e da documentação.

---

**LJ SmartClinic — Clínica organizada, atendimento em dia.**
