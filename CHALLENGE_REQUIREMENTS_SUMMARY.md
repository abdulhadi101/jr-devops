# Paxform DevOps & Backend Engineer Challenge Summary

## 📌 Challenge Overview

**Focus**: This is primarily a **DevOps assessment** (70% weight). Clean, scalable infrastructure code is as important as clean PHP code.

**Project**: Build and deploy a containerized "Secure Drop" service - a burn-after-reading secret sharing application.

---

## 🎯 Core Objective

Build a robust, containerized "Secure Drop" service using Laravel and deploy it using a production-ready Docker Compose & Traefik architecture.

**Feature**: Users can send a sensitive string (password/API key), receive a unique link, and share it. Once viewed, the data is permanently deleted.

---

## 🔧 Part 1: Backend Requirements

### Framework
- Use **Laravel 10 or 11**

### Architecture Pattern
- **Service-Repository Pattern** (mandatory)
- Strict separation of concerns:
  - Business logic → Service layer
  - Database queries → Repository layer

### API Endpoints

#### 1. Create Secret
- **Method**: `POST /api/v1/secrets`
- **Input**: 
  - `text` (required): The secret content
  - `ttl` (optional): Time-to-live in seconds
- **Output**: Returns unique ID/URL for sharing

#### 2. Retrieve Secret (Burn on Read)
- **Method**: `GET /api/v1/secrets/{id}`
- **Output**: Returns decrypted text
- **Critical**: Permanently deletes the record after retrieval

### Security Requirements
- ✅ Text content **must be encrypted** in database
- ✅ Use **secure, non-sequential IDs** (UUIDs or Nanoids)

### Documentation
- ✅ Auto-generate API docs (Scribe or Swagger)
- ✅ Accessible at `/docs` endpoint

---

## 🐳 Part 2: DevOps Requirements (CORE ASSESSMENT)

This is the **main focus** of the evaluation.

### A. Containerization

#### Dockerfile Requirements
1. **Multi-Stage Build**
   - Keep final image size small
   - Optimize for production

2. **Security**
   - Run application as **non-root user**
   - No root privileges in container

3. **PHP Extensions**
   - Must include: BCMath, PCNTL
   - Include all necessary Laravel extensions

#### Docker Compose Strategy (CRITICAL)
Must use **multi-file strategy** to separate concerns:

1. **docker-compose.yml**
   - Base configuration
   - Services, Networks, Volumes

2. **docker-compose.override.yml**
   - Local development overrides
   - Ports exposure
   - Bind mounts for live code

3. **docker-compose.prod.yml**
   - Production overrides
   - Restart policies
   - NO bind mounts (code baked in image)

### B. Infrastructure & Orchestration

#### 1. Traefik Reverse Proxy (MANDATORY)
- Application **must** sit behind Traefik
- Configure routing using **Docker Labels** on app container
- App accessible via hostname:
  - Local: `secure-drop.localhost` (or similar)
  - Production: Real domain name

#### 2. Health Checks
- Implement health check in docker-compose
- Ensure app is ready before receiving traffic
- Check database/service dependencies

### C. CI/CD Pipeline

Create pipeline configuration (GitHub Actions preferred) with these stages:

#### 1. Lint & Test
- Run PHP linter (Pint or phpcs)
- Run tests: `php artisan test`

#### 2. Security Scan
- Scan Docker image for vulnerabilities
- Use tools like **Trivy**

#### 3. Build & Push
- Build Docker image
- Push to registry (Docker Hub or GHCR)

#### 4. Deployment (BONUS)
- Script/step to trigger deployment on VPS
- Automated or semi-automated

---

## 🚀 Deployment Requirements

### VPS Deployment (MANDATORY)
- Deploy to public VPS:
  - AWS Free Tier
  - DigitalOcean
  - Any similar cloud provider

### Production Requirements
- ✅ Application running behind Traefik
- ✅ Provide **Live URL** in submission
- ✅ Accessible for testing

---

## 📝 Submission Instructions

### 1. Base Repository
- Fork: https://github.com/paxpass/jr-devops

### 2. Pull Request
- Submit solution as PR to `main` branch of base repo

### 3. PR Description MUST Include:

#### a. Live Deployment URL ⭐
- Working URL where evaluators can test the API

#### b. Architectural Decisions Summary
- Explain key decisions made
- Why certain technologies/approaches chosen

#### c. Local Setup Instructions ⭐
- **One-command setup** preferred
- Clear step-by-step if multi-step

#### d. Traefik Configuration Explanation ⭐
- How routing is configured
- How labels are used
- Local vs Production differences

#### e. CI/CD Pipeline Instructions
- How to trigger pipeline
- What each stage does
- How to interpret results

---

## 📊 Evaluation Criteria

### 1. Infrastructure Quality (HIGH WEIGHT)
- Is Docker setup **efficient**?
- Is it **secure**?
- Is the code **clean**?
- Production-ready thinking demonstrated?

### 2. Traefik Integration (HIGH WEIGHT)
- Is routing configured correctly via labels?
- Does it work in both local and production?
- HTTPS configured properly?

### 3. Pipeline Quality (MEDIUM WEIGHT)
- Is CI/CD pipeline **comprehensive**?
- Does it cover all required stages?
- Is it automated?

### 4. Code Quality (MEDIUM WEIGHT)
- Is Laravel code clean?
- Is it effectively structured?
- Service-Repository pattern followed?

---

## ✅ Required Deliverables Checklist

### Backend
- [ ] Laravel 10 or 11 application
- [ ] Service-Repository pattern implemented
- [ ] POST /api/v1/secrets endpoint
- [ ] GET /api/v1/secrets/{id} endpoint
- [ ] Database encryption implemented
- [ ] UUID/Nanoid for IDs
- [ ] API documentation at /docs
- [ ] Tests written and passing

### Docker Infrastructure
- [ ] Multi-stage Dockerfile
- [ ] Non-root user in container
- [ ] All required PHP extensions
- [ ] docker-compose.yml (base)
- [ ] docker-compose.override.yml (dev)
- [ ] docker-compose.prod.yml (production)
- [ ] Health checks implemented
- [ ] Traefik service configured
- [ ] Routing via Docker labels
- [ ] Application accessible via hostname

### CI/CD Pipeline
- [ ] Lint & test stage
- [ ] Security scan stage
- [ ] Build & push stage
- [ ] Deployment automation (bonus)
- [ ] Pipeline documentation

### Deployment
- [ ] Application deployed to VPS
- [ ] Running behind Traefik
- [ ] Live URL provided
- [ ] HTTPS working (bonus)

### Documentation
- [ ] Live deployment URL in PR
- [ ] Architecture decisions explained
- [ ] One-command local setup
- [ ] Traefik configuration explained
- [ ] CI/CD instructions provided

---

## 🎯 Success Criteria Summary

### Critical (Must Have)
1. ✅ Working application with both endpoints
2. ✅ Burn-after-reading functionality working
3. ✅ Multi-stage Dockerfile with non-root user
4. ✅ Three-file Docker Compose strategy
5. ✅ Traefik reverse proxy with label routing
6. ✅ Health checks implemented
7. ✅ Live deployment URL provided
8. ✅ CI/CD pipeline with all required stages

### Important (Should Have)
1. ✅ Service-Repository pattern properly implemented
2. ✅ Comprehensive tests
3. ✅ Clean, documented code
4. ✅ One-command local setup
5. ✅ Security scan in pipeline
6. ✅ HTTPS in production

### Nice to Have (Bonus Points)
1. ✅ Automated deployment in pipeline
2. ✅ Let's Encrypt SSL certificates
3. ✅ Monitoring/logging setup
4. ✅ Database backups
5. ✅ High test coverage
6. ✅ Performance optimization

---

## 🚨 Common Pitfalls to Avoid

### Infrastructure
- ❌ Running containers as root user
- ❌ Large Docker images (should be <200MB)
- ❌ No health checks
- ❌ Exposing ports directly instead of through Traefik
- ❌ Single docker-compose file instead of multi-file strategy
- ❌ Bind mounts in production

### Application
- ❌ Not following Service-Repository pattern
- ❌ Secrets not encrypted in database
- ❌ Using sequential IDs instead of UUIDs
- ❌ Secret not deleted after reading
- ❌ No API documentation

### Documentation
- ❌ No live URL provided
- ❌ Complex setup process (not one-command)
- ❌ Poor PR description
- ❌ Missing Traefik explanation
- ❌ No CI/CD instructions

### Security
- ❌ Storing secrets in code
- ❌ Debug mode on in production
- ❌ Database/Redis exposed to public
- ❌ No environment variable validation

---

## 📐 Architecture Quick Reference

### Expected Stack
```
User Request
    ↓
Traefik (Reverse Proxy)
    ↓
Nginx (in App Container)
    ↓
PHP-FPM (Laravel)
    ↓
Services → Repositories → Models
    ↓
Database (MySQL/PostgreSQL)
Redis (Cache/Sessions)
```

### File Structure Expected
```
project/
├── Dockerfile (multi-stage)
├── docker-compose.yml (base)
├── docker-compose.override.yml (dev)
├── docker-compose.prod.yml (prod)
├── .dockerignore
├── .github/
│   └── workflows/
│       └── deploy.yml
├── app/
│   ├── Services/
│   ├── Repositories/
│   └── ...
└── docker/
    ├── nginx/
    ├── php/
    └── supervisor/
```

---

## 🔑 Key Emphasis Points

### What They're REALLY Testing

1. **DevOps Skills (70%)**
   - Can you build production-ready infrastructure?
   - Do you understand Docker best practices?
   - Can you configure Traefik properly?
   - Do you know multi-environment strategies?

2. **System Design (20%)**
   - Clean architecture (Service-Repository)
   - Proper separation of concerns
   - Security best practices

3. **Backend Skills (10%)**
   - Laravel proficiency
   - API design
   - Code quality

### Focus Your Effort On:
1. **Perfect Dockerfile** (multi-stage, optimized, secure)
2. **Proper Docker Compose strategy** (3 files, clean separation)
3. **Working Traefik integration** (labels, routing, HTTPS)
4. **Comprehensive CI/CD** (all stages working)
5. **Clear documentation** (especially Traefik explanation)

---

## 📋 Pre-Submission Checklist

Before submitting your PR:

- [ ] Fork the base repository
- [ ] All backend endpoints working
- [ ] Burn-after-reading tested and working
- [ ] Secrets encrypted in database
- [ ] Multi-stage Dockerfile complete
- [ ] Three Docker Compose files present
- [ ] Traefik routing working
- [ ] Health checks responding
- [ ] Application deployed to VPS
- [ ] Live URL accessible
- [ ] CI/CD pipeline running
- [ ] All tests passing
- [ ] Security scan clean
- [ ] PR description complete with all required sections
- [ ] One-command local setup verified
- [ ] Documentation clear and comprehensive

---

## 🎓 What This Challenge Tests

### Technical Skills
- Docker containerization
- Multi-stage builds
- Docker Compose orchestration
- Traefik configuration
- CI/CD pipeline creation
- Laravel development
- Service-Repository pattern
- API design
- Database encryption
- Security best practices

### Soft Skills
- Following requirements precisely
- Documentation writing
- Problem-solving
- Attention to detail
- Production thinking
- Communication (PR description)

---

## 💡 Tips for Success

1. **Read requirements twice** - They're very specific
2. **Focus on DevOps** - It's 70% of the evaluation
3. **Test everything** - Before deploying
4. **Document clearly** - Especially Traefik setup
5. **Keep it simple** - Don't over-engineer the backend
6. **Production-ready** - Not just "working" but "production-ready"
7. **Security first** - Non-root user, encrypted data, no exposed secrets
8. **Clean code** - Even though DevOps is focus, code quality matters

---

## 📞 Quick Reference URLs

- **Base Repository**: https://github.com/paxpass/jr-devops
- **Submit**: PR to main branch
- **Include**: Live deployment URL in PR description

---

**END OF REQUIREMENTS SUMMARY**
