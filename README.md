# Microservices Learning Project

A hands-on microservices example with two services: **auth-service** and **user-service**.

## Architecture

- **Auth Service** (port 8001): Handles authentication, registration, and password reset. Issues JWT tokens.
- **User Service** (port 8002): Manages user profiles (bio, avatar, etc.). Validates JWT tokens.
- **PostgreSQL**: Separate databases for each service (true microservices pattern)
- **RabbitMQ**: Message broker for async communication between services

## Quick Start

```bash
# Build and start all services
docker compose build --pull --no-cache
docker compose up --wait

# Services will be available at:
# - Auth Service: http://localhost:8001
# - User Service: http://localhost:8002
# - RabbitMQ Management: http://localhost:15672 (guest/guest)

# Stop services
docker compose down --remove-orphans
```

## Development Commands

```bash
# Install dependencies in auth-service
docker compose exec auth-service composer install

# Install dependencies in user-service
docker compose exec user-service composer install

# Run database migrations for auth-service
docker compose exec auth-service bin/console doctrine:migrations:migrate

# Run database migrations for user-service
docker compose exec user-service bin/console doctrine:migrations:migrate

# Access service shell
docker compose exec auth-service sh
docker compose exec user-service sh
```

## API Endpoints

### Auth Service (port 8001)

- `POST /api/register` - Register new user
- `POST /api/login` - Login and get JWT token
- `POST /api/password-reset/request` - Request password reset
- `POST /api/password-reset/confirm` - Confirm password reset

### User Service (port 8002)

- `GET /api/profile` - Get user profile (requires JWT)
- `PUT /api/profile` - Update user profile (requires JWT)

## Communication Pattern

When a user registers via auth-service:
1. Auth service creates user credentials in its database
2. Auth service publishes `user.created` event to RabbitMQ
3. User service consumes the event and creates a profile record
4. Both services maintain their own data independently
