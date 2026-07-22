# APP_KEY rotation

`APP_KEY` protects application secrets, including encrypted SMTP credentials.
Losing it can make encrypted data unrecoverable; changing it without re-encryption
can stop mail and other integrations.

## Approved sequence

1. Schedule maintenance and create verified database/config backups.
2. Inventory every value encrypted by `SecretCipher`.
3. Retain the old key securely for the controlled transition.
4. Decrypt each value with the old key in a non-logging rotation tool.
5. Re-encrypt with a newly generated high-entropy key in one transaction or a
   restartable, validated process.
6. Update the root-owned production secret, restart the application and run
   `scripts/encrypt-secrets.php --validate-only` plus integration smoke tests.
7. Confirm email and administrative settings, then remove temporary old-key
   access according to the secret-retention policy.

No current general-purpose dual-key rotation command is implemented. Do not
improvise this process directly in production; build and test the rotation tool
before any real key change.

