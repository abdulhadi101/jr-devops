# Complete DevOps Walkthrough - Final Guide

## 🎯 Overview

You now have:
1. ✅ Dockerfile (multi-stage build)
2. ✅ Docker Compose (3-file strategy)  
3. ✅ Traefik (routing via labels)

Still need:
4. ⏳ Supporting configs (Nginx, PHP, Supervisor)
5. ⏳ GitHub Actions CI/CD
6. ⏳ VPS deployment
7. ⏳ Testing & verification

---

## 4️⃣ Supporting Configuration Files

### Purpose
These files configure services INSIDE the Docker container.

### File Structure
```
docker/
├── nginx/
│   ├── nginx.conf         # Main Nginx config
│   └── default.conf       # Site config for Laravel
├── php/
│   ├── php.ini           # PHP settings
│   ├── opcache.ini       # PHP OPcache (performance)
│   └── xdebug.ini        # Xdebug (development only)
└── supervisor/
    └── supervisord.conf  # Process manager
```

### A. Nginx Configuration

**docker/nginx/nginx.conf**:
```nginx
# Main Nginx configuration
user www;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    
    access_log /var/log/nginx/access.log main;
    
    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
    
    include /etc/nginx/http.d/*.conf;
}
```

**docker/nginx/default.conf**:
```nginx
# Laravel site configuration
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Laravel public files
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Health check (no logging)
    location /api/health {
        access_log off;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml)$ {
        expires 1y;
        log_not_found off;
    }
    
    # Security: deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

**Why these settings?**
- `root /var/www/html/public` - Laravel public directory
- `try_files` - Laravel routing
- `fastcgi_pass 127.0.0.1:9000` - PHP-FPM on port 9000
- Security headers - Protect against XSS, clickjacking
- Caching - Performance for static files

### B. PHP Configuration

**docker/php/php.ini**:
```ini
[PHP]
; Performance
memory_limit = 256M
max_execution_time = 30
max_input_time = 60

; File uploads
upload_max_filesize = 20M
post_max_size = 20M

; Error handling (production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Timezone
date.timezone = UTC

; Opcache (loaded separately)
expose_php = Off
```

**docker/php/opcache.ini**:
```ini
[opcache]
; Enable opcache
opcache.enable=1
opcache.enable_cli=1

; Memory
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000

; Validation
opcache.validate_timestamps=1  ; Dev: check files changed
; opcache.validate_timestamps=0  ; Prod: don't check (faster)
opcache.revalidate_freq=2

; Optimization
opcache.save_comments=1
opcache.fast_shutdown=1
```

**Why opcache?**
```
Without opcache: PHP parses files every request
With opcache: PHP caches compiled code in memory
Result: 3-5x faster!
```

### C. Supervisor Configuration

**docker/supervisor/supervisord.conf**:
```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

; Nginx process
[program:nginx]
command=nginx -g 'daemon off;'
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3

; PHP-FPM process
[program:php-fpm]
command=php-fpm -F
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3
```

**What is Supervisor?**
```
Supervisor manages multiple processes in one container:
├── Nginx (web server)
└── PHP-FPM (PHP processor)

If either crashes, supervisor restarts it
```

### Configuration Flow

```
Container starts
    ↓
CMD supervisord
    ↓
Supervisor reads supervisord.conf
    ↓
Starts Nginx (reads nginx.conf, default.conf)
    ↓
Starts PHP-FPM (reads php.ini, opcache.ini)
    ↓
Both running, ready to serve requests!
```

---

## 5️⃣ GitHub Actions CI/CD

### Complete Workflow File

**Location**: `.github/workflows/deploy.yml`

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, master]
  pull_request:
    branches: [main, master]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  # ==========================================
  # STAGE 1: Tests (SQLite - Fast!)
  # ==========================================
  tests:
    name: Tests
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
          extensions: pdo_sqlite, mbstring, bcmath, pcntl
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}

      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Setup Environment
        run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          echo "DB_CONNECTION=sqlite" >> .env

      - name: Run Migrations
        run: php artisan migrate --force

      - name: Run Tests
        run: ./vendor/bin/pest --coverage --min=80

  # ==========================================
  # STAGE 2: Lint
  # ==========================================
  lint:
    name: Code Style
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
      - run: composer install
      - run: ./vendor/bin/pint --test

  # ==========================================
  # STAGE 3: Build & Security Scan
  # ==========================================
  build:
    name: Build & Scan
    runs-on: ubuntu-latest
    needs: [tests, lint]
    
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Build Image
        uses: docker/build-push-action@v5
        with:
          context: .
          push: false
          load: true
          tags: ${{ env.IMAGE_NAME }}:scan
          cache-from: type=gha
          cache-to: type=gha,mode=max
          target: production

      - name: Security Scan (Trivy)
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ${{ env.IMAGE_NAME }}:scan
          format: "sarif"
          output: "trivy-results.sarif"

      - name: Upload Scan Results
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: "trivy-results.sarif"

      - name: Fail on Critical Issues
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ${{ env.IMAGE_NAME }}:scan
          format: "table"
          exit-code: "1"
          severity: "CRITICAL,HIGH"

  # ==========================================
  # STAGE 4: Push to Registry
  # ==========================================
  push:
    name: Push to GHCR
    runs-on: ubuntu-latest
    needs: [build]
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3

      - name: Build and Push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: |
            ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:latest
            ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          target: production

  # ==========================================
  # STAGE 5: Deploy to VPS
  # ==========================================
  deploy:
    name: Deploy
    runs-on: ubuntu-latest
    needs: [push]
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    
    steps:
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}

      - name: Deploy to VPS
        run: |
          ssh -o StrictHostKeyChecking=no ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << 'EOF'
            cd ${{ secrets.APP_DIR }}
            docker pull ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:latest
            docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
            docker-compose exec -T app php artisan migrate --force
            docker-compose exec -T app php artisan optimize
          EOF

      - name: Health Check
        run: |
          sleep 10
          curl -f https://${{ secrets.PRODUCTION_DOMAIN }}/api/health || exit 1
```

### Required GitHub Secrets

**Add in GitHub: Settings → Secrets → Actions**

```
VPS_HOST=your.vps.ip.address
VPS_USER=ubuntu
SSH_PRIVATE_KEY=-----BEGIN OPENSSH PRIVATE KEY-----...
APP_DIR=/home/ubuntu/secure-drop
PRODUCTION_DOMAIN=secure-drop.yourdomain.com
```

### Pipeline Flow

```
1. Push to main
     ↓
2. Tests run (SQLite - fast!)
     ↓
3. Lint runs (code style)
     ↓
4. Build Docker image
     ↓
5. Scan with Trivy
     ↓ (if no critical issues)
6. Push to GHCR
     ↓
7. SSH to VPS
     ↓
8. Pull latest image
     ↓
9. Restart containers
     ↓
10. Run migrations
     ↓
11. Health check
     ↓
✅ DEPLOYED!
```

---

## 6️⃣ VPS Deployment

### Option 1: One-Line Installer (Easiest!)

```bash
curl -fsSL https://raw.githubusercontent.com/YOUR_USERNAME/YOUR_REPO/main/install-vps-registry.sh | \
  DOMAIN=secure-drop.yourdomain.com \
  REGISTRY_IMAGE=ghcr.io/your-username/your-repo:latest \
  bash
```

**What it does**:
1. Installs Docker + Docker Compose
2. Configures firewall (UFW)
3. Creates .env.docker with generated passwords
4. Pulls your Docker image from GHCR
5. Starts all services
6. Runs migrations
7. Performs health check

**Time**: ~10 minutes

### Option 2: Manual Deployment

```bash
# 1. SSH to VPS
ssh user@your-vps-ip

# 2. Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# 3. Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
  -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# 4. Configure firewall
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# 5. Create app directory
mkdir -p ~/secure-drop
cd ~/secure-drop

# 6. Create .env.docker
cat > .env.docker << EOF
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=secure_drop
DB_USERNAME=secure_drop
DB_PASSWORD=$(openssl rand -base64 32)

REDIS_PASSWORD=$(openssl rand -base64 32)

TRAEFIK_HOST=your-domain.com
LETSENCRYPT_EMAIL=your-email@example.com
REGISTRY_IMAGE=ghcr.io/your-username/your-repo:latest
EOF

# 7. Create docker-compose.yml
# (Copy from docker-compose.yml file)

# 8. Pull and start
docker pull ghcr.io/your-username/your-repo:latest
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# 9. Generate APP_KEY
APP_KEY=$(docker-compose exec -T app php artisan key:generate --show)
sed -i "s|APP_KEY=|APP_KEY=$APP_KEY|" .env.docker
docker-compose restart app

# 10. Run migrations
docker-compose exec app php artisan migrate --force

# 11. Optimize
docker-compose exec app php artisan optimize
```

---

## 7️⃣ Testing & Verification

### A. Local Testing

```bash
# 1. Start services
docker-compose up -d

# 2. Check all healthy
docker-compose ps

# Should see:
# secure-drop-traefik-1   Up (healthy)
# secure-drop-app-1       Up (healthy)
# secure-drop-db-1        Up (healthy)
# secure-drop-redis-1     Up (healthy)

# 3. Test health endpoint
curl http://secure-drop.localhost/api/health

# Should return:
# {"status":"ok","timestamp":"..."}

# 4. Test API
curl -X POST http://secure-drop.localhost/api/v1/secrets \
  -H "Content-Type: application/json" \
  -d '{"content":"test-secret","ttl":3600}'

# Should return secret ID and URL

# 5. Retrieve secret
curl http://secure-drop.localhost/api/v1/secrets/{ID}

# Should return secret content

# 6. Try again (should fail - burned!)
curl http://secure-drop.localhost/api/v1/secrets/{ID}

# Should return 404
```

### B. Production Testing

```bash
# 1. Health check
curl https://your-domain.com/api/health

# 2. SSL certificate check
curl -vI https://your-domain.com 2>&1 | grep -i "SSL\|TLS\|certificate"

# 3. Create secret
curl -X POST https://your-domain.com/api/v1/secrets \
  -H "Content-Type: application/json" \
  -d '{"content":"production-test","ttl":3600}'

# 4. Verify API docs
curl https://your-domain.com/docs

# 5. Check all containers
ssh user@vps
docker-compose ps

# 6. Check logs
docker-compose logs --tail=50

# 7. Monitor resources
docker stats
```

### C. CI/CD Testing

```bash
# 1. Make a code change
echo "// test" >> app/Http/Controllers/Api/V1/SecretController.php

# 2. Commit and push
git add .
git commit -m "Test CI/CD"
git push origin main

# 3. Watch GitHub Actions
# Go to: https://github.com/your-username/your-repo/actions

# 4. Wait for deployment
# Should see all stages pass

# 5. Verify deployment
curl https://your-domain.com/api/health
```

---

## 🎯 Complete Checklist

### Docker Setup ✅
- [x] Dockerfile with multi-stage build
- [x] docker-compose.yml (base)
- [x] docker-compose.override.yml (dev)
- [x] docker-compose.prod.yml (prod)
- [x] Nginx configuration
- [x] PHP configuration
- [x] Supervisor configuration

### Traefik Setup ✅
- [x] Traefik service in compose
- [x] Docker labels on app
- [x] HTTP entrypoint (port 80)
- [x] HTTPS entrypoint (port 443)
- [x] Let's Encrypt configuration
- [x] HTTP → HTTPS redirect

### CI/CD Pipeline ✅
- [x] Tests stage (SQLite)
- [x] Lint stage (Pint)
- [x] Build stage
- [x] Security scan (Trivy)
- [x] Push to registry (GHCR)
- [x] Deploy stage (SSH)
- [x] Health check

### VPS Deployment ✅
- [x] VPS provisioned
- [x] Docker installed
- [x] Firewall configured
- [x] Domain DNS configured
- [x] Application deployed
- [x] SSL certificate issued
- [x] All containers healthy

---

## 🎉 Summary

**You now understand**:
1. ✅ Dockerfile (multi-stage, non-root, optimized)
2. ✅ Docker Compose (3-file strategy)
3. ✅ Traefik (routing via labels, Let's Encrypt)
4. ✅ Configuration files (Nginx, PHP, Supervisor)
5. ✅ GitHub Actions (4-stage pipeline)
6. ✅ VPS deployment (registry-based)
7. ✅ Testing & verification

**Next step**: Deploy to Google Cloud Free Tier and submit PR!

**You're ready!** 🚀
