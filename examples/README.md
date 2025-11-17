# Exemplos e Recursos de Desenvolvimento

Esta pasta contém recursos úteis para facilitar o desenvolvimento e uso da Digital Wallet API.

---

## 📁 Arquivos Disponíveis

### 1. Script de Teste da API (`api-usage.sh`)

Script bash que demonstra o uso completo da API com exemplos práticos.

**Como usar:**

```bash
# Tornar executável (já está)
chmod +x examples/api-usage.sh

# Executar
./examples/api-usage.sh
```

**O que ele faz:**
- ✅ Registra um novo usuário
- ✅ Consulta saldo inicial
- ✅ Faz depósitos
- ✅ Realiza saque
- ✅ Cria segundo usuário
- ✅ Faz transferência entre contas
- ✅ Consulta extrato
- ✅ Mostra dados completos do usuário

**Requisitos:**
- `curl` instalado
- `jq` instalado (para formatação JSON)
- API rodando em `http://localhost:8000`

**Instalar jq:**
```bash
# macOS
brew install jq

# Ubuntu/Debian
sudo apt-get install jq

# Windows (via Chocolatey)
choco install jq
```

---

### 2. Postman Collection (`Digital-Wallet-API.postman_collection.json`)

Collection completa para importar no Postman com todas as rotas da API.

**Como usar:**

1. Abra o Postman
2. Clique em **Import**
3. Selecione o arquivo `Digital-Wallet-API.postman_collection.json`
4. A collection será importada com todas as rotas configuradas

**Recursos incluídos:**
- ✅ Variáveis de ambiente (base_url, token, account_number)
- ✅ Autenticação Bearer Token automática
- ✅ Scripts para salvar token automaticamente após login/registro
- ✅ Todas as rotas documentadas com exemplos

**Endpoints incluídos:**
- **Authentication:** Register, Login, Logout, Refresh, Me
- **Wallet:** Balance, Deposit, Withdraw, Transfer, Chargeback, Contestar, Transaction Details, Statement

**Dica:** Ao fazer Register ou Login, o token é automaticamente salvo nas variáveis da collection!

---

### 3. Consultas SQLite (`sqlite-queries.sql`)

Arquivo com dezenas de queries SQL úteis para consultar e analisar o banco de dados.

**Como usar:**

```bash
# Executar todas as queries
sqlite3 database/database.sqlite < examples/sqlite-queries.sql

# Abrir interativamente
sqlite3 database/database.sqlite

# Dentro do SQLite, execute queries específicas
sqlite> .read examples/sqlite-queries.sql
```

**Categorias de Queries:**

#### 📋 Consultas Básicas
- Listar todos os usuários
- Listar contas com saldo
- Ver estrutura de tabelas

#### 💸 Transações
- Últimas transações de uma conta
- Total de transações por tipo
- Transações do dia
- Histórico detalhado

#### 🔄 Transferências
- Todas as transferências
- Transferências recebidas
- Transferências enviadas
- Ranking de transferências

#### 📊 Limites Diários
- Consultar limites de uma conta
- Contas próximas do limite (80%+)
- Uso de limites por tipo

#### 📈 Análises e Relatórios
- Extrato consolidado por dia
- Ranking de usuários por saldo
- Movimentação geral do dia
- Estatísticas por tipo de transação

#### 🔍 Auditoria e Segurança
- Transações estornadas
- Transações contestadas
- Contas inativas/bloqueadas
- Transações de alto valor (>R$ 1.000)
- Histórico de alterações de saldo

**Exemplos de uso:**

```bash
# Ver saldo de todas as contas
sqlite3 database/database.sqlite "
SELECT
  u.name as usuario,
  a.account_number as conta,
  PRINTF('R$ %.2f', b.amount) as saldo
FROM accounts a
JOIN users u ON a.user_id = u.id
JOIN balances b ON a.id = b.account_id
ORDER BY b.amount DESC;
"

# Últimas 10 transações
sqlite3 database/database.sqlite "
SELECT
  t.transaction_id,
  tt.name as tipo,
  t.amount,
  t.description,
  datetime(t.created_at) as data
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
ORDER BY t.created_at DESC
LIMIT 10;
"

# Movimentação do dia
sqlite3 database/database.sqlite "
SELECT
  COUNT(*) as total_transacoes,
  SUM(CASE WHEN t.flow = 'C' THEN t.amount ELSE 0 END) as creditos,
  SUM(CASE WHEN t.flow = 'D' THEN t.amount ELSE 0 END) as debitos
FROM transactions t
WHERE DATE(t.created_at) = DATE('now');
"
```

---

## 🚀 Workflow Recomendado

### Para começar a desenvolver:

1. **Execute o script de teste**
   ```bash
   ./examples/api-usage.sh
   ```
   Isso criará dados de exemplo no banco.

2. **Explore o banco de dados**
   ```bash
   sqlite3 database/database.sqlite
   .read examples/sqlite-queries.sql
   ```
   Use as queries para entender a estrutura dos dados.

3. **Use o Postman**
   - Importe a collection
   - Faça Register/Login
   - Teste os endpoints manualmente

4. **Desenvolva sua feature**
   - Consulte o banco para verificar os dados
   - Use o Postman para testar
   - Execute os testes automatizados

---

## 💡 Dicas Úteis

### Resetar Banco de Dados

```bash
# Apagar banco e recriar
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed

# OU via Docker
docker compose exec app rm database/database.sqlite
docker compose exec app touch database/database.sqlite
docker compose exec app php artisan migrate:fresh --seed
```

### Debug de Queries SQLite

```bash
# Ver todas as queries executadas
sqlite3 database/database.sqlite
sqlite> PRAGMA query_only = ON;  # Modo somente leitura
sqlite> .echo on                  # Mostrar queries
sqlite> .headers on               # Mostrar cabeçalhos
sqlite> .mode column              # Formatação em colunas
```

### Exportar Dados

```bash
# Exportar para CSV
sqlite3 database/database.sqlite <<EOF
.headers on
.mode csv
.output users.csv
SELECT * FROM users;
.quit
EOF

# Exportar para JSON
sqlite3 database/database.sqlite "SELECT json_object(
  'id', id,
  'name', name,
  'email', email
) FROM users" | jq '.'
```

### Backup do Banco

```bash
# Criar backup
sqlite3 database/database.sqlite ".backup database/backup.sqlite"

# Restaurar backup
rm database/database.sqlite
sqlite3 database/database.sqlite ".restore database/backup.sqlite"
```

---

## 🔗 Recursos Adicionais

### Ferramentas Recomendadas

- **[Postman](https://www.postman.com/)** - Testar APIs
- **[DB Browser for SQLite](https://sqlitebrowser.org/)** - GUI para SQLite
- **[jq](https://stedolan.github.io/jq/)** - Processar JSON no terminal
- **[HTTPie](https://httpie.io/)** - Alternativa ao curl (mais amigável)

### HTTPie Examples

```bash
# Install
brew install httpie

# Register
http POST localhost:8000/api/auth/register \
  data:='{"name":"João","email":"joao@test.com","password":"senha123","password_confirmation":"senha123"}'

# Login e salvar token
export TOKEN=$(http POST localhost:8000/api/auth/login \
  data:='{"email":"joao@test.com","password":"senha123"}' \
  | jq -r '.data.token')

# Balance
http GET localhost:8000/api/wallet/balance \
  "Authorization: Bearer $TOKEN"

# Deposit
http POST localhost:8000/api/wallet/deposit \
  "Authorization: Bearer $TOKEN" \
  data:='{"amount":500,"description":"Depósito"}'
```

---

## 📚 Mais Informações

- **README Principal:** [../README.md](../README.md)
- **Documentação de Testes:** [../docs/TESTING.md](../docs/TESTING.md)
- **Swagger/OpenAPI:** http://localhost:8000/api/documentation

---

**Última atualização:** 17/11/2025
