# Release Audit

Audit date: 2026-09-01

## Release Decision

Status: **not approved for external production deployment yet**.

The application code has automated coverage for the agreed four-role collaboration MVP. Release remains gated by the environment-specific actions in this document. Those actions intentionally were not performed against development or production data.

## Previous Task Acceptance

| Task | Status | Evidence | Unfinished acceptance criteria |
| --- | --- | --- | --- |
| 1. Test baseline | Complete | Onboarded factory state and separate incomplete-onboarding tests | None found |
| 2. Four roles | Complete in code | Actor/target authorization matrix, fixed-role seed, last-Super-Admin concurrency tests | Apply migrations on a backed-up staging copy |
| 3. Authentication | Complete in code | Active-session suspension, OTP expiry/replay/rate-limit, mandatory-password and recovery tests | Configure and verify real staging SMTP; run the two-factor encryption preview before commit |
| 4. Class authorization | Complete in code | Outsider Teacher, ID tampering, removed member and subscription-revocation tests | None found in code |
| 5. Class lifecycle | Complete in code | Owner creation, archive restrictions, announcements, join code and ownership tests | Run class-avatar migration preview if legacy public avatars exist |
| 6. Attachments | Partial release gate | New files use private storage; guest, outsider and removed-member tests pass | Legacy files on the public disk must be backed up, inventoried and migrated with explicit approval |
| 7. Class messaging | Complete in code | Stable 50-message pagination, idempotency, edit/delete, reconnect and real Reverb tests | Verify staging WebSocket proxy and TLS certificate |
| 8. Groups | Complete in code, data audit pending | Owner/member boundaries, leave rules, revocation and real socket tests | Run group-owner audit on the staging copy and resolve every missing/multiple owner before release |
| 9. Four-role UI | Complete in code | Real counts, permitted links, profile allowlist, empty/error/mobile behavior tests | Human visual check on target phones and browsers |
| 10. Release preparation | In progress | Fresh MySQL and synthetic upgrade verification completed | Complete the release gates below and rehearse the demo |

## Automated Evidence

- Fresh MySQL 8.4.7 migration and credential-free role seed: passed on a randomized disposable database.
- Fresh MySQL role, auth, class, group, attachment, messaging and UI acceptance run: 362 tests, 2,354 assertions passed.
- MySQL concurrency and group-isolation run: 49 tests, 307 assertions passed.
- Synthetic legacy MyISAM upgrade: preserved message, attachment and media rows; reconciled a reliable creator-owner; produced zero non-transactional retained tables and 28 foreign keys.
- Disposable databases matched the guarded `elearning_release_test_<24 hex>` or `elearning_roles_test_<24 hex>` patterns and were dropped after verification.
- Complete SQLite suite, including real class/group Reverb reconnect and revocation: 459 tests, 2,835 assertions passed.
- Production Vite build: passed; 59 modules transformed and a production manifest was generated.
- Laravel configuration, route and Blade cache compilation: passed, then caches were cleared for local development.

## Blocking Release Gates

| Gate | Owner | Pass condition |
| --- | --- | --- |
| Verified database backup | Operator | SQL dump restores successfully into an isolated database |
| Verified storage backup | Operator | Public/private files are present and checksum-verified in restored copy |
| Migration preflight | Operator | Disposable copy migrates with no orphan-row or engine error |
| Group ownership | Project owner | `php artisan chat:audit-group-owners` exits successfully |
| Legacy public files | Project owner | Inventory is empty, or approved migration removes direct public exposure after verified copy |
| Legacy 2FA secrets | Security owner | Preview has zero failures; commit uses the same protected `APP_KEY` and is backed up |
| Staging infrastructure | Operator | HTTPS, SMTP, Redis, queue worker, Reverb and private storage checks pass |
| Human acceptance | Project owner | Four-role script passes on desktop and mobile without permission leakage |

## Scope Exclusions

Do not add calendar, assignments/grading, meetings/calls, screen sharing, analytics, advanced notifications, a file manager, or advanced reactions before release. Existing legacy call tables are not part of the accepted MVP and should not be demonstrated.

## Bug-Fix And Rehearsal Reserve

- Reserve two working days after the first complete staging pass for P0/P1 fixes only.
- Reserve one day for desktop/mobile regression checks.
- Run the presentation script twice with a clean demo dataset.
- Freeze features 48 hours before presentation; accept only release-blocking fixes.
