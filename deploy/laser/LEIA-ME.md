# Pacote de deploy — Regiões a Laser

Aplica em produção o módulo **Regiões a Laser** (commit `13c28c37`), que hoje
existe só parcialmente na VPS: `/laser/regioes` responde, mas `/laser/pacotes`
e `/laser/aplicacoes` ainda dão 404.

## O que o pacote faz

| Etapa | Ação |
|-------|------|
| 1 | Confere se todos os arquivos do pacote existem |
| 2 | Confere se a produção contém o commit base `8ec6e7fe` |
| 3 | Testa o patch dos 6 arquivos alterados — **se não aplicar limpo, nada é alterado** |
| 4 | Gera snapshot em `/var/backups/ljsmartclinic/pre-laser-<data>.tar.gz` |
| 5 | Copia os arquivos atuais para `pre-laser-<data>/` |
| 6 | Aplica o patch e copia os 13 arquivos novos |
| 7 | Roda `php artisan migrate --force` e `php artisan tenants:migrate --force` |
| 8 | Limpa/recria caches e lista as rotas `laser` |

## Conteúdo

```
laser/
├── deploy-laser.sh                  # executa na VPS, como root
├── alterados.patch                  # diff dos 6 arquivos já existentes
├── files/                           # 13 arquivos novos
│   ├── app/Http/Controllers/{Aplicacao,Pacote,Regiao}LaserController.php
│   ├── app/Models/{Aplicacao,Pacote,Regiao}Laser.php
│   ├── app/Services/Laser/AplicacaoLaserService.php
│   ├── database/migrations/tenant/2026_11_16_000002_create_laser_tables.php
│   ├── resources/views/laser/{_tabs,regioes,pacotes,historico}.blade.php
│   └── tests/Feature/LaserTest.php
└── LEIA-ME.md
```

## Como aplicar

Na sua máquina (Windows), envie o pacote:

```powershell
scp .\laser-pacote.tar.gz minha-vps:/root/
```

Na VPS, como root:

```bash
cd /root
tar xzf laser-pacote.tar.gz
cd laser
bash deploy-laser.sh
```

> **Pré-requisito:** a chave `id_ed25519` desta máquina precisa estar
> autorizada em `root@179.236.238.61`. Hoje ela é recusada
> (`Permission denied (publickey)`), por isso o envio ainda não foi feito.
> Alternativa: rodar os mesmos comandos pelo console da hospedagem.

## Verificação depois do deploy

```bash
php artisan route:list --path=laser
```

Devem aparecer as rotas `regioes_laser.index`, `regioes_laser.pacotes.*` e
`regioes_laser.aplicacoes.*`. Depois, logado como usuário da clínica:

- `/laser/regioes` → aba Regiões
- `/laser/pacotes` → aba Pacotes
- `/laser/aplicacoes` → aba Histórico

## Rollback

O script imprime os dois caminhos ao final. Para voltar ao estado anterior:

```bash
tar xzf /var/backups/ljsmartclinic/pre-laser-<data>.tar.gz -C /var/www/ljsmartclinic
cd /var/www/ljsmartclinic
php artisan optimize:clear
```

As tabelas criadas pela migration são **novas** (`laser_regioes`,
`laser_pacotes`, `laser_aplicacoes`); o rollback de código não remove dados.
Se precisar remover as tabelas, faça backup antes.

## O que já foi validado

- `php -l` em todos os 14 arquivos PHP: sem erros
- `LaserTest`: **16 testes, 39 asserções, todos passando**
- `alterados.patch` aplica limpo sobre a base `8ec6e7fe`
- Migration é aditiva (só cria tabelas)
