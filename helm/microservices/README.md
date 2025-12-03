# Microservices Helm Chart

This Helm chart deploys a microservices architecture with authentication and user management services to Kubernetes.

## Architecture

The chart deploys:
- **Auth Service**: Handles user authentication, registration, and JWT token generation
- **User Service**: Manages user profiles with JWT validation
- **User Consumer**: Consumes RabbitMQ events for profile creation
- **PostgreSQL**: Two separate database instances (auth-db, user-db)
- **RabbitMQ**: Message broker for async communication

## Prerequisites

- Kubernetes 1.24+
- Helm 3.8+
- Docker images built and pushed to GitHub Container Registry

## Installation

### Quick Start

```bash
# Update values.yaml with your configuration
# At minimum, change:
# - global.imageOwner (your GitHub username)
# - secrets.* (all passwords and JWT secret)
# - ingress hosts (your domain names)

# Install the chart
helm install microservices . \
  --create-namespace \
  --namespace microservices \
  --values values.yaml
```

### Using Custom Values

Create a custom values file:

```bash
# custom-values.yaml
global:
  imageOwner: myusername

secrets:
  jwtSecret: "my-secure-jwt-secret"
  postgres:
    authPassword: "auth-secure-pass"
    userPassword: "user-secure-pass"

authService:
  ingress:
    hosts:
      - host: auth.mycompany.com
        paths:
          - path: /
            pathType: Prefix
```

Install with custom values:

```bash
helm install microservices . \
  -f values.yaml \
  -f custom-values.yaml \
  --namespace microservices
```

## Configuration

### Required Configuration

These values **MUST** be changed before deployment:

| Parameter | Description | Example |
|-----------|-------------|---------|
| `global.imageOwner` | Your GitHub username/org | `myusername` |
| `secrets.jwtSecret` | JWT signing secret | Generate with `openssl rand -base64 32` |
| `secrets.postgres.authPassword` | Auth DB password | Strong random password |
| `secrets.postgres.userPassword` | User DB password | Strong random password |
| `secrets.rabbitmq.password` | RabbitMQ password | Strong random password |

### Optional Configuration

| Parameter | Description | Default |
|-----------|-------------|---------|
| `authService.replicaCount` | Number of auth service replicas | `2` |
| `userService.replicaCount` | Number of user service replicas | `2` |
| `authService.ingress.enabled` | Enable ingress for auth service | `true` |
| `userService.ingress.enabled` | Enable ingress for user service | `true` |
| `postgresAuth.persistence.size` | Auth database storage size | `10Gi` |
| `postgresUser.persistence.size` | User database storage size | `10Gi` |
| `rabbitmq.persistence.size` | RabbitMQ storage size | `8Gi` |

### Image Configuration

```yaml
global:
  imageRegistry: ghcr.io
  imageOwner: YOUR_GITHUB_USERNAME  # Change this!
  imagePullPolicy: IfNotPresent

authService:
  image:
    repository: microservice-auth-service
    tag: latest  # or specific version like v1.0.0

userService:
  image:
    repository: microservice-user-service
    tag: latest
```

### Resource Limits

```yaml
authService:
  resources:
    requests:
      cpu: 100m
      memory: 128Mi
    limits:
      cpu: 500m
      memory: 512Mi
```

### Autoscaling

```yaml
authService:
  autoscaling:
    enabled: true
    minReplicas: 2
    maxReplicas: 10
    targetCPUUtilizationPercentage: 70
    targetMemoryUtilizationPercentage: 80
```

### Ingress Configuration

```yaml
authService:
  ingress:
    enabled: true
    className: "nginx"
    annotations:
      cert-manager.io/cluster-issuer: "letsencrypt-prod"
    hosts:
      - host: auth.example.com
        paths:
          - path: /
            pathType: Prefix
    tls:
      - secretName: auth-service-tls
        hosts:
          - auth.example.com
```

## Post-Installation

### 1. Run Database Migrations

```bash
# Auth Service
kubectl exec -n microservices -it deployment/auth-service -- \
  bin/console doctrine:migrations:migrate --no-interaction

# User Service
kubectl exec -n microservices -it deployment/user-service -- \
  bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Verify Deployment

```bash
# Check all resources
kubectl get all -n microservices

# Check ingress
kubectl get ingress -n microservices

# View logs
kubectl logs -n microservices -f deployment/auth-service
kubectl logs -n microservices -f deployment/user-service
kubectl logs -n microservices -f deployment/user-service-consumer
```

### 3. Test the Services

```bash
# Register a user
curl -X POST https://auth.example.com/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Get profile (use JWT from registration)
curl -X GET https://user.example.com/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Upgrading

```bash
# Fetch new images by updating tags in values.yaml
# Then upgrade the release
helm upgrade microservices . \
  --namespace microservices \
  --values values.yaml

# Run migrations if needed
kubectl exec -n microservices -it deployment/auth-service -- \
  bin/console doctrine:migrations:migrate --no-interaction
```

## Uninstalling

```bash
# Uninstall the release
helm uninstall microservices -n microservices

# Delete the namespace and all resources
kubectl delete namespace microservices
```

## Troubleshooting

### Pods Not Starting

```bash
# Check pod events
kubectl describe pod -n microservices POD_NAME

# Check logs
kubectl logs -n microservices POD_NAME
```

### Image Pull Errors

For private repositories:
```bash
# Create image pull secret
kubectl create secret docker-registry ghcr-secret \
  --namespace=microservices \
  --docker-server=ghcr.io \
  --docker-username=YOUR_GITHUB_USERNAME \
  --docker-password=YOUR_GITHUB_PAT

# Update values.yaml
global:
  imagePullSecrets:
    - name: ghcr-secret
```

### Database Connection Issues

```bash
# Check PostgreSQL pods
kubectl get pods -n microservices -l app.kubernetes.io/component=database

# Check database logs
kubectl logs -n microservices statefulset/postgres-auth
kubectl logs -n microservices statefulset/postgres-user

# Test connection from service pod
kubectl exec -n microservices -it deployment/auth-service -- \
  bin/console doctrine:schema:validate
```

### RabbitMQ Issues

```bash
# Check RabbitMQ status
kubectl logs -n microservices statefulset/rabbitmq

# Access management UI
kubectl port-forward -n microservices svc/rabbitmq 15672:15672
# Visit: http://localhost:15672
```

## Values Documentation

See [values.yaml](values.yaml) for complete configuration options with inline documentation.

## Support

For detailed deployment instructions, see [DEPLOYMENT.md](../../DEPLOYMENT.md) in the project root.
