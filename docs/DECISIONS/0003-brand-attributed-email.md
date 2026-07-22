# ADR 0003: Brand-attributed outbound email

Status: accepted.

Every queued email records `brand_id`. The worker resolves the From address and
name from that exact brand. A shared SMTP transport may be used, but TowSmart and
TrailerWise must never fall back to VanAssist sender identity. Missing or unknown
brand sender configuration fails delivery safely instead of misbranding email.

