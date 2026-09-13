# Backup And Restore

## Backup Scope

Back up these items as one release set:

- MySQL database
- `storage/app/private`
- `storage/app/public` while any legacy public file remains
- Deployed `.env` in a secrets manager, not inside the ordinary archive
- Current `APP_KEY` and previous keys in the secrets manager
- Release commit identifier and built asset manifest

## Database Backup

Run from a trusted host using a protected MySQL option file or secret prompt. Do not put the password in shell history.

```bash
mysqldump --single-transaction --routines --triggers --hex-blob --set-gtid-purged=OFF --host=DB_HOST --user=DB_BACKUP_USER DB_NAME > elearning-YYYYMMDD-HHMM.sql
```

Hash the completed dump:

```bash
sha256sum elearning-YYYYMMDD-HHMM.sql > elearning-YYYYMMDD-HHMM.sql.sha256
```

Check the command exit code, dump size, header, and checksum. A file existing is not proof of a valid backup.

## Storage Backup

Stop file mutations or use a filesystem snapshot. Archive private and legacy public storage separately:

```bash
tar -czf elearning-private-YYYYMMDD-HHMM.tar.gz storage/app/private
tar -czf elearning-public-YYYYMMDD-HHMM.tar.gz storage/app/public
sha256sum elearning-*-YYYYMMDD-HHMM.tar.gz > elearning-storage-YYYYMMDD-HHMM.sha256
```

Store database and file backups encrypted, access-controlled, and outside the application server.

## Restore Verification

1. Create a new isolated database whose name clearly identifies it as a restore test.
2. Verify the SQL checksum before import.
3. Import with `mysql --host=DB_HOST --user=RESTORE_USER RESTORE_TEST_DB < backup.sql`.
4. Restore file archives into a separate staging directory, never over live storage.
5. Configure a temporary staging `.env` with a distinct URL, database, Redis namespace and mail sandbox.
6. Run `php artisan migrate:status`, group ownership audit, two-factor encryption preview, and the release tests.
7. Verify representative old messages, class history, public legacy files and private new files.
8. Record counts and checksums, then remove the temporary restore only after review.

## Production Restore

1. Declare an incident owner and recovery point.
2. Put the application into maintenance mode and stop queue/Reverb workers.
3. Preserve the failed database, storage and logs before replacing anything.
4. Restore into new database/storage locations first.
5. Point the application at the restored locations only after integrity checks pass.
6. Clear/cache configuration, restart workers and run permission smoke tests.
7. Keep maintenance mode active if authentication, history, attachment authorization or membership revocation fails.

Security migrations are forward-only because rollback could expose files, reactivate archived classes, lose audits, or corrupt encrypted 2FA data. Restore a verified release set instead of improvising destructive down migrations.

