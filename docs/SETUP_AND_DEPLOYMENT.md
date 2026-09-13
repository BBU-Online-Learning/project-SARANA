# Setup And Deployment

## Prerequisites

- PHP 8.3 with PDO MySQL, OpenSSL, Mbstring, Fileinfo, GD and required Composer extensions
- Composer 2
- Node.js and npm compatible with the lock file
- MySQL 8 or compatible MariaDB with an application-specific account
- Redis for staging cache, sessions and queues
- SMTP account for password recovery
- HTTPS certificates for the application and Reverb host
- Process monitor such as Supervisor or systemd

## Fresh Installation

1. Create an empty MySQL database and a least-privilege database account.
2. Run `composer install` for development or `composer install --no-dev --optimize-autoloader` on staging.
3. Run `npm ci`.
4. Copy `.env.example` to `.env` locally, or `.env.staging.example` to `.env` on staging.
5. Set every blank secret locally on the server. Never commit `.env`.
6. Generate the application key once with `php artisan key:generate` and back it up securely. Losing it makes encrypted two-factor data unreadable.
7. Run `php artisan migrate --force` only against the verified empty database.
8. Run `php artisan db:seed --force`. This creates role definitions only, never users or passwords.
9. Run `php artisan users:create-first-super-admin --name="Full Name" --email="admin@example.edu"` in an interactive private terminal. Enter the temporary password only at the hidden prompts.
10. Build assets with `npm run build`.
11. Start the web application, queue worker and Reverb process.
12. Sign in as the first Super Admin and complete two-factor and mandatory-password onboarding.

For an existing installation without any Super Admin assignment, preview an explicit eligible account with:

```bash
php artisan users:provision-super-admin USER_ID --confirm-email=exact@example.edu
```

After verifying the displayed ID, repeat with `--commit`. This command never changes credentials or bypasses onboarding.

## Existing Installation Upgrade

1. Put the application into maintenance mode.
2. Create and verify database and storage backups using `docs/BACKUP_AND_RESTORE.md`.
3. Restore those backups into an isolated staging database and storage location.
4. Run `php artisan chat:audit-group-owners` on the copy. Resolve missing or multiple owners without guessing.
5. Run `php artisan auth:encrypt-two-factor-secrets` in preview mode. Do not use `--commit` until every failure is reviewed and `APP_KEY` is backed up.
6. Run `php artisan classes:privatize-avatars` in preview mode if legacy class images exist.
7. Run `php artisan migrate --force` on the copy. The release migration stops if legacy foreign-key orphan rows exist.
8. Run the complete test and smoke-test checklist on the copy.
9. Schedule the real migration only after the copy passes and the rollback owner approves it.

Never run `migrate:fresh`, `db:wipe`, destructive rollback commands, or unreviewed SQL on retained data.

## Staging Environment

Use `.env.staging.example` as the field list. Required protections include:

- `APP_ENV=staging`, `APP_DEBUG=false`, and an `https://` application URL
- Secure, HTTP-only, encrypted sessions
- `DB_ENGINE=InnoDB`
- Redis-backed cache, sessions and queues
- `MEDIA_DISK=chat_private` and private conversion storage
- Reverb broadcasting through `wss` on port 443
- A precise `REVERB_ALLOWED_ORIGINS` hostname list, never `*`
- SMTP rather than log mail for recovery
- Unique database, Redis, SMTP and Reverb secrets injected on the server

After changing environment values, run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Processes

Run the queue worker under a process monitor:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

Run Reverb on a private interface behind the HTTPS reverse proxy:

```bash
php artisan reverb:start --host=127.0.0.1 --port=8080
```

The proxy must forward `/app` and `/apps`, preserve WebSocket upgrade headers, and expose only `wss://reverb.staging.example.edu:443`. After deployment, run `php artisan queue:restart` and `php artisan reverb:restart`; the process monitor must start replacement processes.

## Release Verification

1. Request `/up` over HTTPS and confirm a successful response.
2. Verify `APP_DEBUG` is false without deliberately exposing an exception to public users.
3. Test password recovery through the configured staging mailbox.
4. Test all four roles using `docs/PRESENTATION_SCRIPT.md`.
5. Upload an image and document in direct and group chat; confirm direct `/storage/...` guessing cannot retrieve chat media.
6. Remove a group member and class member while two browsers are connected; verify content and future events stop immediately.
7. Restart Reverb during a class message session; verify reconnect fetches missing messages without duplicates.
8. Check `php artisan queue:failed` and application logs for sanitized errors.
9. Confirm no unresolved group owner or public legacy-file gate remains.

## Rollback Trigger

Rollback when authentication, role boundaries, retained history, private file authorization, or migration integrity fails. Keep the application in maintenance mode, preserve logs, and follow the isolated restore procedure. Never attempt an improvised destructive rollback of the forward-only security migrations.

