# Kubernetes Quick Start Guide

This is a quick reference for deploying the microservices application to GCP with Kubernetes.

## TL;DR - Fast Track Deployment

```bash
# 1. Update Helm values
cd helm/microservices
# Edit values.yaml - change imageOwner and all secrets

# 2. Create GKE cluster
gcloud container clusters create microservices-cluster \
  --zone=us-central1-a \
  --num-nodes=2 \
  --machine-type=e2-medium

# 3. Get credentials
gcloud container clusters get-credentials microservices-cluster --zone=us-central1-a

# 4. Install NGINX Ingress
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --create-namespace --namespace ingress-nginx

# 5. Deploy application
helm install microservices . \
  --create-namespace \
  --namespace microservices \
  --values values.yaml

# 6. Run migrations
kubectl exec -n microservices -it deployment/auth-service -- bin/console doctrine:migrations:migrate --no-interaction
kubectl exec -n microservices -it deployment/user-service -- bin/console doctrine:migrations:migrate --no-interaction

# 7. Test with port-forward
kubectl port-forward -n microservices svc/auth-service 8001:80
kubectl port-forward -n microservices svc/user-service 8002:80
```

## MicroK8s Alternative

For local Kubernetes development, you can use MicroK8s instead of GKE:

```bash
# 1. Install and start MicroK8s
sudo snap install microk8s --classic
microk8s start

# 2. Enable required addons
microk8s enable dns storage helm3 ingress

# 3. Set up kubectl alias (or use microk8s kubectl)
alias kubectl='microk8s kubectl'
alias helm='microk8s helm3'

# 4. Update Helm values (same as GKE - change imageOwner and secrets)
cd helm/microservices
# Edit values.yaml

# 5. Deploy application (no separate ingress install needed)
microk8s helm3 install microservices . \
  --create-namespace \
  --namespace microservices \
  --values values.yaml

# 6. Run migrations
microk8s kubectl exec -n microservices -it deployment/auth-service -- \
  bin/console doctrine:migrations:migrate --no-interaction
microk8s kubectl exec -n microservices -it deployment/user-service -- \
  bin/console doctrine:migrations:migrate --no-interaction

# 7. Test with port-forward (same as GKE)
microk8s kubectl port-forward -n microservices svc/auth-service 8001:80
microk8s kubectl port-forward -n microservices svc/user-service 8002:80
```

**Key MicroK8s Differences:**
- No cloud load balancer (ingress uses NodePort/host IP)
- Built-in ingress addon (no need to install NGINX separately)
- Local hostpath storage by default
- For local images: `microk8s enable registry` and push to `localhost:32000`

**Using local images with MicroK8s:**
```bash
# Enable registry
microk8s enable registry

# Tag and push images
docker tag microservice-auth-service localhost:32000/auth-service:latest
docker push localhost:32000/auth-service:latest

# Update values.yaml to use localhost:32000
```

## Before You Start

### 1. Configure GitHub Actions

Push to `main` branch or create a tag to trigger Docker image builds:
```bash
git tag v1.0.0
git push origin v1.0.0
```

Images will be available at:
- `ghcr.io/YOUR_USERNAME/microservice-auth-service:latest`
- `ghcr.io/YOUR_USERNAME/microservice-user-service:latest`

### 2. Update Helm values.yaml

**REQUIRED CHANGES:**
```yaml
global:
  imageOwner: YOUR_GITHUB_USERNAME  # ← Change this!

secrets:
  jwtSecret: "generate-with-openssl-rand-base64-32"
  postgres:
    authPassword: "strong-password-1"
    userPassword: "strong-password-2"
  rabbitmq:
    username: "admin"
    password: "strong-password-3"
```

## Deployment Commands

### Install
```bash
helm install microservices ./helm/microservices \
  --create-namespace \
  --namespace microservices
```

### Upgrade
```bash
helm upgrade microservices ./helm/microservices \
  --namespace microservices
```

### Uninstall
```bash
helm uninstall microservices -n microservices
kubectl delete namespace microservices
```

## Useful Commands

### Check Status
```bash
# All resources
kubectl get all -n microservices

# Pods only
kubectl get pods -n microservices

# Logs
kubectl logs -n microservices -f deployment/auth-service
kubectl logs -n microservices -f deployment/user-service
kubectl logs -n microservices -f deployment/user-service-consumer
```

### Run Migrations
```bash
# Auth service
kubectl exec -n microservices -it deployment/auth-service -- \
  bin/console doctrine:migrations:migrate --no-interaction

# User service
kubectl exec -n microservices -it deployment/user-service -- \
  bin/console doctrine:migrations:migrate --no-interaction
```

### Port Forwarding (for testing)
```bash
# Auth service
kubectl port-forward -n microservices svc/auth-service 8001:80

# User service
kubectl port-forward -n microservices svc/user-service 8002:80

# RabbitMQ management UI
kubectl port-forward -n microservices svc/rabbitmq 15672:15672

# PostgreSQL (auth)
kubectl port-forward -n microservices svc/postgres-auth 5432:5432
```

### Test API
```bash
# Register user
curl -X POST http://localhost:8001/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Login
curl -X POST http://localhost:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Get profile (replace JWT_TOKEN)
curl -X GET http://localhost:8002/api/profile \
  -H "Authorization: Bearer JWT_TOKEN"
```

## Troubleshooting

### Pod not starting
```bash
kubectl describe pod -n microservices POD_NAME
kubectl logs -n microservices POD_NAME
```

### Image pull errors
For private repos, create image pull secret:
```bash
kubectl create secret docker-registry ghcr-secret \
  --namespace=microservices \
  --docker-server=ghcr.io \
  --docker-username=YOUR_USERNAME \
  --docker-password=YOUR_PAT
```

Then update values.yaml:
```yaml
global:
  imagePullSecrets:
    - name: ghcr-secret
```

### Database connection issues
```bash
# Check DB pods
kubectl get pods -n microservices -l app.kubernetes.io/component=database

# Check DB logs
kubectl logs -n microservices statefulset/postgres-auth
```

## Production Setup

For production deployment with HTTPS:

1. **Install cert-manager:**
```bash
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml
```

2. **Create ClusterIssuer:**
```bash
cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: your-email@example.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
```

3. **Configure DNS:**
Get LoadBalancer IP:
```bash
kubectl get svc -n ingress-nginx ingress-nginx-controller
```

Create A records:
```
auth.yourdomain.com → LOADBALANCER_IP
user.yourdomain.com → LOADBALANCER_IP
```

4. **Update values.yaml with your domain:**
```yaml
authService:
  ingress:
    hosts:
      - host: auth.yourdomain.com
        paths:
          - path: /
            pathType: Prefix
```

## Single Domain Deployment (Path-Based Routing)

Deploy both services under one domain with path prefixes:
- `https://mydomain.pl/auth/*` → auth-service
- `https://mydomain.pl/user/*` → user-service

### Configuration

**1. Update values.yaml:**
```yaml
# Enable unified ingress
unifiedIngress:
  enabled: true
  className: "nginx"
  host: "mydomain.pl"
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
  tls:
    enabled: true
    secretName: "microservices-tls"
  rabbitmq:
    enabled: false  # Set true to expose RabbitMQ at /rabbitmq

# Disable individual service ingresses
authService:
  ingress:
    enabled: false

userService:
  ingress:
    enabled: false
```

**2. Configure DNS:**
Create a single A record:
```
mydomain.pl → LOADBALANCER_IP
```

### API Endpoints (Single Domain)

| Endpoint | URL |
|----------|-----|
| Register | `POST https://mydomain.pl/auth/api/register` |
| Login | `POST https://mydomain.pl/auth/api/login` |
| Password Reset Request | `POST https://mydomain.pl/auth/api/password-reset/request` |
| Password Reset Confirm | `POST https://mydomain.pl/auth/api/password-reset/confirm` |
| Get Profile | `GET https://mydomain.pl/user/api/profile` |
| Update Profile | `PUT https://mydomain.pl/user/api/profile` |

### Test API (Single Domain)
```bash
# Register user
curl -X POST https://mydomain.pl/auth/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Login
curl -X POST https://mydomain.pl/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Get profile (replace JWT_TOKEN)
curl -X GET https://mydomain.pl/user/api/profile \
  -H "Authorization: Bearer JWT_TOKEN"
```

### How It Works

The unified ingress uses NGINX path rewriting:
- Request to `/auth/api/register` is rewritten to `/api/register` before hitting auth-service
- Request to `/user/api/profile` is rewritten to `/api/profile` before hitting user-service

This means **no changes to Symfony applications** are needed.

## Architecture Overview

```
┌─────────────────┐
│   Ingress       │
│   (NGINX)       │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼────┐ ┌──▼─────┐
│ Auth   │ │ User   │
│Service │ │Service │
└───┬────┘ └──┬─────┘
    │         │
┌───▼────┐ ┌──▼─────┐
│Postgres│ │Postgres│
│ Auth   │ │ User   │
└────────┘ └────┬───┘
                │
         ┌──────▼─────┐
         │  RabbitMQ  │
         └────────────┘
         │
    ┌────▼─────┐
    │ Consumer │
    │ (User)   │
    └──────────┘
```

## Next Steps

- See [DEPLOYMENT.md](DEPLOYMENT.md) for comprehensive deployment guide
- See [helm/microservices/README.md](helm/microservices/README.md) for Helm chart documentation
- See [README.md](README.md) for API documentation

## Clean Up

Delete everything:
```bash
# Delete application
helm uninstall microservices -n microservices
kubectl delete namespace microservices

# Delete ingress controller
helm uninstall ingress-nginx -n ingress-nginx
kubectl delete namespace ingress-nginx

# Delete GKE cluster
gcloud container clusters delete microservices-cluster --zone=us-central1-a
```
