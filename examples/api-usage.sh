#!/bin/bash

# Digital Wallet API - Exemplos de Uso
# Execute: chmod +x examples/api-usage.sh && ./examples/api-usage.sh

API_URL="http://localhost:8000/api"
TOKEN=""

echo "🚀 Digital Wallet API - Exemplos de Uso"
echo "=========================================="
echo ""

# Função para fazer requests
request() {
  local method=$1
  local endpoint=$2
  local data=$3

  echo "📡 $method $endpoint"

  if [ -z "$TOKEN" ]; then
    curl -s -X $method "$API_URL$endpoint" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "$data" | jq '.'
  else
    curl -s -X $method "$API_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "$data" | jq '.'
  fi

  echo ""
}

# 1. REGISTRAR USUÁRIO
echo "1️⃣ Registrando novo usuário..."
REGISTER_DATA='{
  "data": {
    "name": "João Silva",
    "email": "joao'$(date +%s)'@example.com",
    "password": "senha123",
    "password_confirmation": "senha123"
  }
}'

REGISTER_RESPONSE=$(request "POST" "/auth/register" "$REGISTER_DATA")
TOKEN=$(echo $REGISTER_RESPONSE | jq -r '.data.token')
ACCOUNT_NUMBER=$(echo $REGISTER_RESPONSE | jq -r '.data.account_number')

echo "✅ Usuário registrado!"
echo "📝 Token: ${TOKEN:0:50}..."
echo "🏦 Conta: $ACCOUNT_NUMBER"
echo ""

sleep 1

# 2. CONSULTAR SALDO
echo "2️⃣ Consultando saldo..."
request "GET" "/wallet/balance"
sleep 1

# 3. FAZER DEPÓSITO
echo "3️⃣ Fazendo depósito de R$ 1.000,00..."
DEPOSIT_DATA='{
  "data": {
    "amount": 1000.00,
    "description": "Depósito inicial"
  }
}'
request "POST" "/wallet/deposit" "$DEPOSIT_DATA"
sleep 1

# 4. FAZER OUTRO DEPÓSITO
echo "4️⃣ Fazendo depósito de R$ 500,00..."
DEPOSIT_DATA2='{
  "data": {
    "amount": 500.00,
    "description": "Depósito via PIX"
  }
}'
request "POST" "/wallet/deposit" "$DEPOSIT_DATA2"
sleep 1

# 5. CONSULTAR SALDO ATUALIZADO
echo "5️⃣ Consultando saldo atualizado..."
request "GET" "/wallet/balance"
sleep 1

# 6. FAZER SAQUE
echo "6️⃣ Fazendo saque de R$ 200,00..."
WITHDRAW_DATA='{
  "data": {
    "amount": 200.00,
    "description": "Saque em caixa eletrônico"
  }
}'
request "POST" "/wallet/withdraw" "$WITHDRAW_DATA"
sleep 1

# 7. REGISTRAR SEGUNDO USUÁRIO
echo "7️⃣ Registrando segundo usuário..."
REGISTER_DATA2='{
  "data": {
    "name": "Maria Santos",
    "email": "maria'$(date +%s)'@example.com",
    "password": "senha123",
    "password_confirmation": "senha123"
  }
}'

REGISTER_RESPONSE2=$(curl -s -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$REGISTER_DATA2")

RECEIVER_ACCOUNT=$(echo $REGISTER_RESPONSE2 | jq -r '.data.account_number')
echo "✅ Segunda usuária registrada!"
echo "🏦 Conta destino: $RECEIVER_ACCOUNT"
echo ""
sleep 1

# 8. FAZER TRANSFERÊNCIA
echo "8️⃣ Fazendo transferência de R$ 100,00 para $RECEIVER_ACCOUNT..."
TRANSFER_DATA='{
  "data": {
    "receiver_account_number": "'$RECEIVER_ACCOUNT'",
    "amount": 100.00,
    "description": "Pagamento jantar"
  }
}'
request "POST" "/wallet/transfer" "$TRANSFER_DATA"
sleep 1

# 9. CONSULTAR SALDO FINAL
echo "9️⃣ Consultando saldo final..."
request "GET" "/wallet/balance"
sleep 1

# 10. CONSULTAR EXTRATO
echo "🔟 Consultando extrato..."
START_DATE=$(date -v-30d +%Y-%m-%d 2>/dev/null || date -d '30 days ago' +%Y-%m-%d)
END_DATE=$(date +%Y-%m-%d)
request "GET" "/wallet/statement?start_date=$START_DATE&end_date=$END_DATE&per_page=10"
sleep 1

# 11. MEUS DADOS
echo "1️⃣1️⃣ Consultando meus dados..."
request "GET" "/auth/me"

echo ""
echo "✅ Exemplos concluídos!"
echo ""
echo "📝 Resumo:"
echo "   - Usuário criado: João Silva"
echo "   - Conta: $ACCOUNT_NUMBER"
echo "   - Saldo final: R$ 1.200,00 (1000 + 500 - 200 - 100)"
echo ""
echo "💡 Dica: Salve o token para usar em outras requisições"
echo "   export TOKEN='$TOKEN'"
echo ""
