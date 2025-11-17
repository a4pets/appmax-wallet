#!/bin/bash

# Script para executar testes com coverage no Docker

echo "🐳 Construindo imagem Docker com Xdebug..."
docker compose -f docker-compose.test.yml build

echo ""
echo "🚀 Iniciando container de testes..."
docker compose -f docker-compose.test.yml up -d

echo ""
echo "⏳ Aguardando container ficar pronto..."
sleep 5

echo ""
echo "🧪 Executando testes com coverage..."
docker compose -f docker-compose.test.yml exec app-test php artisan test --coverage-html coverage --coverage-text

echo ""
echo "✅ Testes concluídos!"
echo ""
echo "📊 Relatório HTML de coverage gerado em: ./coverage/index.html"
echo ""
echo "Para parar o container de testes, execute:"
echo "  docker compose -f docker-compose.test.yml down"
