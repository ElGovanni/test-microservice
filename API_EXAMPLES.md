# API Examples

This file contains curl examples to test the microservices.

## Prerequisites

Make sure all services are running:
```bash
docker compose up --wait
```

Start the RabbitMQ consumer in a separate terminal:
```bash
docker compose exec user-service bin/console app:consume-user-events
```

## Auth Service Examples

### 1. Register a New User

```bash
curl -X POST http://localhost:8001/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

**Expected Response:**
```json
{
  "message": "User registered successfully",
  "userId": 1,
  "email": "john@example.com",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

Save the `token` value for subsequent requests!

### 2. Login

```bash
curl -X POST http://localhost:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

**Expected Response:**
```json
{
  "message": "Login successful",
  "userId": 1,
  "email": "john@example.com",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### 3. Request Password Reset

```bash
curl -X POST http://localhost:8001/api/password-reset/request \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com"
  }'
```

**Expected Response:**
```json
{
  "message": "Password reset token generated",
  "resetToken": "abc123...",
  "note": "In production, this would be sent via email"
}
```

### 4. Confirm Password Reset

```bash
curl -X POST http://localhost:8001/api/password-reset/confirm \
  -H "Content-Type: application/json" \
  -d '{
    "token": "abc123...",
    "newPassword": "NewSecurePass456!"
  }'
```

**Expected Response:**
```json
{
  "message": "Password reset successfully"
}
```

## User Service Examples

**Important:** Replace `YOUR_JWT_TOKEN` with the actual token from registration/login!

### 5. Get User Profile

```bash
curl -X GET http://localhost:8002/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "userId": 1,
  "email": "john@example.com",
  "bio": null,
  "avatar": null,
  "firstName": null,
  "lastName": null,
  "createdAt": "2024-01-15T10:30:00+00:00",
  "updatedAt": "2024-01-15T10:30:00+00:00"
}
```

### 6. Update User Profile

```bash
curl -X PUT http://localhost:8002/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "bio": "Software developer and open source enthusiast",
    "firstName": "John",
    "lastName": "Doe",
    "avatar": "https://example.com/avatar.jpg"
  }'
```

**Expected Response:**
```json
{
  "message": "Profile updated successfully",
  "userId": 1,
  "email": "john@example.com",
  "bio": "Software developer and open source enthusiast",
  "avatar": "https://example.com/avatar.jpg",
  "firstName": "John",
  "lastName": "Doe",
  "updatedAt": "2024-01-15T10:35:00+00:00"
}
```

### 7. Partially Update Profile (PATCH)

```bash
curl -X PATCH http://localhost:8002/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "bio": "Updated bio only"
  }'
```

**Expected Response:**
```json
{
  "message": "Profile updated successfully",
  "userId": 1,
  "email": "john@example.com",
  "bio": "Updated bio only",
  "avatar": "https://example.com/avatar.jpg",
  "firstName": "John",
  "lastName": "Doe",
  "updatedAt": "2024-01-15T10:40:00+00:00"
}
```

## Testing Error Cases

### Invalid JWT Token

```bash
curl -X GET http://localhost:8002/api/profile \
  -H "Authorization: Bearer invalid_token"
```

**Expected Response:**
```json
{
  "error": "Unauthorized - Invalid or missing token"
}
```

### Missing Authorization Header

```bash
curl -X GET http://localhost:8002/api/profile
```

**Expected Response:**
```json
{
  "error": "Unauthorized - Invalid or missing token"
}
```

### Invalid Credentials

```bash
curl -X POST http://localhost:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "wrong_password"
  }'
```

**Expected Response:**
```json
{
  "error": "Invalid credentials"
}
```

## Checking RabbitMQ

Access RabbitMQ Management UI:
```
http://localhost:15672
Username: guest
Password: guest
```

Navigate to:
- **Exchanges** → `user_events` to see the exchange
- **Queues** → `user_profile_creation` to see consumed messages

## Checking Databases

### Auth Service Database

```bash
docker compose exec postgres-auth psql -U auth_user -d auth_db -c "SELECT * FROM users;"
```

### User Service Database

```bash
docker compose exec postgres-user psql -U user_user -d user_db -c "SELECT * FROM user_profiles;"
```
