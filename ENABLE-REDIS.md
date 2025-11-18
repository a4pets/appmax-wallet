# Como Habilitar Redis

O Redis já está configurado no projeto, mas está **desabilitado por padrão** para permitir testes locais sem Docker.

## ✅ Opção 1: Com Docker (Recomendado)

### 1. Edite o `.env`

```bash
# Performance Optimization
USE_REDIS=true

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 2. Reinicie os containers

```bash
docker compose down
docker compose up -d
```

### 3. Teste a conexão

```bash
docker compose exec app php artisan tinker
```

No Tinker:
```php
Cache::put('test', 'Redis funcionando!', 60);
Cache::get('test');
// Deve retornar: "Redis funcionando!"
```

---

## 🖥️ Opção 2: Sem Docker (Local)

### 1. Instale o Redis localmente

**macOS:**
```bash
brew install redis
brew services start redis
```

**Ubuntu/Debian:**
```bash
sudo apt-get install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

**Windows:**
- Baixe: https://redis.io/download
- Ou use WSL2

### 2. Instale a extensão PHP Redis

**macOS:**
```bash
pecl install redis
# Adicione ao php.ini: extension=redis.so
```

**Ubuntu/Debian:**
```bash
sudo apt-get install php-redis
sudo systemctl restart php8.3-fpm
```

### 3. Edite o `.env`

```bash
USE_REDIS=true
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis local
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 4. Teste

```bash
php artisan tinker
```

```php
Cache::put('test', 'Redis local funcionando!', 60);
Cache::get('test');
```

---

## 🔄 Desabilitar Redis (Voltar para Database)

Caso queira desabilitar o Redis e voltar a usar database:

```bash
# .env
USE_REDIS=false
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

Limpe o cache:
```bash
php artisan cache:clear
php artisan config:clear
```

---

## 📊 Monitorar Redis

### Ver estatísticas

```bash
# Com Docker
docker compose exec redis redis-cli INFO stats

# Local
redis-cli INFO stats
```

### Monitorar comandos em tempo real

```bash
# Com Docker
docker compose exec redis redis-cli MONITOR

# Local
redis-cli MONITOR
```

### Ver chaves armazenadas

```bash
# Com Docker
docker compose exec redis redis-cli KEYS "*"

# Local
redis-cli KEYS "*"
```

---

## ⚠️ Troubleshooting

### "Connection refused" ao conectar no Redis

**Com Docker:**
```bash
# Verificar se container está rodando
docker compose ps redis

# Ver logs
docker compose logs redis

# Reiniciar
docker compose restart redis
```

**Local:**
```bash
# Verificar se está rodando
redis-cli ping
# Deve retornar: PONG

# Se não estiver rodando
brew services start redis  # macOS
sudo systemctl start redis-server  # Linux
```

### Extensão Redis não encontrada

```bash
# Verificar se extensão está instalada
php -m | grep redis

# Se não estiver, instale:
pecl install redis
```

Depois adicione ao `php.ini`:
```ini
extension=redis.so
```

### Cache não está funcionando

```bash
# Limpe todos os caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reinicie o servidor
docker compose restart app
```

---

## 🎯 Benefícios de Usar Redis

| Métrica | Database | Redis | Ganho |
|---------|----------|-------|-------|
| Cache read | 10-50ms | 0.1-1ms | 10-100x |
| Cache write | 5-20ms | 0.1-0.5ms | 10-50x |
| Session lookup | 5-15ms | 0.5-2ms | 5-10x |
| Queue processing | Moderado | Rápido | 3-5x |

**Recomendação:** Use Redis em produção para melhor performance e escalabilidade.

---

**Dúvidas?** Consulte: [docs/PERFORMANCE-OPTIMIZATION.md](docs/PERFORMANCE-OPTIMIZATION.md)
