# AI operations runbook

**Status:** design (Phase AI-0). No production AI service yet.

## Enable / disable

1. Confirm owner approval and budget caps.  
2. Configure secret via approved secret management.  
3. Set model allowlist.  
4. Enable vendor provider flag, then global AI flag.  
5. Smoke-test on non-production with golden queries.  
6. Monitor usage dashboard and soft-warning thresholds.

## Incident: unexpected spend

1. Disable global AI immediately.  
2. Rotate API key.  
3. Inspect `ai_usage` / audit for abuse.  
4. Confirm structured search still healthy.  
5. Post-incident review before re-enable.

## Incident: bad interpretations

1. Raise deterministic rules / lower AI confidence threshold.  
2. Flush or version-bump intent cache.  
3. Do not “fix” by inventing listings.

## Rollback

Feature flags off restores pre-AI public behaviour. Forward migrations leave
unused tables if operationally rolled back.
