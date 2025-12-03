# Deployment Guide

This guide covers deploying the microservices application to Google Cloud Platform (GCP) using Kubernetes and Helm.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [GitHub Actions Setup](#github-actions-setup)
3. [GCP Cluster Setup](#gcp-cluster-setup)
4. [Helm Chart Configuration](#helm-chart-configuration)
5. [Deploying to GCP](#deploying-to-gcp)
6. [Post-Deployment Tasks](#post-deployment-tasks)
7. [Monitoring and Troubleshooting](#monitoring-and-troubleshooting)
8. [Updating the Application](#updating-the-application)

## Prerequisites

- GitHub account with repository access
- GCP account with billing enabled
- Local tools installed:
  - `kubectl` (Kubernetes CLI)
  - `gcloud` (Google Cloud SDK)
  - `helm` (Helm 3.x)
  - `git`

## GitHub Actions Setup

### 1. Enable GitHub Container Registry

The GitHub Actions workflow automatically builds and pushes Docker images to GitHub Container Registry (ghcr.io).

1. Navigate to your repository on GitHub
2. Go to **Settings** → **Packages**
3. Ensure Container registry is enabled

### 2. Configure Repository Settings

The workflow uses `GITHUB_TOKEN` which is automatically provided by GitHub Actions. No additional secrets needed for public repositories.

For **private repositories**, you'll need to:
1. Create a Personal Access Token (PAT) with `read:packages` scope
2. Store it as a repository secret named `GHCR_TOKEN`
3. Update the workflow to use this token

### 3. Trigger Image Builds

Images are built automatically on:
- Push to `main` or `develop` branches
- Creating version tags (e.g., `v1.0.0`)
- Pull requests to `main`

To trigger a build:
```bash
git tag v1.0.0
git push origin v1.0.0
```

### 4. Verify Image Availability

After the workflow completes, verify images are available:
```bash
# View packages at:
https://github.com/YOUR_USERNAME?tab=packages
```

Images will be available at:
- `ghcr.io/YOUR_USERNAME/microservice-auth-service:latest`
- `ghcr.io/YOUR_USERNAME/microservice-user-service:latest`

## GCP Cluster Setup

### 1. Authenticate with GCP

```bash
# Login to GCP
gcloud auth login

# Set your project
gcloud config set project YOUR_PROJECT_ID
```

### 2. Create GKE Cluster

```bash
# Create a regional cluster for high availability
gcloud container clusters create microservices-cluster \
  --region=us-central1 \
  --num-nodes=2 \
  --machine-type=e2-standard-2 \
  --enable-autoscaling \
  --min-nodes=2 \
  --max-nodes=10 \
  --enable-autorepair \
  --enable-autoupgrade \
  --disk-size=50 \
  --disk-type=pd-standard

# Or create a zonal cluster (cheaper)
gcloud container clusters create microservices-cluster \
  --zone=us-central1-a \
  --num-nodes=2 \
  --machine-type=e2-medium \
  --enable-autoscaling \
  --min-nodes=2 \
  --max-nodes=6
```

### 3. Get Cluster Credentials

```bash
# For regional cluster
gcloud container clusters get-credentials microservices-cluster --region=us-central1

# For zonal cluster
gcloud container clusters get-credentials microservices-cluster --zone=us-central1-a

# Verify connection
kubectl cluster-info
kubectl get nodes
```

### 4. Install NGINX Ingress Controller

```bash
# Add Helm repository
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update

# Install NGINX Ingress Controller
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --create-namespace \
  --namespace ingress-nginx \
  --set controller.service.type=LoadBalancer

# Wait for LoadBalancer IP to be assigned
kubectl get service -n ingress-nginx ingress-nginx-controller --watch
```

### 5. Install cert-manager (Optional - for HTTPS)

```bash
# Install cert-manager for automatic TLS certificates
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml

# Create Let's Encrypt ClusterIssuer
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

## Helm Chart Configuration

### 1. Update values.yaml

Edit `helm/microservices/values.yaml`:

```yaml
# Update GitHub username/organization
global:
  imageOwner: YOUR_GITHUB_USERNAME  # REQUIRED: Change this!

# Update domain names
authService:
  ingress:
    hosts:
      - host: auth.yourdomain.com  # Change to your domain
        paths:
          - path: /
            pathType: Prefix
    tls:
      - secretName: auth-service-tls
        hosts:
          - auth.yourdomain.com

userService:
  ingress:
    hosts:
      - host: user.yourdomain.com  # Change to your domain
        paths:
          - path: /
            pathType: Prefix
    tls:
      - secretName: user-service-tls
        hosts:
          - user.yourdomain.com

# Update secrets for production
secrets:
  # Generate with: openssl rand -base64 32
  jwtSecret: "CHANGE_ME_TO_SECURE_RANDOM_STRING"
  postgres:
    authPassword: "CHANGE_ME_SECURE_PASSWORD_1"
    userPassword: "CHANGE_ME_SECURE_PASSWORD_2"
  rabbitmq:
    username: "admin"
    password: "CHANGE_ME_SECURE_PASSWORD_3"
```

### 2. Configure DNS

After NGINX Ingress is deployed, get the LoadBalancer IP:
```bash
kubectl get service -n ingress-nginx ingress-nginx-controller
```

Create DNS A records pointing to this IP:
```
auth.yourdomain.com  → INGRESS_IP
user.yourdomain.com  → INGRESS_IP
```

### 3. Create Image Pull Secret (for private repos)

If using private GitHub Container Registry:

```bash
# Create namespace
kubectl create namespace microservices

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

## Deploying to GCP

### 1. Deploy with Helm

```bash
# From the project root directory
cd helm/microservices

# Install the chart
helm install microservices . \
  --create-namespace \
  --namespace microservices \
  --values values.yaml

# Or use upgrade --install to update existing deployment
helm upgrade --install microservices . \
  --namespace microservices \
  --values values.yaml
```

### 2. Verify Deployment

```bash
# Check all resources
kubectl get all -n microservices

# Check pods status
kubectl get pods -n microservices

# Check services
kubectl get services -n microservices

# Check ingress
kubectl get ingress -n microservices

# Check persistent volumes
kubectl get pvc -n microservices
```

## Post-Deployment Tasks

### 1. Run Database Migrations

```bash
# Auth Service migrations
kubectl exec -n microservices -it deployment/auth-service -- bin/console doctrine:migrations:migrate --no-interaction

# User Service migrations
kubectl exec -n microservices -it deployment/user-service -- bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Verify Services

```bash
# Test auth-service (replace with your domain)
curl https://auth.yourdomain.com/api/health

# Or use port-forward for testing
kubectl port-forward -n microservices svc/auth-service 8001:80
curl http://localhost:8001/api/health
```

### 3. Test User Registration Flow

```bash
# Register a new user
curl -X POST https://auth.yourdomain.com/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'

# Save the JWT token from response
export JWT_TOKEN="your_jwt_token_here"

# Wait a few seconds for profile creation via RabbitMQ

# Get user profile
curl -X GET https://user.yourdomain.com/api/profile \
  -H "Authorization: Bearer $JWT_TOKEN"
```

### 4. Monitor Consumer Logs

```bash
# Watch consumer logs
kubectl logs -n microservices -f deployment/user-service-consumer

# Should see messages like:
# Processing message for user ID: 123
# Created user profile successfully
```

## Monitoring and Troubleshooting

### View Logs

```bash
# Auth Service logs
kubectl logs -n microservices -f deployment/auth-service

# User Service logs
kubectl logs -n microservices -f deployment/user-service

# Consumer logs
kubectl logs -n microservices -f deployment/user-service-consumer

# PostgreSQL logs
kubectl logs -n microservices -f statefulset/postgres-auth
kubectl logs -n microservices -f statefulset/postgres-user

# RabbitMQ logs
kubectl logs -n microservices -f statefulset/rabbitmq
```

### Debug Pod Issues

```bash
# Describe pod for events
kubectl describe pod -n microservices POD_NAME

# Get pod status
kubectl get pod -n microservices POD_NAME -o yaml

# Shell into container
kubectl exec -n microservices -it POD_NAME -- sh
```

### Check Resource Usage

```bash
# View resource usage
kubectl top nodes
kubectl top pods -n microservices

# Check HPA status
kubectl get hpa -n microservices
```

### Access RabbitMQ Management UI

```bash
# Port forward to RabbitMQ management
kubectl port-forward -n microservices svc/rabbitmq 15672:15672

# Access at: http://localhost:15672
# Default credentials from values.yaml
```

### Common Issues

**Pods stuck in ImagePullBackOff:**
- Verify GitHub package is public or image pull secret is configured
- Check image name matches your GitHub username in values.yaml

**Database connection errors:**
- Verify PostgreSQL pods are running
- Check database passwords in secrets
- Verify DATABASE_URL format in deployment

**Ingress not working:**
- Verify DNS records point to LoadBalancer IP
- Check ingress controller is running: `kubectl get pods -n ingress-nginx`
- View ingress events: `kubectl describe ingress -n microservices`

## Updating the Application

### Update to New Version

```bash
# 1. Build and push new images via GitHub Actions
git tag v1.1.0
git push origin v1.1.0

# 2. Update values.yaml with new tag
authService:
  image:
    tag: v1.1.0
userService:
  image:
    tag: v1.1.0

# 3. Upgrade Helm release
helm upgrade microservices ./helm/microservices \
  --namespace microservices \
  --values helm/microservices/values.yaml

# 4. Run migrations if needed
kubectl exec -n microservices -it deployment/auth-service -- bin/console doctrine:migrations:migrate --no-interaction
kubectl exec -n microservices -it deployment/user-service -- bin/console doctrine:migrations:migrate --no-interaction

# 5. Verify rollout
kubectl rollout status -n microservices deployment/auth-service
kubectl rollout status -n microservices deployment/user-service
```

### Rollback Deployment

```bash
# View release history
helm history microservices -n microservices

# Rollback to previous version
helm rollback microservices -n microservices

# Or rollback to specific revision
helm rollback microservices 2 -n microservices

# Verify rollback
kubectl get pods -n microservices
```

## Cleanup

### Delete Application

```bash
# Uninstall Helm release
helm uninstall microservices -n microservices

# Delete namespace (including PVCs)
kubectl delete namespace microservices
```

### Delete GKE Cluster

```bash
# Delete cluster
gcloud container clusters delete microservices-cluster --region=us-central1

# Or for zonal cluster
gcloud container clusters delete microservices-cluster --zone=us-central1-a
```

## Cost Optimization Tips

1. **Use preemptible nodes** for non-production:
   ```bash
   gcloud container clusters create microservices-cluster \
     --preemptible \
     --machine-type=e2-small
   ```

2. **Scale down when not in use**:
   ```bash
   kubectl scale -n microservices deployment/auth-service --replicas=0
   kubectl scale -n microservices deployment/user-service --replicas=0
   ```

3. **Use Autopilot mode** for hands-off scaling:
   ```bash
   gcloud container clusters create-auto microservices-cluster --region=us-central1
   ```

4. **Monitor costs**: Set up billing alerts in GCP Console

## Production Checklist

- [ ] Change all default passwords in values.yaml
- [ ] Use strong JWT secret (32+ random characters)
- [ ] Configure proper domain names with DNS
- [ ] Set up TLS certificates (cert-manager + Let's Encrypt)
- [ ] Enable pod autoscaling based on load
- [ ] Set up proper monitoring (Prometheus/Grafana)
- [ ] Configure log aggregation (Cloud Logging)
- [ ] Set up database backups
- [ ] Configure resource limits appropriately
- [ ] Review security contexts and RBAC
- [ ] Set up CI/CD pipeline for automated deployments
- [ ] Configure health checks for all services
- [ ] Enable network policies for pod-to-pod security
- [ ] Set up alerting for critical errors

## Additional Resources

- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Helm Documentation](https://helm.sh/docs/)
- [GKE Documentation](https://cloud.google.com/kubernetes-engine/docs)
- [GitHub Container Registry](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry)
