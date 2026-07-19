<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * Auto-matching tunables.
 *
 * The `auto_matching` feature flag (admin-toggleable) is the master switch;
 * these values shape behaviour once it is on. The most operationally useful
 * numeric knobs can also be overridden live via site_settings using the key
 * "match_<name>" (e.g. match_auto_invite_min_score) without a deploy — see
 * App\Services\AutoMatchService::tunable().
 */
return [
    // Maximum providers auto-invited per request on the initial pass.
    'auto_invite_max_per_request' => (int) Env::get('MATCH_AUTO_INVITE_MAX', 5),

    // Minimum match score a provider must reach before being auto-invited.
    'auto_invite_min_score' => (int) Env::get('MATCH_AUTO_INVITE_MIN_SCORE', 45),

    // Max automated invites a single provider may receive per day (anti-fatigue).
    'auto_invite_provider_daily_cap' => (int) Env::get('MATCH_PROVIDER_DAILY_CAP', 8),

    // How many interested providers may receive the customer's contact details
    // before the request locks to further auto-invites/releases.
    'contact_release_max_providers' => (int) Env::get('MATCH_CONTACT_RELEASE_MAX', 2),

    // Require the customer's explicit "share my contact" consent before auto
    // releasing their details to a provider. Strongly recommended to leave on.
    'auto_release_requires_consent' => (bool) Env::get('MATCH_RELEASE_REQUIRES_CONSENT', true),

    // Hours of provider silence before escalating to the next batch, by urgency.
    'escalation_hours' => [
        'urgent' => (int) Env::get('MATCH_ESCALATE_URGENT_HOURS', 3),
        'high'   => (int) Env::get('MATCH_ESCALATE_HIGH_HOURS', 8),
        'medium' => (int) Env::get('MATCH_ESCALATE_MEDIUM_HOURS', 24),
        'low'    => (int) Env::get('MATCH_ESCALATE_LOW_HOURS', 48),
    ],

    // Providers invited per escalation pass.
    'escalation_batch' => (int) Env::get('MATCH_ESCALATION_BATCH', 3),

    // Total automated invites allowed across all passes before handing the
    // request to an administrator.
    'max_total_auto_invites' => (int) Env::get('MATCH_MAX_TOTAL_INVITES', 12),

    // Requests processed per cron pass.
    'cron_batch' => (int) Env::get('MATCH_CRON_BATCH', 25),
];
