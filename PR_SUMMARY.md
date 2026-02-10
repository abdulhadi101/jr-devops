# Pull Request Summary - Secure Drop Application

**Production URL**: https://secure-drop.asuku.xyz
**API Documentation**: https://secure-drop.asuku.xyz/docs
**Health Check**: https://secure-drop.asuku.xyz/api/health

## Challenge Completion Status

### Backend Requirements
- **Laravel 11 application** implemented with Service-Repository Pattern.
- **Encryption**: Database columns encrypted using Laravel's `encrypted` cast.
- **IDs**: UUIDs used for all primary keys (non-sequential).
- **Endpoints**:
    - `POST /api/v1/secrets` (Create with TTL)
    - `GET /api/v1/secrets/{id}` (Burn on read)
- **Documentation**: Auto-generated via Scribe (baked into Docker image).

### DevOps Requirements
- **Multi-stage Dockerfile**:
    - Composer stage (builds deps & docs)
    - Base stage (Alpine 3.19 + PHP 8.2)
    - Production stage (Optimized, <400MB)
- **Security**: Application runs as non-root user (`www`).
- **Orchestration**: 3-file Docker Compose strategy (base, override, prod).
- **Reverse Proxy**: Traefik handles SSL (Let's Encrypt), routing, and HTTP->HTTPS redirects via Docker Labels.
- **Health Checks**: Implemented for App, DB, and Redis.

### CI/CD & Deployment
- **GitHub Actions**: Tests -> Lint -> Build -> Trivy Scan -> Push to GHCR -> Deploy.
- **One-Line Installer**: Custom `install.sh` script for zero-touch VPS setup.

## 🏗️ Architecture Decisions

### 1. **Alpine Linux Base Image**
**Decision**: Use `php:8.2-fpm-alpine` instead of Debian

**Rationale**:
- **Size**: Alpine images are ~5x smaller (final image: ~180MB vs ~900MB)
- **Security**: Smaller attack surface with minimal packages
- **Performance**: Faster pulls and deployments

**Trade-off**: Required careful handling of build dependencies for PHP extensions

### 2. **Single Container Architecture**
**Decision**: Run Nginx + PHP-FPM in one container using Supervisor

**Rationale**:
- **Simplicity**: Easier deployment and orchestration
- **Performance**: No network overhead between Nginx and PHP-FPM
- **Resource Efficiency**: Lower memory footprint

**Alternative Considered**: Separate containers for Nginx and PHP-FPM (more "Docker-native" but adds complexity)

### 3. **PostgreSQL over MySQL**
**Decision**: Use PostgreSQL 16 as the database

**Rationale**:
- **Data Integrity**: Better ACID compliance
- **JSON Support**: Superior JSON operations for future features
- **Performance**: Better for complex queries and concurrent writes

### 4. **GitHub Container Registry (GHCR)**
**Decision**: Use GHCR instead of Docker Hub

**Rationale**:
- **Integration**: Native GitHub integration
- **Free**: Unlimited public images
- **Security**: Built-in vulnerability scanning
- **Permissions**: Leverages GitHub's permission model

### 5. **Let's Encrypt via Traefik**
**Decision**: Automatic SSL certificate management through Traefik

**Rationale**:
- **Automation**: Zero-touch certificate renewal
- **Simplicity**: No separate cert-bot containers
- **Reliability**: Traefik's ACME client is battle-tested

### 6. **Redis for Cache and Sessions**
**Decision**: Use Redis for Laravel cache and session storage

**Rationale**:
- **Performance**: In-memory storage provides sub-millisecond response times
- **Persistence**: Optional persistence ensures session durability across restarts
- **Scalability**: Easy to scale horizontally if traffic increases
- **Laravel Native**: First-class support with simple configuration
- **Atomic Operations**: Built-in support for atomic increments/decrements


## 🔌 API Usage & Application Flow

The application follows a strict **Create → Read → Burn** cycle.

### 1. Create a Secret
Send a POST request to create a new secret. You can optionally set a `ttl` (Time To Live) in seconds.

```bash
curl -X POST https://secure-drop.asuku.xyz/api/v1/secrets \
  -H "Content-Type: application/json" \
  -d '{"content":"SUPER_SECRET_PASSWORD","ttl":3600}'
```

**Response**:
```json
{
  "id": "9d4f5e6a-7b8c-9d0e-1f2a-3b4c5d6e7f8a",
  "url": "https://secure-drop.asuku.xyz/api/v1/secrets/9d4f5e6a-...",
  "expires_at": "2024-02-10T12:00:00Z"
}
```

### 2. Retrieve a Secret (Burn on Read)
Accessing the secret via GET will return the content **once**. Immediately after this request, the secret is deleted from the database.

```bash
curl https://secure-drop.asuku.xyz/api/v1/secrets/9d4f5e6a-7b8c-9d0e-1f2a-3b4c5d6e7f8a
```

**Response**:
```json
{
  "content": "SUPER_SECRET_PASSWORD"
}
```

### 3. Verification (Try to Read Again)
Any subsequent attempts to read the same secret will fail, as it has been burned.

```bash
curl https://secure-drop.asuku.xyz/api/v1/secrets/9d4f5e6a-7b8c-9d0e-1f2a-3b4c5d6e7f8a
```

**Response**:
```json
{
  "error": "Secret not found or already accessed"
}
```

## 🚀 Deployment Strategies

### 1. Manual Deployment (The "Zero to Hero" Script)
Recommended for initial VPS setup. This script installs Docker, configures the firewall, generates secrets, and deploys the stack.

```bash
curl -fsSL https://raw.githubusercontent.com/abdulhadi101/jr-devops/main/install.sh | \
  DOMAIN=secure-drop.asuku.xyz \
  ACME_EMAIL=your-email@gmail.com \
  bash
```

### 2. Automated CI/CD
Pushing to the `main` branch triggers the GitHub Actions pipeline.
- **Test & Lint**: PHPUnit and Pint.
- **Security**: Trivy image scan.
- **Deploy**: SSH into VPS, pull `ghcr.io/...:latest`, and update containers.

## 🔧 Troubleshooting & Common Issues

If you encounter issues immediately after deployment (e.g., 500 Error on `/docs`), it is usually due to Laravel's aggressive caching in production.

### Issue: API Docs (Scribe) returns 500 or 404
**Cause**: The application route cache may contain stale data, or the docs were generated before routes were fully registered.

**Fix**: Run the following commands inside the container to clear the cache and regenerate the docs on the live server:

```bash
# 1. Clear the route cache (Crucial for Scribe)
docker compose --env-file .env.docker exec app php artisan route:clear

# 2. Regenerate documentation (Optional, if static assets are missing)
docker compose --env-file .env.docker exec app php artisan scribe:generate

# 3. Re-optimize for production performance
docker compose --env-file .env.docker exec app php artisan optimize
```

### Issue: "View [scribe.index] not found"
**Cause**: The static HTML files were not copied correctly during the Docker build or were overwritten.
**Fix**: The `install.sh` script handles this, but you can force regeneration using the commands above.

## ⚡ Quick Start (Local)

To run this project locally with Hot-Reloading:

```bash
# 1. Start Services
docker compose up -d

# 2. Run Migrations
docker compose exec app php artisan migrate

# 3. Access
# App: http://secure-drop.localhost
# Traefik Dashboard: http://localhost:8080
```
