#!/bin/bash

# Script para executar testes rapidamente (sem rebuild)

echo "🧪 Executando testes com coverage..."
docker compose -f docker-compose.test.yml exec app-test php artisan test --coverage-html coverage --coverage-text

echo ""
echo "✅ Testes concluídos!"
echo "📊 Relatório HTML: ./coverage/index.html"
