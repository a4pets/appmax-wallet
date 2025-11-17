# Digital Wallet API

API REST para gerenciamento de carteira digital com autenticação JWT, operações bancárias (depósito, saque, transferência), controle de limites diários e sistema de estorno/contestação.

**Stack:** Laravel 11 + PHP 8.3 + SQLite + JWT Auth

---

## 🚀 Quick Start

### 1. Executar com Docker (Recomendado)

```bash
# Clone o repositório
git clone <repo-url>
cd digital-wallet-api

# Copie o .env
cp .env.example .env

# Inicie os containers
docker compose up -d

# Acesse a aplicação
curl http://localhost:8000/api/health
```

### 2. Executar Localmente

```bash
# Instale as dependências
composer install

# Configure o ambiente
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Execute as migrations
touch database/database.sqlite
php artisan migrate --seed

# Inicie o servidor
php artisan serve
```

---

## 📦 Exemplos Práticos

A pasta `examples/` contém recursos úteis para começar rapidamente:

### Script de Teste Automático
```bash
# Testar toda a API com um único comando
./examples/api-usage.sh
```

### Postman Collection
```bash
# Importar no Postman para testar manualmente
examples/Digital-Wallet-API.postman_collection.json
```

### Queries SQLite Prontas
```bash
# Consultas úteis para explorar o banco
sqlite3 database/database.sqlite < examples/sqlite-queries.sql
```

**📚 Veja mais detalhes:** [examples/README.md](examples/README.md)

---

## 🧪 Testes e Cobertura

### Executar Testes com Docker (Xdebug)

```bash
# Build e execução completa (primeira vez)
./test-coverage.sh

# Executar testes (sem rebuild)
./test-run.sh

# Visualizar relatório de cobertura
open coverage/index.html
```

### Executar Testes Localmente

```bash
# Testes simples
php artisan test

# Com cobertura (requer Xdebug ou PCOV)
php artisan test --coverage-html coverage

# Teste específico
php artisan test --filter test_successful_deposit
```

**Status Atual:** ✅ 77/77 testes passando (295 assertions)

---

## 📡 Endpoints da API

Base URL: `http://localhost:8000/api`

### Autenticação

#### Registrar Usuário
```bash
POST /api/auth/register

# Request
{
  "data": {
    "name": "João Silva",
    "email": "joao@example.com",
    "password": "senha123",
    "password_confirmation": "senha123"
  }
}

# Response 201
{
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com"
    },
    "account_number": "DW12345678",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer"
  }
}
```

#### Login
```bash
POST /api/auth/login

# Request
{
  "data": {
    "email": "joao@example.com",
    "password": "senha123"
  }
}

# Response 200
{
  "data": {
    "user": { ... },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer"
  }
}
```

#### Logout
```bash
POST /api/auth/logout
Authorization: Bearer {token}

# Response 200
{
  "data": {
    "message": "Logout realizado com sucesso"
  }
}
```

#### Refresh Token
```bash
POST /api/auth/refresh
Authorization: Bearer {token}

# Response 200
{
  "data": {
    "token": "new_token_here",
    "token_type": "bearer"
  }
}
```

#### Meus Dados
```bash
GET /api/auth/me
Authorization: Bearer {token}

# Response 200
{
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "account": {
      "agency": "0001",
      "account": "123456789",
      "account_digit": "7",
      "account_number": "DW12345678",
      "account_type": "digital_wallet",
      "status": "active",
      "balance": 1000.00
    }
  }
}
```

---

### Carteira

#### Consultar Saldo
```bash
GET /api/wallet/balance
Authorization: Bearer {token}

# Response 200
{
  "data": {
    "account_number": "DW12345678",
    "balance": 1000.00,
    "account_type": "digital_wallet",
    "status": "active",
    "daily_limit": 5000.00,
    "daily_used": 0.00,
    "daily_available": 5000.00
  }
}
```

#### Depositar
```bash
POST /api/wallet/deposit
Authorization: Bearer {token}

# Request
{
  "data": {
    "amount": 500.00,
    "description": "Depósito via PIX"
  }
}

# Response 201
{
  "data": {
    "transaction": {
      "id": 1,
      "transaction_id": "DEP-20250117143052-...",
      "amount": 500.00,
      "transaction_type": "deposit",
      "flow": "C",
      "balance_before": 1000.00,
      "balance_after": 1500.00,
      "description": "Depósito via PIX",
      "created_at": "2025-01-17T14:30:52.000000Z"
    },
    "new_balance": 1500.00
  }
}
```

**Limite Diário:** R$ 10.000,00

#### Sacar
```bash
POST /api/wallet/withdraw
Authorization: Bearer {token}

# Request
{
  "data": {
    "amount": 200.00,
    "description": "Saque em caixa eletrônico"
  }
}

# Response 201
{
  "data": {
    "transaction": {
      "id": 2,
      "transaction_id": "WIT-20250117143152-...",
      "amount": 200.00,
      "transaction_type": "withdraw",
      "flow": "D",
      "balance_before": 1500.00,
      "balance_after": 1300.00,
      "description": "Saque em caixa eletrônico",
      "created_at": "2025-01-17T14:31:52.000000Z"
    },
    "new_balance": 1300.00
  }
}
```

**Limite Diário:** R$ 5.000,00

#### Transferir
```bash
POST /api/wallet/transfer
Authorization: Bearer {token}

# Request
{
  "data": {
    "receiver_account_number": "DW87654321",
    "amount": 100.00,
    "description": "Pagamento jantar"
  }
}

# Response 200
{
  "data": {
    "transfer": {
      "id": 1,
      "from_account_number": "DW12345678",
      "receiver_account_number": "DW87654321",
      "amount": 100.00,
      "status": "completed",
      "transaction_id": "TRF-20250117143252-..."
    },
    "transaction": {
      "id": 3,
      "transaction_type": "transfer_sent",
      "flow": "D",
      "amount": 100.00,
      "balance_before": 1300.00,
      "balance_after": 1200.00
    },
    "new_balance": 1200.00
  }
}
```

**Limite Diário:** R$ 5.000,00

#### Estornar Transação (Chargeback)
```bash
POST /api/wallet/chargeback
Authorization: Bearer {token}

# Request
{
  "data": {
    "transaction_id": 1,
    "reason": "Transação duplicada"
  }
}

# Response 201
{
  "data": {
    "chargeback": {
      "id": 4,
      "transaction_type": "chargeback",
      "flow": "E",
      "amount": 500.00,
      "description": "Estorno: Transação duplicada"
    },
    "original_transaction": { ... },
    "new_balance": 700.00
  }
}
```

#### Contestar Transação
```bash
POST /api/wallet/contestar
Authorization: Bearer {token}

# Request
{
  "data": {
    "transaction_id": 2,
    "motivo": "Não reconheço esta transação"
  }
}

# Response 201
{
  "message": "Contestação processada com sucesso",
  "data": {
    "estorno": { ... },
    "transacao_original": { ... },
    "novo_saldo": 900.00,
    "status_contestacao": {
      "contestada": true,
      "contestada_em": "2025-01-17T14:35:00.000000Z",
      "motivo": "Não reconheço esta transação"
    }
  }
}
```

#### Extrato (Statement)
```bash
GET /api/wallet/statement?start_date=2025-01-01&end_date=2025-01-31&per_page=15
Authorization: Bearer {token}

# Response 200
{
  "data": [
    {
      "date": "2025-01-17",
      "opening_balance": 1000.00,
      "closing_balance": 1200.00,
      "total_credits": 500.00,
      "total_debits": 300.00,
      "transaction_count": 5,
      "transactions": [ ... ]
    }
  ],
  "summary": {
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31"
    },
    "opening_balance": 1000.00,
    "closing_balance": 1200.00,
    "total_credits": 1500.00,
    "total_debits": 1300.00,
    "net_change": 200.00,
    "total_days": 15,
    "total_transactions": 47
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 15,
    "last_page": 1
  }
}
```

**Parâmetros:**
- `start_date` (obrigatório): Data inicial (YYYY-MM-DD)
- `end_date` (obrigatório): Data final (máximo 90 dias)
- `transaction_type` (opcional): Filtrar por tipo
- `per_page` (opcional): Itens por página (1-100, padrão: 15)
- `page` (opcional): Número da página

#### Detalhes da Transação
```bash
GET /api/wallet/transaction/1
Authorization: Bearer {token}

# Response 200
{
  "data": {
    "id": 1,
    "transaction_id": "DEP-20250117143052-...",
    "amount": 500.00,
    "transaction_type": "deposit",
    "flow": "C",
    "description": "Depósito via PIX",
    "balance_before": 1000.00,
    "balance_after": 1500.00,
    "metadata": {},
    "created_at": "2025-01-17T14:30:52.000000Z"
  }
}
```

---

## 🗄️ Estrutura do Banco de Dados

### Principais Tabelas

#### `users`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID do usuário |
| name | varchar | Nome completo |
| email | varchar | Email (único) |
| password | varchar | Senha (hash) |
| created_at | timestamp | Data de criação |

#### `accounts`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID da conta |
| user_id | bigint | FK para users |
| agency | varchar | Agência (4 dígitos) |
| account | varchar | Número da conta (9 dígitos) |
| account_digit | varchar | Dígito verificador |
| account_number | varchar | Número único (DW + 8 dígitos) |
| account_type | enum | checking, savings, digital_wallet |
| status | enum | active, inactive, blocked |
| created_at | timestamp | Data de criação |

#### `balances`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID do saldo |
| account_id | bigint | FK para accounts |
| amount | decimal(15,2) | Saldo atual |
| updated_at | timestamp | Última atualização |

#### `transactions`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID da transação |
| account_id | bigint | FK para accounts |
| transaction_type_id | bigint | FK para transaction_types |
| transaction_id | varchar | ID único da transação |
| flow | char(1) | C=Crédito, D=Débito, E=Estorno |
| amount | decimal(15,2) | Valor |
| balance_before | decimal(15,2) | Saldo antes |
| balance_after | decimal(15,2) | Saldo depois |
| description | text | Descrição |
| metadata | json | Metadados adicionais |
| is_chargebacked | boolean | Foi estornada? |
| is_contested | boolean | Foi contestada? |
| contested_at | timestamp | Data da contestação |
| contested_reason | text | Motivo da contestação |
| chargeback_of_transaction_id | bigint | ID da transação original (se for estorno) |
| created_at | timestamp | Data de criação |

#### `transaction_types`
| Código | Nome | Descrição |
|--------|------|-----------|
| DEPOSIT | Depósito | Entrada de dinheiro |
| WITHDRAW | Saque | Saída de dinheiro |
| TRANSFER_SENT | Transferência Enviada | Débito por transferência |
| TRANSFER_RECEIVED | Transferência Recebida | Crédito por transferência |
| CHARGEBACK | Estorno | Reversão de transação |

#### `transfers`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID da transferência |
| sender_account_id | bigint | FK para accounts (origem) |
| receiver_account_id | bigint | FK para accounts (destino) |
| amount | decimal(15,2) | Valor |
| description | text | Descrição |
| status | enum | pending, completed, failed |
| transaction_id | varchar | ID único da transação |
| created_at | timestamp | Data de criação |

#### `daily_limits`
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | ID do limite |
| account_id | bigint | FK para accounts |
| limit_type | enum | deposit, withdraw, transfer |
| daily_limit | decimal(15,2) | Limite diário |
| current_used | decimal(15,2) | Valor usado hoje |
| reset_at | date | Data de reset |

**Limites Padrão:**
- Depósito: R$ 10.000,00/dia
- Saque: R$ 5.000,00/dia
- Transferência: R$ 5.000,00/dia

---

## 🔍 Consultando o Banco SQLite

### Via CLI

```bash
# Abrir o banco
sqlite3 database/database.sqlite

# Listar tabelas
.tables

# Ver estrutura de uma tabela
.schema users

# Consultar dados
SELECT * FROM users;
SELECT * FROM accounts WHERE user_id = 1;
SELECT * FROM transactions WHERE account_id = 1 ORDER BY created_at DESC;

# Ver saldo de uma conta
SELECT a.account_number, b.amount as balance
FROM accounts a
JOIN balances b ON a.id = b.account_id
WHERE a.user_id = 1;

# Ver extrato com tipos de transação
SELECT
  t.id,
  t.transaction_id,
  tt.name as type,
  t.flow,
  t.amount,
  t.balance_after,
  t.description,
  t.created_at
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE t.account_id = 1
ORDER BY t.created_at DESC
LIMIT 10;

# Sair
.quit
```

### Via GUI (DB Browser for SQLite)

1. Baixe: https://sqlitebrowser.org/
2. Abra o arquivo: `database/database.sqlite`
3. Navegue pelas tabelas visualmente

### Via Docker

```bash
# Entrar no container
docker compose exec app sh

# Abrir o banco
sqlite3 database/database.sqlite

# Ou executar query direto
docker compose exec app sqlite3 database/database.sqlite "SELECT * FROM users;"
```

---

## 🔐 Autenticação JWT

### Headers Obrigatórios

Todas as rotas protegidas requerem:

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Tempo de Expiração

- Token expira em **60 minutos**
- Use `/api/auth/refresh` para renovar

### Exemplo com cURL

```bash
# Salvar token
TOKEN="seu_token_aqui"

# Fazer requisição
curl -X GET "http://localhost:8000/api/wallet/balance" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

### Exemplo com Postman

1. Aba **Authorization**
2. Type: **Bearer Token**
3. Token: `{seu_token}`

---

## 📊 Códigos de Erro

### Validação (422)
```json
{
  "message": "The data.amount field is required.",
  "errors": {
    "data.amount": ["The data.amount field is required."]
  }
}
```

### Não Autorizado (401)
```json
{
  "data": {
    "error": "Credenciais inválidas",
    "code": "INVALID_CREDENTIALS"
  }
}
```

### Saldo Insuficiente (422)
```json
{
  "data": {
    "error": "Saldo insuficiente para realizar operação",
    "code": "INSUFFICIENT_BALANCE",
    "details": {
      "available_balance": 100.00,
      "requested_amount": 200.00
    }
  }
}
```

### Limite Diário Excedido (422)
```json
{
  "data": {
    "error": "Limite diário excedido para withdraw",
    "code": "DAILY_LIMIT_EXCEEDED",
    "details": {
      "limit_type": "withdraw",
      "daily_limit": 5000.00,
      "current_used": 4900.00,
      "requested_amount": 200.00
    }
  }
}
```

### Conta Inválida (404)
```json
{
  "data": {
    "error": "Conta não encontrada ou inativa",
    "code": "INVALID_ACCOUNT"
  }
}
```

---

## 🐳 Docker

### Arquivos

- `docker-compose.yml` - Container de desenvolvimento
- `docker-compose.test.yml` - Container de testes (com Xdebug)
- `Dockerfile.test` - Imagem de testes

### Comandos Úteis

```bash
# Ver logs
docker compose logs -f

# Entrar no container
docker compose exec app sh

# Rodar migrations
docker compose exec app php artisan migrate

# Limpar cache
docker compose exec app php artisan cache:clear

# Parar containers
docker compose down

# Rebuild
docker compose up -d --build
```

---

## 📚 Documentação Swagger

A API possui documentação OpenAPI 3.0 integrada.

```bash
# Gerar documentação
php artisan l5-swagger:generate

# Acessar
http://localhost:8000/api/documentation
```

---

## 🛠️ Desenvolvimento

### Estrutura de Pastas

```
digital-wallet-api/
├── app/
│   ├── Exceptions/          # Exceções customizadas
│   ├── Http/
│   │   ├── Controllers/     # Controllers da API
│   │   ├── Middleware/      # Middlewares
│   │   ├── Requests/        # Form Requests (validação)
│   │   └── Resources/       # API Resources (serialização)
│   ├── Models/              # Models Eloquent
│   └── Services/            # Lógica de negócio
├── database/
│   ├── migrations/          # Migrations
│   ├── seeders/             # Seeders
│   └── factories/           # Factories para testes
├── routes/
│   └── api.php             # Rotas da API
├── tests/
│   ├── Feature/            # Testes de integração
│   └── Unit/               # Testes unitários
└── storage/
    └── api-docs/           # Swagger JSON
```

### Adicionar Nova Feature

```bash
# Criar migration
php artisan make:migration create_example_table

# Criar model
php artisan make:model Example -m

# Criar controller
php artisan make:controller Api/ExampleController

# Criar request
php artisan make:request ExampleRequest

# Criar resource
php artisan make:resource ExampleResource

# Criar teste
php artisan make:test ExampleTest
```

### Code Style

```bash
# Instalar Pint
composer require laravel/pint --dev

# Formatar código
./vendor/bin/pint
```

---

## 📝 Variáveis de Ambiente

```env
# Aplicação
APP_NAME="Digital Wallet API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de Dados
DB_CONNECTION=sqlite
# Para MySQL/PostgreSQL, descomente e configure:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=digital_wallet
# DB_USERNAME=root
# DB_PASSWORD=

# JWT
JWT_SECRET=sua_chave_secreta_aqui
JWT_TTL=60
JWT_REFRESH_TTL=20160

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## 🔄 Fluxo de Transações

### Depósito
1. Validar valor (> 0)
2. Verificar limite diário
3. Lock na conta
4. Atualizar saldo
5. Criar transação (flow: C)
6. Atualizar limite usado
7. Commit

### Saque
1. Validar valor (> 0)
2. Verificar saldo suficiente
3. Verificar limite diário
4. Lock na conta
5. Atualizar saldo
6. Criar transação (flow: D)
7. Atualizar limite usado
8. Commit

### Transferência
1. Validar contas (origem ≠ destino)
2. Verificar saldo suficiente
3. Verificar limite diário
4. Lock nas duas contas
5. Debitar conta origem
6. Creditar conta destino
7. Criar 2 transações (D + C)
8. Criar registro de transferência
9. Atualizar limite usado
10. Commit

### Estorno/Contestação
1. Buscar transação original
2. Validar (não pode ser estorno, não pode estar já estornada)
3. Lock na conta
4. Reverter saldo
5. Criar transação de estorno (flow: E)
6. Marcar original como estornada
7. Commit

---

## 🤝 Contribuindo

```bash
# Clone e crie branch
git checkout -b feature/nova-funcionalidade

# Faça suas alterações
# Adicione testes

# Execute os testes
php artisan test

# Verifique cobertura
./test-coverage.sh

# Commit e push
git commit -m "feat: adiciona nova funcionalidade"
git push origin feature/nova-funcionalidade
```

---

## 📞 Suporte

- **Documentação de Testes:** [README-TESTS.md](docs/README-TESTS.md)
- **Quick Start Docker:** [QUICKSTART-DOCKER-TESTS.md](docs/QUICKSTART-DOCKER-TESTS.md)
- **Documentação Detalhada:** [TESTING.md](docs/TESTING.md)

---

## 📄 Licença

Este projeto está sob a licença MIT.

---

**Última atualização:** 17/11/2025
**Versão:** 1.0.0
**PHP:** 8.3+
**Laravel:** 11
**Banco:** SQLite / MySQL / PostgreSQL
