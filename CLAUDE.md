# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a microservices learning project with two Symfony 7.3 services using FrankenPHP runtime, containerized with Docker. The project demonstrates true microservices patterns with separate databases, JWT authentication, and async messaging.

### Services

1. **auth-service** (port 8001): Handles user authentication, registration, and password reset. Issues JWT tokens.
2. **user-service** (port 8002): Manages user profiles. Validates JWT tokens independently. Listens for user creation events.

### Infrastructure

- **PostgreSQL**: Separate database instances for each service (postgres-auth, postgres-user)
- **RabbitMQ**: Message broker for async inter-service communication
- **Shared JWT Secret**: Both services use the same secret for token validation

## Key Commands

### Development Workflow

```bash
# Build and start all services
docker compose build --pull --no-cache
docker compose up --wait

# Stop services
docker compose down --remove-orphans

# Access service shells
docker compose exec auth-service sh
docker compose exec user-service sh

# Run Symfony console commands
docker compose exec auth-service bin/console <command>
docker compose exec user-service bin/console <command>

# Install dependencies
docker compose exec auth-service composer install
docker compose exec user-service composer install
```

### Database Management

```bash
# Create migration for auth-service
docker compose exec auth-service bin/console make:migration

# Run migrations for auth-service
docker compose exec auth-service bin/console doctrine:migrations:migrate

# Create migration for user-service
docker compose exec user-service bin/console make:migration

# Run migrations for user-service
docker compose exec user-service bin/console doctrine:migrations:migrate
```

### RabbitMQ Consumer

```bash
# Start consuming user.created events (user-service)
docker compose exec user-service bin/console app:consume-user-events
```

## Architecture & Patterns

### Microservices Communication

**Synchronous (REST)**:
- Client calls auth-service to register/login
- Client calls user-service with JWT token to manage profile

**Asynchronous (RabbitMQ)**:
- auth-service publishes `user.created` event to `user_events` exchange
- user-service consumes events from `user_profile_creation` queue
- Routing key: `user.created`

### Data Isolation

Each service owns its database:
- **auth-service/auth_db**: stores User credentials (id, email, password, reset tokens)
- **user-service/user_db**: stores UserProfile data (userId, bio, avatar, firstName, lastName)

The `userId` in UserProfile references the User.id from auth-service but is stored as a simple integer (no foreign key across services).

### JWT Token Flow

1. User registers/logs in via auth-service
2. auth-service generates JWT with userId and email
3. Client includes JWT in Authorization header for user-service requests
4. user-service validates JWT independently using shared secret
5. user-service extracts userId from token to fetch profile

## API Endpoints

### Auth Service (port 8001)

- `POST /api/register` - Register new user, returns JWT token
- `POST /api/login` - Login with email/password, returns JWT token
- `POST /api/password-reset/request` - Request password reset token
- `POST /api/password-reset/confirm` - Confirm password reset with token

### User Service (port 8002)

- `GET /api/profile` - Get user profile (requires JWT in Authorization header)
- `PUT /api/profile` - Update user profile (requires JWT)
- `PATCH /api/profile` - Partially update profile (requires JWT)

## Service Structure

### Common Patterns

Both services follow similar structure:
```
service-name/
├── src/
│   ├── Command/         # Console commands
│   ├── Controller/      # HTTP endpoints
│   ├── Entity/          # Doctrine entities
│   ├── Repository/      # Data access layer
│   └── Service/         # Business logic
├── config/
│   ├── packages/        # Bundle configurations
│   ├── services.yaml    # DI container
│   └── routes.yaml      # URL routing
└── frankenphp/          # Web server config
```

### Key Classes

**Auth Service**:
- `Entity/User`: User credentials entity
- `Repository/UserRepository`: User data access
- `Service/JwtService`: JWT token generation and validation
- `Service/RabbitMqPublisher`: Publish events to RabbitMQ
- `Controller/AuthController`: Registration, login, password reset endpoints

**User Service**:
- `Entity/UserProfile`: User profile entity
- `Repository/UserProfileRepository`: Profile data access
- `Service/JwtService`: JWT token validation
- `Service/RabbitMqConsumer`: Consume user.created events
- `Command/ConsumeUserEventsCommand`: Start event consumer
- `Controller/ProfileController`: Profile get/update endpoints

## Development Considerations

### Adding New Endpoints

1. Create controller method with Route attribute
2. Inject required repositories/services via constructor
3. For user-service endpoints, validate JWT token first

### Creating Database Changes

1. Modify entity class
2. Run `make:migration` to generate migration
3. Review generated migration file
4. Run `doctrine:migrations:migrate` to apply

### Environment Variables

Shared across services (set in compose.yaml):
- `JWT_SECRET`: Shared secret for JWT signing/validation
- `RABBITMQ_URL`: RabbitMQ connection string
- `DATABASE_URL`: Service-specific database connection

### Testing the Flow

1. Register user: `POST http://localhost:8001/api/register`
2. Copy JWT token from response
3. Wait for profile creation (consumer must be running)
4. Get profile: `GET http://localhost:8002/api/profile` with `Authorization: Bearer <token>`
5. Update profile: `PUT http://localhost:8002/api/profile` with JWT and profile data

## Important Files

- `compose.yaml`: Multi-service Docker Compose configuration
- `README.md`: Quick start guide and API documentation
- `auth-service/src/Service/RabbitMqPublisher.php`: Event publishing logic
- `user-service/src/Service/RabbitMqConsumer.php`: Event consumption logic
- `user-service/src/Command/ConsumeUserEventsCommand.php`: Consumer command
