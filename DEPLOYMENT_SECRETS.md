# GitHub Secrets Configuration for Deployment

## Required Secrets for CI/CD Pipeline

To enable the automated deployment workflow (`.github/workflows/deploy.yml`), you need to configure the following secrets in your GitHub repository.

### Navigation
Go to: **Repository Settings** → **Secrets and variables** → **Actions** → **New repository secret**

---

## Required Secrets

### 1. `SSH_PRIVATE_KEY`
**Purpose**: SSH key for authenticating to your VPS

**How to generate**:
```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy

# Copy the private key
cat ~/.ssh/github_actions_deploy
```

**Add to GitHub**: Copy the entire private key (including `-----BEGIN` and `-----END` lines)

**On VPS**: Add the public key to `~/.ssh/authorized_keys`
```bash
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

---

### 2. `VPS_USER`
**Purpose**: SSH username for VPS login

**Value**: Your VPS username (e.g., `root`, `ubuntu`, `deploy`)

**Example**: `ubuntu`

---

### 3. `VPS_HOST`
**Purpose**: VPS IP address or hostname

**Value**: Your server's IP address or domain name

**Example**: `203.0.113.42` or `vps.example.com`

---

### 4. `APP_DIR`
**Purpose**: Absolute path to application directory on VPS

**Value**: Full path where your app is deployed

**Example**: `/var/www/secure-drop`

---

### 5. `PRODUCTION_DOMAIN`
**Purpose**: Your production domain name (for health check)

**Value**: The domain where your app is accessible

**Example**: `secure-drop.example.com`

---

### 6. `LETSENCRYPT_EMAIL`
**Purpose**: Email for Let's Encrypt TLS certificates

**Value**: Valid email address for certificate notifications

**Example**: `admin@example.com`

---

## Environment Variables on VPS

Create `.env` file on your VPS at `$APP_DIR/.env`:

```bash
APP_NAME="Secure Drop"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=secure_drop
DB_USERNAME=secure_drop
DB_PASSWORD=SECURE_PASSWORD_HERE

REDIS_PASSWORD=SECURE_PASSWORD_HERE

TRAEFIK_HOST=your-domain.com
LETSENCRYPT_EMAIL=admin@example.com
```

---

## Verification Checklist

Before pushing to trigger deployment:

- [ ] All 6 GitHub Secrets configured
- [ ] SSH key added to VPS `authorized_keys`
- [ ] VPS has Docker and Docker Compose installed
- [ ] Application directory exists on VPS
- [ ] `.env` file configured on VPS
- [ ] Firewall allows ports 80, 443, 22
- [ ] Domain DNS points to VPS IP address

---

## Manual Deployment (Alternative)

If not using GitHub Actions, deploy manually:

```bash
# On VPS
cd /var/www/secure-drop
git pull origin main
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

---

## Troubleshooting

### Deployment fails with "Permission denied"
- Verify SSH key is correctly added to VPS
- Check VPS_USER has permissions on APP_DIR

### Health check fails
- Verify domain DNS is configured
- Check Traefik is running: `docker ps`
- Check app logs: `docker compose logs app`

### Database connection errors
- Verify DB_PASSWORD matches in .env
- Wait for DB container health check: `docker compose ps`
