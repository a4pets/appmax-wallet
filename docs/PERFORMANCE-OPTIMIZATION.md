# Performance & Security Optimization Guide

Este documento descreve as otimizações de performance e segurança implementadas na Digital Wallet API.

## 🚀 Otimizações Implementadas

### 1. Redis (Cache, Session & Queue)

O Redis foi adicionado como opção de cache, session e queue para melhorar significativamente a performance.

#### Configuração

**No Docker:**
- Container Redis 7 Alpine já configurado no `docker-compose.yml`
- Porta: `6379`
- Persistência: Volume `redis_data`
- Health check automático

**Habilitar Redis:**

1. Edite o arquivo `.env`:
```env
USE_REDIS=true
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

2. Para uso local sem Docker:
```env
USE_REDIS=false
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
REDIS_HOST=127.0.0.1
```

**Benefícios:**
- Cache até 100x mais rápido que database
- Sessões distribuídas (multi-servidor)
- Queue processing mais eficiente
- Redução de I/O no banco de dados

---

### 2. OPcache + JIT (PHP 8.3)

OPcache com JIT (Just-In-Time Compilation) para acelerar a execução do código PHP.

#### Configuração Ativa

```ini
opcache.enable=1
opcache.memory_consumption=256M
opcache.interned_strings_buffer=16M
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.enable_cli=1
opcache.jit_buffer_size=100M
opcache.jit=tracing
opcache.validate_timestamps=0
```

**Benefícios:**
- Código compilado mantido em memória
- JIT compila hot paths para código nativo
- Redução de 30-50% no tempo de execução
- Menor uso de CPU

**⚠️ Produção:** `opcache.validate_timestamps=0` desabilita verificação de mudanças. Para recarregar código após deploy:
```bash
docker compose exec app php artisan opcache:clear
# ou
docker compose restart app
```

---

### 3. Rate Limiting

Proteção contra abuso e ataques de força bruta através de rate limiting por IP.

#### Limites Configurados

| Rota | Limite | Proteção |
|------|--------|----------|
| `/api/auth/register` | 5 req/min | Previne spam de contas |
| `/api/auth/login` | 5 req/min | Previne brute force |
| Rotas autenticadas (leitura) | 60 req/min | Uso geral da API |
| Transações (depósito, saque, etc) | 10 req/min | Previne fraude |

#### Resposta de Rate Limit Excedido

```json
{
  "message": "Too Many Requests",
  "retry_after": 60
}
```

**Headers de resposta:**
- `X-RateLimit-Limit`: Limite total
- `X-RateLimit-Remaining`: Requisições restantes
- `Retry-After`: Segundos até reset

**Customização:**

Edite `routes/api.php`:
```php
// Exemplo: 100 requests por minuto
Route::middleware('throttle:100,1')->group(function () {
    // rotas aqui
});

// Por hora: 1000 requests
Route::middleware('throttle:1000,60')->group(function () {
    // rotas aqui
});
```

---

### 4. Laravel Octane + Swoole

Servidor de aplicação de alta performance que mantém o Laravel em memória.

#### O que é Octane?

- Mantém aplicação Laravel em memória (sem bootstrap a cada request)
- Pool de workers assíncronos
- Suporte a HTTP/2 e WebSockets
- 10-100x mais rápido que PHP-FPM tradicional

#### Configuração Atual

**Supervisor (`docker/supervisord.conf`):**
```ini
[program:octane]
command=php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=4 --task-workers=6 --max-requests=1000
```

**Parâmetros:**
- `--workers=4`: 4 workers para requests HTTP
- `--task-workers=6`: 6 workers para tarefas assíncronas
- `--max-requests=1000`: Recicla worker após 1000 requests (previne memory leaks)

**Nginx como Reverse Proxy:**
- Nginx (porta 80) → Octane (porta 8000)
- Arquivos estáticos servidos diretamente pelo Nginx
- Requests dinâmicos proxy para Octane

#### Performance Esperada

| Métrica | PHP-FPM | Octane + Swoole | Ganho |
|---------|---------|-----------------|-------|
| Requests/seg | 100-200 | 5000-10000 | 25-100x |
| Latência média | 50-100ms | 5-20ms | 5-10x |
| Memória | Alta | Média | 30-50% |

#### Desenvolvimento Local

Para rodar Octane localmente (sem Docker):
```bash
php artisan octane:start --server=swoole --watch
```

O parâmetro `--watch` recarrega automaticamente ao detectar mudanças.

#### ⚠️ Cuidados com Octane

1. **State Management:**
   - Não use variáveis estáticas ou singletons que mudam
   - Sempre limpe estado após cada request

2. **Container Bindings:**
   - Evite bindings que mantêm estado
   - Use `$this->app->forgetInstance()` se necessário

3. **Debugging:**
```bash
# Ver logs do Octane
docker compose logs -f app

# Recarregar workers
php artisan octane:reload

# Status dos workers
php artisan octane:status
```

---

### 5. Security Headers

Headers HTTP de segurança para proteger contra vulnerabilidades comuns.

#### Headers Aplicados

| Header | Valor | Proteção |
|--------|-------|----------|
| `X-Content-Type-Options` | `nosniff` | MIME type sniffing |
| `X-Frame-Options` | `DENY` | Clickjacking |
| `X-XSS-Protection` | `1; mode=block` | XSS (navegadores antigos) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | Force HTTPS (prod) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Controla referrer |
| `Content-Security-Policy` | Restritivo | XSS, injection |
| `Permissions-Policy` | Desabilita features | Geolocation, camera, etc |

#### Implementação

**Middleware:** `app/Http/Middleware/SecurityHeaders.php`

Aplicado automaticamente em todas as rotas da API via `bootstrap/app.php`.

#### Customização do CSP

Edite `app/Http/Middleware/SecurityHeaders.php`:
```php
// Exemplo: permitir Google Fonts
$response->headers->set('Content-Security-Policy',
    "default-src 'self'; font-src 'self' fonts.gstatic.com; style-src 'self' fonts.googleapis.com 'unsafe-inline'"
);
```

#### Testar Headers

```bash
curl -I http://localhost:8000/api/health
```

Ou use: https://securityheaders.com/

---

## 📊 Ganhos Esperados

Com todas as otimizações implementadas:

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| Requests/segundo | 100-200 | 5000-10000 | 25-100x |
| Tempo de resposta | 50-100ms | 5-20ms | 5-10x |
| Cache hit rate | N/A | 80-95% | - |
| Uso de CPU | Alto | Baixo | -40% |
| Uso de memória | Alto | Otimizado | -30% |
| Vulnerabilidades | Média | Baixa | OWASP Top 10 |

---

## 🔧 Comandos Úteis

### Cache
```bash
# Limpar cache
php artisan cache:clear

# Ver estatísticas do Redis
docker compose exec redis redis-cli INFO stats

# Monitorar comandos Redis em tempo real
docker compose exec redis redis-cli MONITOR
```

### OPcache
```bash
# Limpar OPcache
php artisan opcache:clear

# Status do OPcache (via script PHP)
docker compose exec app php -r "print_r(opcache_get_status());"
```

### Octane
```bash
# Start Octane
php artisan octane:start

# Recarregar workers (após deploy)
php artisan octane:reload

# Status
php artisan octane:status

# Parar
php artisan octane:stop
```

### Monitoramento
```bash
# Logs em tempo real
docker compose logs -f app

# Logs do Redis
docker compose logs -f redis

# Logs do Nginx
docker compose exec app tail -f /var/log/nginx/access.log
docker compose exec app tail -f /var/log/nginx/error.log
```

---

## 🚀 Deploy em Produção

### Checklist

1. **Habilitar Redis:**
```env
USE_REDIS=true
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_PASSWORD=sua_senha_forte_aqui
```

2. **Otimizar Laravel:**
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

3. **Verificar OPcache:**
```bash
php -i | grep opcache
```

4. **Configurar HTTPS no Nginx:**
```nginx
server {
    listen 443 ssl http2;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    # ...
}
```

5. **Ambiente de produção:**
```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

6. **Rebuild dos containers:**
```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

---

## 📈 Monitoramento Recomendado

### Tools Sugeridas

1. **APM (Application Performance Monitoring):**
   - Sentry
   - New Relic
   - Datadog

2. **Logs:**
   - ELK Stack (Elasticsearch, Logstash, Kibana)
   - Papertrail

3. **Metrics:**
   - Prometheus + Grafana
   - CloudWatch (AWS)

4. **Uptime:**
   - UptimeRobot
   - Pingdom

---

## 🔍 Troubleshooting

### Redis não conecta

```bash
# Verificar se Redis está rodando
docker compose ps redis

# Testar conexão
docker compose exec app php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

### Octane não inicia

```bash
# Verificar extensão Swoole
docker compose exec app php -m | grep swoole

# Logs detalhados
docker compose exec app php artisan octane:start --verbose
```

### OPcache não funciona

```bash
# Verificar se está habilitado
docker compose exec app php -i | grep opcache.enable

# Rebuild do container
docker compose build --no-cache app
```

### Rate Limit muito restritivo

Edite `routes/api.php` e aumente os limites:
```php
Route::middleware('throttle:100,1')->group(function () {
    // ...
});
```

---

## 📚 Referências

- [Laravel Octane Documentation](https://laravel.com/docs/11.x/octane)
- [Swoole Documentation](https://www.swoole.co.uk/)
- [Redis Documentation](https://redis.io/docs/)
- [OPcache Configuration](https://www.php.net/manual/en/opcache.configuration.php)
- [OWASP Security Headers](https://owasp.org/www-project-secure-headers/)

---

**Última atualização:** 2025-11-17
**Versão:** 1.0.0
