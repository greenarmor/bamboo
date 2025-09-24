# JWT Authentication CLI Toolkit

Bamboo ships a JWT authentication starter that is provisioned by the `auth.jwt.setup` console command. This guide walks through what the command does, how to verify the generated scaffolding, and which knobs you can tune after the initial bootstrap.

## Prerequisites

- A Bamboo application installed locally.
- PHP CLI access to run `php bin/bamboo` commands from the project root.
- File system write permissions so the command can manage `.env`, `etc/`, and `var/` artifacts.

## Running the setup command

Execute the setup command from the root of your project:

```bash
php bin/bamboo auth.jwt.setup
```

On success the command exits with code `0` and prints a checklist of the work it performed, followed by `JWT authentication scaffolding is ready to use.`【F:src/Console/Command/AuthJwtSetup.php†L31-L87】

> **Tip:** The command is idempotent. You can rerun it at any time to ensure configuration drift has not occurred. Existing secrets, module registrations, and populated user stores are left intact.【F:src/Console/Command/AuthJwtSetup.php†L34-L87】【F:tests/Console/AuthJwtSetupCommandTest.php†L40-L76】

## Artifacts created by the toolkit

When you run `auth.jwt.setup`, Bamboo prepares the following assets:

| Artifact | Purpose | Location |
| --- | --- | --- |
| JWT secret | Adds a random 64-character secret to `.env` (creating the file from `.env.example` if needed). Used for signing issued tokens. | `.env` via `AUTH_JWT_SECRET` | 
| Auth config | Publishes the JWT configuration stub that drives the module. | `etc/auth.php` | 
| User store | Creates a JSON user repository (default) and seeds an `admin` user (`password`). Alternate drivers (MySQL, PostgreSQL, Firebase, NoSQL) are scaffolded via configuration. | JSON: `var/auth/users.json` (configurable) |
| Module registration | Ensures the `JwtAuthModule` is registered so routes and middleware load. | `etc/modules.php` |

The setup command writes the env secret, copies the configuration stub, seeds the user store when empty, and registers the module if it is not already present.【F:src/Console/Command/AuthJwtSetup.php†L52-L233】 The JSON user store is only seeded when the file is missing or blank, preventing accidental overwrites of real accounts, while alternate drivers are left untouched so you can manage migrations externally.【F:src/Console/Command/AuthJwtSetup.php†L108-L233】

Set `AUTH_JWT_STORAGE_DRIVER` to `mysql`, `pgsql`, `firebase`, or `nosql` before running the command to tailor `etc/auth.php` to your preferred backend. The generated configuration includes connection placeholders and schema guidance for each driver, while continuing to default to the JSON file store when unset.【F:stubs/auth/jwt-auth.php†L20-L181】【F:etc/auth.php†L20-L181】

## Default routes and behaviour

Registering the `JwtAuthModule` wires three HTTP endpoints into your application:

| Method & path | Handler | Notes |
| --- | --- | --- |
| `POST /api/auth/register` | `AuthController::register` | Optional—honours the `auth.jwt.registration.enabled` flag. Returns a token and sanitized user payload after creating the account. |
| `POST /api/auth/login` | `AuthController::login` | Validates credentials and issues a signed JWT containing the user name and roles. |
| `GET /api/auth/profile` | `AuthController::profile` | Protected by the `auth.jwt` middleware alias. Returns the authenticated user and decoded claims. |

These routes are registered during module boot, and the middleware alias `auth.jwt` becomes available for protecting additional routes in your project.【F:src/Auth/Jwt/JwtAuthModule.php†L31-L75】 Requests hitting the controller use the JSON user repository and token service that were bound in the service container.【F:src/Auth/Jwt/AuthController.php†L11-L120】

## Verifying the setup

After running the setup command:

1. Start the HTTP server (`php bin/bamboo http.serve`).
2. Make a login request with the seeded admin user:
   ```bash
   curl -X POST http://localhost:8080/api/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"username":"admin","password":"password"}'
   ```
3. Copy the `token` value from the response and call the profile endpoint:
   ```bash
   curl http://localhost:8080/api/auth/profile \
     -H 'Authorization: Bearer <token>'
   ```
4. Confirm the response contains the sanitized user payload and `claims` extracted from the JWT.【F:src/Auth/Jwt/AuthController.php†L57-L118】

If you enabled registration, you can exercise the register endpoint with the same pattern. Duplicate usernames receive a `409` response, and malformed payloads are rejected with descriptive error codes.【F:src/Auth/Jwt/AuthController.php†L25-L56】

## Configuration knobs

All JWT settings are stored in `etc/auth.php`, which reads defaults from environment variables. Key options include:

- `AUTH_JWT_SECRET` – signing key used by `JwtTokenService`; rotate this in production deployments.【F:stubs/auth/jwt-auth.php†L5-L38】【F:src/Auth/Jwt/JwtAuthModule.php†L19-L40】
- `AUTH_JWT_TTL` – token lifetime in seconds (default `3600`).【F:stubs/auth/jwt-auth.php†L10-L33】【F:src/Auth/Jwt/JwtAuthModule.php†L23-L34】
- `AUTH_JWT_ISSUER` / `AUTH_JWT_AUDIENCE` – metadata included in tokens for validation.【F:stubs/auth/jwt-auth.php†L14-L33】【F:src/Auth/Jwt/JwtAuthModule.php†L23-L34】
- `AUTH_JWT_STORAGE_DRIVER` – selects the user repository backend (`json`, `mysql`, `pgsql`, `firebase`, `nosql`). The generated configuration surfaces connection placeholders and schema guidance for each driver while defaulting to JSON when unset.【F:stubs/auth/jwt-auth.php†L20-L181】【F:etc/auth.php†L20-L181】
- `AUTH_JWT_USER_STORE` – path to the JSON store when the JSON driver is active; point this to shared storage in clustered environments.【F:stubs/auth/jwt-auth.php†L20-L44】【F:src/Auth/Jwt/JwtAuthModule.php†L15-L28】
- Driver-specific environment variables populate the nested configuration:
  - MySQL: `AUTH_JWT_MYSQL_DSN`, `AUTH_JWT_MYSQL_USERNAME`, `AUTH_JWT_MYSQL_PASSWORD`, `AUTH_JWT_MYSQL_TABLE`.【F:stubs/auth/jwt-auth.php†L40-L89】
  - PostgreSQL: `AUTH_JWT_PGSQL_DSN`, `AUTH_JWT_PGSQL_USERNAME`, `AUTH_JWT_PGSQL_PASSWORD`, `AUTH_JWT_PGSQL_TABLE`.【F:stubs/auth/jwt-auth.php†L91-L127】
  - Firebase: `AUTH_JWT_FIREBASE_CREDENTIALS`, `AUTH_JWT_FIREBASE_DATABASE_URL`, `AUTH_JWT_FIREBASE_COLLECTION`.【F:stubs/auth/jwt-auth.php†L129-L152】
  - NoSQL document stores: `AUTH_JWT_NOSQL_CONNECTION`, `AUTH_JWT_NOSQL_DATABASE`, `AUTH_JWT_NOSQL_COLLECTION`.【F:stubs/auth/jwt-auth.php†L154-L181】
- `AUTH_JWT_ALLOW_REGISTRATION` – toggles the `/register` endpoint. The controller checks this flag before accepting new accounts.【F:stubs/auth/jwt-auth.php†L26-L37】【F:src/Auth/Jwt/AuthController.php†L33-L55】

Updating the env variables and reloading your server is enough—the module resolves configuration at runtime.

## Maintaining the user store

By default user records live in a JSON array with bcrypt password hashes. The setup command seeds an admin user with a random ID, email placeholder, and `admin` role metadata.【F:src/Console/Command/AuthJwtSetup.php†L108-L233】 When you switch drivers, apply the schema guidance in `etc/auth.php` to your database and seed records using your preferred tooling.【F:etc/auth.php†L20-L181】

To manage real accounts:

- Replace the default password immediately after bootstrapping.
- Use the `/register` endpoint or edit the JSON file directly (remember to hash passwords with `password_hash()` if you script changes).
- Backup and secure the user store when deploying to shared environments.

## Rerunning the toolkit safely

Rerunning `php bin/bamboo auth.jwt.setup` performs drift correction without deleting existing users:

- The command respects existing `AUTH_JWT_SECRET` values, only generating a new secret when the entry is missing or blank.【F:src/Console/Command/AuthJwtSetup.php†L72-L105】
- User stores that already contain data are preserved, and non-JSON backends are never mutated by the command.【F:src/Console/Command/AuthJwtSetup.php†L108-L233】
- Module registration is skipped when `JwtAuthModule` is already listed.【F:src/Console/Command/AuthJwtSetup.php†L304-L347】

This makes the command safe to wire into provisioning scripts, CI smoke tests, or recovery playbooks.

## Next steps

- Enforce HTTPS and secure token storage (for example, using `Authorization` headers in front-end clients).
- Extend the controller or middleware to add refresh tokens, revoke lists, or role-based access checks.
- Add contract tests that exercise your custom routes with the `auth.jwt` middleware to prevent regressions.

With the CLI toolkit in place you can ship JWT-protected APIs from a fresh Bamboo install in minutes.
