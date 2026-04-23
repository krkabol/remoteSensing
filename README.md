# Symfony Remote Sensing Application

A Symfony 8+ web application with Doctrine ORM, featuring GitHub OAuth authentication, REST API with multiple authentication methods, and separate public/internal sections.

## Features

- **Public Section**: Accessible to everyone without authentication
- **Internal Section**: Requires GitHub OAuth authentication
- **REST API**: Full-featured API with multiple authentication options
- **API Keys**: Long-lived tokens for external devices/sensors
- **JWT Tokens**: Short-term tokens for API access
- **GitHub Integration**: Single sign-on using GitHub credentials
- **Docker Support**: Complete Docker Compose setup for local development
- **PostgreSQL Database**: Doctrine ORM with PostgreSQL
- **Swagger Documentation**: Interactive API documentation at `/api/doc`

## Authentication Methods

The application supports three authentication methods:

### 1. Session Authentication (Browser)

Used for web browser access. After logging in via GitHub OAuth, a session cookie is automatically sent with each request.

**Use case:** Web application navigation
**Expiration:** Session lifetime (until logout or browser close)

### 2. JWT Token (Short-term API Access)

JSON Web Tokens for API authentication with configurable expiration (default: 1 hour).

**Use case:** Temporary API access, mobile apps, short-lived integrations
**Expiration:** 1 hour (configurable via `JWT_TOKEN_LIFETIME`)

**Getting a JWT Token:**

Via UI:
1. Log in to the application
2. Navigate to **API > JWT Token**
3. Copy the displayed token

Via API:
```bash
curl -X POST http://localhost:8080/api/v1/token \
  -H "Cookie: PHPSESSID=your_session_id"
```

**Using JWT Token:**
```bash
curl -X GET http://localhost:8080/api/v1/user/me \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..."
```

### 3. API Keys (Long-lived Device Authentication)

Static API keys for external devices, sensors, or IoT devices that need to send data.

**Use case:** External devices running in the wild, sensors, IoT devices
**Expiration:** Never (optional expiration can be set)

**Key Format:** `sk_live_` + 64 hexadecimal characters

**Creating an API Key:**

Via UI:
1. Log in to the application
2. Navigate to **API > API Keys (Devices)**
3. Enter a name (e.g., "Temperature Sensor #1")
4. Optionally set expiration in days
5. Click Create and **copy the key immediately** (it won't be shown again)

Via API:
```bash
curl -X POST http://localhost:8080/api/v1/api-keys \
  -H "Authorization: Bearer <jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Temperature Sensor #1"}'
```

**Using API Key:**
```bash
curl -X POST http://localhost:8080/api/v1/data \
  -H "Authorization: ApiKey sk_live_abc123..." \
  -H "Content-Type: application/json" \
  -d '{"sensor": "temp", "value": 25.5}'
```

**Python Example:**
```python
import requests

API_KEY = "sk_live_abc123..."
headers = {"Authorization": f"ApiKey {API_KEY}"}

response = requests.post(
    "http://localhost:8080/api/v1/data",
    headers=headers,
    json={"sensor": "temp", "value": 25.5}
)
```

## Requirements

- Docker and Docker Compose
- GitHub OAuth credentials (client ID and secret)

## Quick Start

### 1. Clone and Setup

```bash
# Install PHP dependencies
docker compose run --rm php composer install

# Copy environment file
cp .env .env.local
```

### 2. Configure GitHub Credentials

Edit `.env.local` and set your GitHub OAuth credentials:

```env
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_REDIRECT_URI=http://localhost:8080/auth/github/callback
```

**To get GitHub credentials:**
1. Visit [GitHub Developer Settings](https://github.com/settings/developers)
2. Create a new OAuth App
3. Copy the Client ID and generate a Client Secret
4. Set the callback URL to match `GITHUB_REDIRECT_URI`

### 3. Configure JWT (Optional)

```env
JWT_SECRET_KEY=change-this-to-a-random-secret-key-in-production
JWT_TOKEN_LIFETIME=3600  # Token lifetime in seconds (default: 1 hour)
```

### 4. Start Docker Containers

```bash
docker compose up -d
```

### 5. Run Database Migrations

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

### 6. Access the Application

- **Public Section**: http://localhost:8080/public
- **Internal Section**: http://localhost:8080/internal (requires login)
- **Login Page**: http://localhost:8080/login
- **API Documentation**: http://localhost:8080/api/doc

## Project Structure

```
├── config/
│   ├── bundles.php
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── security.yaml
│   │   ├── nelmio_api_doc.yaml
│   │   └── twig.yaml
│   └── routes.yaml
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── migrations/
│   └── Version*.php
├── public/
│   └── index.php
├── src/
│   ├── Controller/
│   │   ├── Api/
│   │   │   ├── ApiKeyController.php
│   │   │   ├── TokenController.php
│   │   │   └── UserApiController.php
│   │   ├── InternalController.php
│   │   ├── PublicController.php
│   │   └── SecurityController.php
│   ├── Entity/
│   │   ├── ApiKey.php
│   │   └── User.php
│   ├── Repository/
│   │   ├── ApiKeyRepository.php
│   │   └── UserRepository.php
│   ├── Security/
│   │   ├── ApiKeyAuthenticator.php
│   │   ├── ApiKeyService.php
│   │   ├── GithubAuthenticator.php
│   │   ├── JwtAuthenticator.php
│   │   └── JwtTokenService.php
│   └── Kernel.php
├── templates/
│   ├── base.html.twig
│   ├── internal/
│   │   ├── api_keys.html.twig
│   │   ├── api_token.html.twig
│   │   ├── index.html.twig
│   │   └── dashboard.html.twig
│   ├── public/
│   │   ├── index.html.twig
│   │   └── about.html.twig
│   └── security/
│       └── login.html.twig
├── docker-compose.yml
└── composer.json
```

## Architecture

### Security Configuration

The application uses a single main firewall with multiple authenticators:

| Authenticator | Priority | Header Format |
|--------------|----------|---------------|
| GithubAuthenticator | - | Session cookie |
| JwtAuthenticator | - | `Authorization: Bearer <token>` |
| ApiKeyAuthenticator | - | `Authorization: ApiKey <key>` |

### Authentication Flow

#### Session (GitHub OAuth)
1. User clicks "Login with GitHub"
2. Redirected to GitHub authorization page
3. User grants permission
4. GitHub redirects back with authorization code
5. Application exchanges code for access token
6. User profile is fetched and stored/updated
7. Session is created and user is redirected

#### JWT Token
1. User logs in via GitHub OAuth
2. Request JWT token from `/api/v1/token` or UI
3. Use token in `Authorization: Bearer <token>` header
4. Token expires after configured lifetime (default: 1 hour)

#### API Key
1. User logs in via GitHub OAuth
2. Create API key in UI or via API
3. **Copy the key immediately** (only shown once)
4. Use key in `Authorization: ApiKey sk_live_...` header
5. Key never expires (unless expiration was set)

### Entity Relationships

```
User (1) ----< (N) ApiKey
```

- Each User can have multiple API Keys
- Each API Key belongs to one User

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/v1/token` | Get JWT token | Session |
| GET | `/api/v1/token/refresh` | Refresh JWT token | Session |

### Users

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/user` | List all users | Any |
| GET | `/api/v1/user/me` | Get current user | Any |
| GET | `/api/v1/user/{id}` | Get user by ID | Any |

### API Keys

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/api-keys` | List API keys | Any |
| POST | `/api/v1/api-keys` | Create API key | Session/JWT |
| DELETE | `/api/v1/api-keys/{id}` | Revoke API key | Any |

## Development Commands

```bash
# Run console commands
docker compose exec php php bin/console <command>

# Clear cache
docker compose exec php php bin/console cache:clear

# Run migrations
docker compose exec php php bin/console doctrine:migrations:migrate

# Create new migration
docker compose exec php php bin/console make:migration

# View logs
docker compose logs -f php
docker compose logs -f nginx
docker compose logs -f database
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| APP_ENV | Application environment | dev |
| APP_SECRET | Application secret key | - |
| DATABASE_URL | PostgreSQL connection URL | postgresql://symfony:symfony@database:5432/symfony |
| GITHUB_CLIENT_ID | GitHub OAuth client ID | - |
| GITHUB_CLIENT_SECRET | GitHub OAuth client secret | - |
| GITHUB_REDIRECT_URI | OAuth callback URL | http://localhost:8080/auth/github/callback |
| JWT_SECRET_KEY | Secret key for JWT signing | auto-generated |
| JWT_TOKEN_LIFETIME | JWT token lifetime (seconds) | 3600 (1 hour) |

## Security Best Practices

### For API Keys
- **Store securely** on devices - never commit to version control
- **Use HTTPS** for all API requests
- **Rotate keys periodically** by revoking and creating new ones
- **Use descriptive names** to identify each key's purpose
- **Set expiration dates** for keys that shouldn't last forever
- **Monitor `lastUsedAt`** to detect unused or compromised keys

### For JWT Tokens
- **Short lifetime** - use 1 hour or less for sensitive operations
- **Secure storage** on client side (not in localStorage for web apps)
- **Use refresh tokens** pattern for longer sessions

## Troubleshooting

### API Authentication Issues

1. **401 Unauthorized with JWT:**
   - Check token hasn't expired
   - Verify `Authorization: Bearer <token>` format
   - Generate new token via `/api/v1/token`

2. **401 Unauthorized with API Key:**
   - Verify key format: `ApiKey sk_live_...`
   - Check key hasn't been revoked
   - Ensure key hasn't expired (if expiration was set)

3. **Swagger UI Authentication:**
   - Log in to the application first
   - Session cookie is automatically used
   - Or manually enter JWT/API key in Authorize button
