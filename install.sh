#!/bin/bash
set -e

# Secure Drop - One-Line VPS Installation Script
# Usage: curl -fsSL https://raw.githubusercontent.com/abdulhadi101/jr-devops/main/install.sh | DOMAIN=your-domain.com bash

echo "🚀 Secure Drop - VPS Installation Script"
echo "=========================================="

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
   echo "❌ Please do not run as root. Run as a regular user with sudo privileges."
   exit 1
fi

# Required environment variables
DOMAIN=${DOMAIN:-""}
ACME_EMAIL=${ACME_EMAIL:-"admin@${DOMAIN}"}
APP_DIR=${APP_DIR:-"$HOME/secure-drop"}

if [ -z "$DOMAIN" ]; then
    echo "❌ Error: DOMAIN environment variable is required"
    echo "Usage: curl -fsSL https://... | DOMAIN=your-domain.com bash"
    exit 1
fi

echo "📋 Configuration:"
echo "  Domain: $DOMAIN"
echo "  Email: $ACME_EMAIL"
echo "  Install Directory: $APP_DIR"
echo ""

# Step 1: Install Docker
echo "📦 Step 1/8: Installing Docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker $USER
    echo "✅ Docker installed"
else
    echo "✅ Docker already installed"
fi

# Step 2: Install Docker Compose
echo "📦 Step 2/8: Installing Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
      -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
    echo "✅ Docker Compose installed"
else
    echo "✅ Docker Compose already installed"
fi

# Step 3: Configure Firewall
echo "🔒 Step 3/8: Configuring firewall..."
if command -v ufw &> /dev/null; then
    sudo ufw --force enable
    sudo ufw allow 22/tcp
    sudo ufw allow 80/tcp
    sudo ufw allow 443/tcp
    echo "✅ Firewall configured"
else
    echo "⚠️  UFW not found, skipping firewall configuration"
fi

# Step 4: Create application directory
echo "📁 Step 4/8: Creating application directory..."
mkdir -p "$APP_DIR"
cd "$APP_DIR"
echo "✅ Directory created: $APP_DIR"

# Step 5: Download docker-compose files
echo "📥 Step 5/8: Downloading configuration files..."
curl -fsSL https://raw.githubusercontent.com/abdulhadi101/jr-devops/main/docker-compose.yml -o docker-compose.yml
curl -fsSL https://raw.githubusercontent.com/abdulhadi101/jr-devops/main/docker-compose.prod.yml -o docker-compose.prod.yml
echo "✅ Configuration files downloaded"

# Step 6: Generate secure credentials (or reuse existing)
echo "🔐 Step 6/8: Generating secure credentials..."

# Check if .env.docker already exists to preserve passwords
if [ -f .env.docker ]; then
    echo "♻️  Reuse existing configuration from .env.docker"
    # Extract existing passwords to ensure they persist
    DB_PASSWORD=$(grep DB_PASSWORD .env.docker | cut -d '=' -f2)
    REDIS_PASSWORD=$(grep REDIS_PASSWORD .env.docker | cut -d '=' -f2)
    
    # If extraction failed, generate new ones
    if [ -z "$DB_PASSWORD" ]; then DB_PASSWORD=$(openssl rand -base64 32); fi
    if [ -z "$REDIS_PASSWORD" ]; then REDIS_PASSWORD=$(openssl rand -base64 32); fi
else
    DB_PASSWORD=$(openssl rand -base64 32)
    REDIS_PASSWORD=$(openssl rand -base64 32)
fi

cat > .env.docker <<EOF
APP_NAME="Secure Drop"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://${DOMAIN}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=secure_drop
DB_USERNAME=secure_drop
DB_PASSWORD=${DB_PASSWORD}

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379

TRAEFIK_HOST=${DOMAIN}
LETSENCRYPT_EMAIL=${ACME_EMAIL}
EOF

echo "✅ Environment file created"

# Step 7: Pull and start services
echo "🐳 Step 7/8: Starting Docker services..."
docker-compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml pull
docker-compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d

# Wait for app container to be ready
echo "⏳ Waiting for application to start..."
sleep 10

# Step 8: Generate APP_KEY and run migrations
echo "🔑 Step 8/8: Initializing application..."
# APP_KEY=$(docker-compose --env-file .env.docker exec -T app php artisan key:generate --show)
APP_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|APP_KEY=|APP_KEY=$APP_KEY|" .env.docker
docker-compose --env-file .env.docker restart app

# Wait for restart
sleep 5

# Run migrations
echo "📊 Running database migrations..."
docker-compose --env-file .env.docker exec -T app php artisan migrate --force

# Optimize application
echo "⚡ Optimizing application..."
docker-compose --env-file .env.docker exec -T app php artisan optimize

# Final health check
echo "🏥 Performing health check..."
sleep 5
if curl -f http://localhost/api/health > /dev/null 2>&1; then
    echo "✅ Health check passed!"
else
    echo "⚠️  Health check failed, but installation completed. Check logs with: docker-compose logs"
fi

echo ""
echo "🎉 Installation Complete!"
echo "=========================================="
echo ""
echo "📝 Next Steps:"
echo "1. Point your domain DNS A record to this server's IP"
echo "2. Wait a few minutes for Let's Encrypt SSL certificate"
echo "3. Access your application at: https://${DOMAIN}"
echo ""
echo "📊 Useful Commands:"
echo "  View logs:        cd $APP_DIR && docker-compose logs -f"
echo "  Check status:     cd $APP_DIR && docker-compose ps"
echo "  Restart services: cd $APP_DIR && docker-compose restart"
echo "  Stop services:    cd $APP_DIR && docker-compose down"
echo ""
echo "🔐 Important: Save these credentials securely!"
echo "  Database Password: ${DB_PASSWORD}"
echo "  Redis Password:    ${REDIS_PASSWORD}"
echo ""
