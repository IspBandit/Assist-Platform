# ADR 0002: Forward-only ordered migrations

Status: accepted.

`database/migrations/` is the schema authority. Applied files are immutable.
Changes use expand, bounded backfill, validation and later contract steps.
Production migrations are controlled, backed up and never run concurrently.

