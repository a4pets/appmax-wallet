# Executando Testes com Coverage

Este projeto está configurado para executar testes com cobertura de código usando Xdebug no Docker.

## 📋 Pré-requisitos

- Docker
- Docker Compose

## 🚀 Executando Testes

### Primeira execução (com build):

```bash
./test-coverage.sh
```

Este script irá:
1. Construir a imagem Docker com Xdebug
2. Iniciar o container de testes
3. Executar os testes com coverage
4. Gerar relatório HTML em `./coverage/index.html`

### Execuções subsequentes (sem rebuild):

```bash
./test-run.sh
```

Este script apenas executa os testes sem reconstruir a imagem, economizando tempo.

## 📊 Visualizando o Relatório

Após executar os testes, abra o relatório HTML:

```bash
open coverage/index.html
```

Ou navegue até a pasta `coverage/` e abra o arquivo `index.html` no seu navegador.

## 🐳 Comandos Docker Manuais

### Construir e iniciar container:

```bash
docker-compose -f docker-compose.test.yml up -d --build
```

### Executar testes com coverage HTML:

```bash
docker-compose -f docker-compose.test.yml exec app-test php artisan test --coverage-html coverage
```

### Executar testes com coverage no terminal:

```bash
docker-compose -f docker-compose.test.yml exec app-test php artisan test --coverage
```

### Executar testes com cobertura mínima:

```bash
docker-compose -f docker-compose.test.yml exec app-test php artisan test --coverage --min=80
```

### Parar o container:

```bash
docker-compose -f docker-compose.test.yml down
```

### Ver logs do container:

```bash
docker-compose -f docker-compose.test.yml logs -f
```

## 📁 Estrutura de Arquivos

- `Dockerfile.test` - Dockerfile com Xdebug configurado
- `docker-compose.test.yml` - Configuração Docker Compose para testes
- `test-coverage.sh` - Script para primeira execução (com build)
- `test-run.sh` - Script para execuções rápidas (sem build)
- `coverage/` - Pasta onde são gerados os relatórios (ignorada pelo git)

## 🔧 Configuração do Xdebug

O Xdebug está configurado especificamente para coverage com as seguintes opções:

```ini
xdebug.mode=coverage
xdebug.start_with_request=yes
```

## ✅ Status Atual dos Testes

```
Tests:    77 passed (295 assertions)
Duration: ~3s
```

**100% dos testes passando! 🎉**

## 🎯 Testes Incluídos

- **AuthenticationTest** (12 testes) - Autenticação JWT
- **ExceptionHandlingTest** (14 testes) - Tratamento de exceções
- **TransactionDetailTest** (7 testes) - Detalhes de transações
- **WalletBalanceTest** (6 testes) - Consulta de saldo
- **WalletDepositTest** (10 testes) - Operações de depósito
- **WalletTransferTest** (14 testes) - Transferências entre contas
- **WalletWithdrawTest** (12 testes) - Operações de saque

## 💡 Dicas

- O relatório HTML é mais detalhado e mostra linha por linha o que foi testado
- Use `--min=80` para garantir um mínimo de 80% de cobertura
- A pasta `coverage/` é ignorada pelo git, não será commitada
- O container fica rodando após os testes, use `docker-compose down` quando terminar
