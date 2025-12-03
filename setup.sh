#!/bin/bash
set -e

echo "===================================="
echo "Microservices Setup Script"
echo "===================================="
echo ""

echo "Step 1: Stop any running containers..."
docker compose down --remove-orphans

echo ""
echo "Step 2: Start database and RabbitMQ services..."
docker compose up -d postgres-auth postgres-user rabbitmq

echo ""
echo "Step 3: Wait for databases to be ready..."
sleep 10

echo ""
echo "Step 4: Install auth-service dependencies..."
docker compose run --rm auth-service composer install --no-interaction --optimize-autoloader

echo ""
echo "Step 5: Install user-service dependencies..."
docker compose run --rm user-service composer install --no-interaction --optimize-autoloader

echo ""
echo "Step 6: Register Doctrine bundles in auth-service..."
echo "Updating config/bundles.php for auth-service..."

echo ""
echo "Step 7: Register Doctrine bundles in user-service..."
echo "Updating config/bundles.php for user-service..."

echo ""
echo "Step 8: Create database migrations for auth-service..."
docker compose run --rm auth-service bin/console make:migration --no-interaction || true

echo ""
echo "Step 9: Run auth-service migrations..."
docker compose run --rm auth-service bin/console doctrine:migrations:migrate --no-interaction

echo ""
echo "Step 10: Create database migrations for user-service..."
docker compose run --rm user-service bin/console make:migration --no-interaction || true

echo ""
echo "Step 11: Run user-service migrations..."
docker compose run --rm user-service bin/console doctrine:migrations:migrate --no-interaction

echo ""
echo "Step 12: Start all services..."
docker compose up -d

echo ""
echo "===================================="
echo "Setup Complete!"
echo "===================================="
echo ""
echo "Services are running:"
echo "- Auth Service:    http://localhost:8001"
echo "- User Service:    http://localhost:8002"
echo "- RabbitMQ UI:     http://localhost:15672 (guest/guest)"
echo ""
echo "To start the RabbitMQ consumer:"
echo "  docker compose exec user-service bin/console app:consume-user-events"
echo ""
echo "To view logs:"
echo "  docker compose logs -f"
echo ""
