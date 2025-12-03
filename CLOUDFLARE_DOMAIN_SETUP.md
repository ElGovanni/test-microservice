# Cloudflare Domain Setup with MicroK8s on GCP VM

This guide explains how to use your Cloudflare domain with MicroK8s running on a GCP VM.

## Step-by-Step Setup

### 1. Configure GCP VM Firewall Rules

First, ensure your GCP VM accepts HTTP/HTTPS traffic:

```bash
# Create firewall rule for HTTP/HTTPS (if not exists)
gcloud compute firewall-rules create allow-http-https \
  --allow tcp:80,tcp:443 \
  --source-ranges 0.0.0.0/0 \
  --description "Allow HTTP and HTTPS traffic"

# Check your VM's external IP
gcloud compute instances list
```

Or via GCP Console:
- Go to VPC Network → Firewall
- Create rule allowing ports 80 and 443 from `0.0.0.0/0`

### 2. Get Your VM's External IP

```bash
# Get external IP of your VM
gcloud compute instances describe YOUR_VM_NAME \
  --zone=YOUR_ZONE \
  --format='get(networkInterfaces[0].accessConfigs[0].natIP)'
```

### 3. Configure Cloudflare DNS

**Option A: Without Cloudflare Proxy (Recommended for testing)**

In Cloudflare dashboard:
1. Go to DNS → Records
2. Add A record:
   - **Type**: A
   - **Name**: `mydomain.pl` (or `@` for root)
   - **IPv4 address**: Your GCP VM external IP
   - **Proxy status**: DNS only (gray cloud) ← Important for cert-manager
   - **TTL**: Auto

For subdomains (optional):
```
auth.mydomain.pl → YOUR_VM_IP (DNS only)
user.mydomain.pl → YOUR_VM_IP (DNS only)
```

**Option B: With Cloudflare Proxy (Orange cloud)**
- Better DDoS protection and CDN
- Use Cloudflare's SSL (Flexible or Full mode)
- No need for cert-manager

### 4. Configure MicroK8s Ingress

**If using single domain (path-based routing):**

```yaml
# values.yaml
unifiedIngress:
  enabled: true
  className: "nginx"
  host: "mydomain.pl"
  annotations:
    # Remove cert-manager if using Cloudflare proxy
    # cert-manager.io/cluster-issuer: "letsencrypt-prod"
  tls:
    enabled: true  # Set false if using Cloudflare Flexible SSL
    secretName: "microservices-tls"

authService:
  ingress:
    enabled: false
  env:
    SERVER_NAME: "mydomain.pl"

userService:
  ingress:
    enabled: false
  env:
    SERVER_NAME: "mydomain.pl"
```

**If using subdomains:**

```yaml
# values.yaml
authService:
  ingress:
    enabled: true
    hosts:
      - host: auth.mydomain.pl
        paths:
          - path: /
            pathType: Prefix
  env:
    SERVER_NAME: "auth.mydomain.pl"

userService:
  ingress:
    enabled: true
    hosts:
      - host: user.mydomain.pl
        paths:
          - path: /
            pathType: Prefix
  env:
    SERVER_NAME: "user.mydomain.pl"
```

### 5. SSL/TLS Options

**Option A: Cloudflare Proxy (Easiest)**

1. Set Cloudflare DNS to "Proxied" (orange cloud)
2. In Cloudflare → SSL/TLS:
   - Set mode to **"Flexible"** (Cloudflare ↔ Visitor encrypted, Cloudflare ↔ Origin unencrypted)
   - Or **"Full"** if you set up cert-manager
3. Update values.yaml:
```yaml
unifiedIngress:
  tls:
    enabled: false  # Cloudflare handles SSL
```

**Option B: Let's Encrypt with cert-manager (Better security)**

1. Set Cloudflare DNS to "DNS only" (gray cloud)
2. Install cert-manager on MicroK8s:
```bash
microk8s kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml
```

3. Create ClusterIssuer:
```bash
cat <<EOF | microk8s kubectl apply -f -
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

4. Update values.yaml:
```yaml
unifiedIngress:
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
  tls:
    enabled: true
    secretName: "microservices-tls"
```

### 6. Deploy and Test

```bash
# Deploy or upgrade
microk8s helm3 upgrade --install microservices ./helm/microservices \
  --namespace microservices \
  --values values.yaml

# Wait for certificate (if using cert-manager)
microk8s kubectl get certificate -n microservices

# Test
curl https://mydomain.pl/auth/api/health
```

## Architecture

```
┌─────────────┐
│ Cloudflare  │ (DNS: mydomain.pl → GCP VM IP)
│     DNS     │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Internet   │
└──────┬──────┘
       │ Port 80/443
       ▼
┌─────────────┐
│  GCP VM     │ External IP: x.x.x.x
│ (Firewall)  │ Allow 80/443
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  MicroK8s   │
│   Ingress   │ → Routes /auth, /user
└──────┬──────┘
       │
   ┌───┴───┐
   ▼       ▼
 Auth    User
Service Service
```

## Quick Troubleshooting

```bash
# Check if ports are open
curl -I http://YOUR_VM_IP

# Check ingress
microk8s kubectl get ingress -n microservices

# Check ingress controller logs
microk8s kubectl logs -n ingress -l app.kubernetes.io/name=ingress-nginx

# Test DNS resolution
nslookup mydomain.pl
dig mydomain.pl
```

## Recommended Setup

For GCP VM with MicroK8s, I recommend:
1. **DNS only** mode in Cloudflare (gray cloud)
2. **cert-manager** with Let's Encrypt
3. **Single domain** with path-based routing

This gives you full control and proper SSL certificates without Cloudflare proxy complexity.