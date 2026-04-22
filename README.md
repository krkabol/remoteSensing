# Symfony Remote Sensing Application

A Symfony 7/8+ web application with Doctrine ORM, featuring ORCID OAuth authentication and separate public/internal sections.

## Features

- **Public Section**: Accessible to everyone without authentication
- **Internal Section**: Requires ORCID OAuth authentication
- **ORCID Integration**: Single sign-on using ORCID credentials
- **Docker Support**: Complete Docker Compose setup for local development
- **PostgreSQL Database**: Doctrine ORM with PostgreSQL

## Requirements

- Docker and Docker Compose
- ORCID API credentials (client ID and secret)

## Quick Start

### 1. Clone and Setup

```bash
# Install PHP dependencies
docker-compose run --rm php composer install

# Copy environment file
cp .env .env.local
```

### 2. Configure ORCID Credentials

Edit `.env.local` and set your ORCID API credentials:

```env
ORCID_CLIENT_ID=your-client-id
ORCID_CLIENT_SECRET=your-client-secret
ORCID_REDIRECT_URI=http://localhost:8080/auth/orcid/callback
```

**To get ORCID credentials:**
1. Visit [ORCID Developer Tools](https://orcid.org/developer-tools)
2. Register your application
3. Copy the Client ID and Client Secret

### 3. Start Docker Containers

```bash
docker-compose up -d
```

### 4. Run Database Migrations

```bash
docker-compose exec php php bin/console doctrine:migrations:migrate
```

### 5. Access the Application

- **Public Section**: http://localhost:8080/public
- **Internal Section**: http://localhost:8080/internal (requires login)
- **Login Page**: http://localhost:8080/login

## Project Structure

```
├── config/
│   ├── bundles.php
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── security.yaml
│   │   └── twig.yaml
│   └── routes.yaml
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── migrations/
│   └── Version20240101000000.php
├── public/
│   └── index.php
├── src/
│   ├── Controller/
│   │   ├── InternalController.php
│   │   ├── PublicController.php
│   │   └── SecurityController.php
│   ├── Entity/
│   │   └── User.php
│   ├── Repository/
│   │   └── UserRepository.php
│   ├── Security/
│   │   └── OrcidAuthenticator.php
│   └── Kernel.php
├── templates/
│   ├── base.html.twig
│   ├── internal/
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

The application uses two firewall sections:

1. **Public Firewall** (`^/public`): No authentication required
2. **Internal Firewall** (`^/internal`): Requires ORCID authentication

### ORCID Authentication Flow

1. User clicks "Login with ORCID"
2. Redirected to ORCID authorization page
3. User grants permission
4. ORCID redirects back with authorization code
5. Application exchanges code for access token
6. User profile is fetched and stored/updated
7. User is redirected to internal section

### User Entity

The `User` entity stores:
- ORCID ID (unique identifier)
- Name information (given names, family name)
- Email address
- Additional ORCID profile data (JSON)
- Login timestamps

## Development Commands

```bash
# Run console commands
docker-compose exec php php bin/console <command>

# Clear cache
docker-compose exec php php bin/console cache:clear

# Run migrations
docker-compose exec php php bin/console doctrine:migrations:migrate

# Create new migration
docker-compose exec php php bin/console make:migration

# View logs
docker-compose logs -f php
docker-compose logs -f nginx
docker-compose logs -f database
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| APP_ENV | Application environment | dev |
| APP_SECRET | Application secret key | - |
| DATABASE_URL | PostgreSQL connection URL | postgresql://symfony:symfony@database:5432/symfony |
| ORCID_CLIENT_ID | ORCID API client ID | - |
| ORCID_CLIENT_SECRET | ORCID API client secret | - |
| ORCID_REDIRECT_URI | OAuth callback URL | http://localhost:8080/auth/orcid/callback |
| ORCID_AUTH_URL | ORCID authorization URL | https://orcid.org/oauth/authorize |
| ORCID_TOKEN_URL | ORCID token URL | https://orcid.org/oauth/token |
| ORCID_API_URL | ORCID API base URL | https://pub.orcid.org/v3.0 |

## Using Sandbox ORCID

For testing, you can use ORCID sandbox:

```env
ORCID_AUTH_URL=https://sandbox.orcid.org/oauth/authorize
ORCID_TOKEN_URL=https://pub.sandbox.orcid.org/oauth/token
ORCID_API_URL=https://pub.sandbox.orcid.org/v3.0
```

Register at: https://sandbox.orcid.org/developer-tools

## License

MIT License