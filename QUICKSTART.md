# Quick Start Guide

Follow these steps to get your microservices running:

## 1. Build the Images (Already Done ✓)

The Docker images have been built with the necessary PHP extensions including sockets.

## 2. Install Dependencies & Setup Databases

Run these commands in order:

```bash
# Stop any running containers
cd /Users/stefan/Documents/microservice
docker compose down --remove-orphans

# Start databases and RabbitMQ
docker compose up -d postgres-auth postgres-user rabbitmq

# Wait a few seconds for databases to initialize
sleep 10

# Install auth-service dependencies
docker compose run --rm auth-service composer install --no-interaction

# Install user-service dependencies
docker compose run --rm user-service composer install --no-interaction

# Create and run auth-service migrations
docker compose run --rm auth-service bin/console make:migration --no-interaction
docker compose run --rm auth-service bin/console doctrine:migrations:migrate --no-interaction

# Create and run user-service migrations
docker compose run --rm user-service bin/console make:migration --no-interaction
docker compose run --rm user-service bin/console doctrine:migrations:migrate --no-interaction

# Start all services
docker compose up -d

# View logs to confirm services are running
docker compose logs -f
```

## 3. Start the RabbitMQ Consumer

In a separate terminal, run:

```bash
docker compose exec user-service bin/console app:consume-user-events
```

This listens for `user.created` events and automatically creates user profiles.

## 4. Test the Services

### Register a User

```bash
curl -X POST http://localhost:8001/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "test123"
  }'
```

Save the JWT token from the response!

### Get User Profile

```bash
curl -X GET http://localhost:8002/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

### Update Profile

```bash
curl -X PUT http://localhost:8002/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "bio": "Learning microservices!",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

## Troubleshooting

### Services Won't Start

Check logs:
```bash
docker compose logs auth-service
docker compose logs user-service
```

### Database Connection Issues

Make sure databases are healthy:
```bash
docker compose ps
```

All should show "healthy" status.

### Check if migrations ran:

```bash
# Auth service
docker compose exec postgres-auth psql -U auth_user -d auth_db -c "\dt"

# User service
docker compose exec postgres-user psql -U user_user -d user_db -c "\dt"
```

You should see `users` table in auth_db and `user_profiles` in user_db.

## Services URLs

- **Auth Service**: http://localhost:8001
- **User Service**: http://localhost:8002
- **RabbitMQ Management**: http://localhost:15672 (guest/guest)
- **Postgres Auth**: localhost:5432 (auth_user/auth_pass)
- **Postgres User**: localhost:5433 (user_user/user_pass)

## Next Steps

See `API_EXAMPLES.md` for complete API documentation with curl examples for all endpoints!
