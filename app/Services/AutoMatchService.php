<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Automated request -> provider matching & dispatch.
 *
 * Gated entirely by the `auto_matching` feature flag. When on:
 *   - an approved (open) request is scored and the strongest providers are
 *     invited automatically, moving the request into 'matching' with no admin
 *     action (process / runBatch);
 *   - if no provider clears the bar, the request is flagged for the admin
 *     instead of silently stalling (fallback_admin);
 *   - when an invited provider expresses interest, the customer's contact is
 *     released to them automatically, subject to consent and a hard cap
 *     (releaseContactOnInterest);
 *   - silent invites are escalated to the next batch of providers after an
 *     urgency-based delay (escalate).
 *
 * Everything is additive and reversible: turn the flag off and the platform
 * reverts to the manual admin matching console.
 */
final class AutoMatchService
{
    public static function enabled(): bool
    {
        return FeatureFlag::enabled('auto_matching');
    }

    /**
     * Score and auto-invite providers for a single request. Safe to call more
     * than once: it only acts on an 'open' request that has no matches yet, so
     * it never tramples manual work or double-invites.
     *
     * @return array<string,mixed>
     */
    public function process(int $requestId): array
    {
        if (!self::enabled()) {
            return ['skipped' => 'flag_off'];
        }

        $req = Database::selectOne(
            'SELECT sr.id, sr.reference, sr.status, sr.urgency, sr.is_spam, sr.town_id, '
            . 'sr.contact_email, sr.contact_name, t.name AS town_name '
            . 'FROM service_requests sr LEFT JOIN towns t ON t.id = sr.town_id '
            . 'WHERE sr.id = ? AND sr.deleted_at IS NULL',
            [$requestId]
        );
        if ($req === null) {
            return ['skipped' => 'not_found'];
        }
        if ($req['status'] !== 'open') {
            return ['skipped' => 'not_open'];
        }
        if ((int) $req['is_spam'] === 1) {
            return ['skipped' => 'spam'];
        }

        $existing = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_request_matches WHERE request_id = ?',
            [$requestId]
        );
        if ($existing > 0) {
            Database::query(
                "UPDATE service_requests SET auto_match_state = 'done', auto_matched_at = COALESCE(auto_matched_at, NOW()) WHERE id = ?",
                [$requestId]
            );
            return ['skipped' => 'already_matched'];
        }

        $minScore = $this->tunable('auto_invite_min_score', 45);
        $maxInvites = $this->tunable('auto_invite_max_per_request', 5);

        $candidates = (new MatchingService())->suggest($requestId, 30);
        $eligible = $this->eligible($candidates, $minScore);

        if ($eligible === []) {
            $this->fallbackToAdmin($req, count($candidates), 'No provider met the auto-invite threshold (min score ' . $minScore . ')');
            return ['state' => 'fallback_admin', 'invited' => 0, 'candidates' => count($candidates)];
        }

        $invited = $this->inviteBatch($req, $eligible, $maxInvites);
        if ($invited === 0) {
            $this->fallbackToAdmin($req, count($candidates), 'Eligible providers were all over their daily invite cap');
            return ['state' => 'fallback_admin', 'invited' => 0, 'candidates' => count($candidates)];
        }

        RequestWorkflow::changeStatus($requestId, 'matching', null, 'Auto-matched: invited ' . $invited . ' provider(s)');
        Database::query(
            "UPDATE service_requests SET auto_match_state = 'done', auto_matched_at = NOW() WHERE id = ?",
            [$requestId]
        );
        AuditLog::record('request.auto_matched', 'service_request', (string) $requestId, null, (string) $invited);

        return ['state' => 'done', 'invited' => $invited, 'candidates' => count($candidates)];
    }

    /**
     * Process the backlog of requests awaiting an automated matching pass.
     * Driven by the `update_match_suggestions` cron task.
     *
     * @return array<string,mixed>
     */
    public function runBatch(): array
    {
        if (!self::enabled()) {
            return ['note' => 'auto_matching disabled; skipped'];
        }

        $limit = max(1, $this->tunable('cron_batch', 25));
        $rows = Database::select(
            "SELECT sr.id FROM service_requests sr "
            . "WHERE sr.status = 'open' AND sr.auto_match_state = 'pending' AND sr.is_spam = 0 AND sr.deleted_at IS NULL "
            . "AND NOT EXISTS (SELECT 1 FROM service_request_matches m WHERE m.request_id = sr.id) "
            . "ORDER BY FIELD(sr.urgency, 'urgent', 'high', 'medium', 'low'), sr.created_at "
            . "LIMIT {$limit}"
        );

        $processed = 0;
        $invited = 0;
        $fallback = 0;
        foreach ($rows as $row) {
            $res = $this->process((int) $row['id']);
            $processed++;
            $invited += (int) ($res['invited'] ?? 0);
            if (($res['state'] ?? '') === 'fallback_admin') {
                $fallback++;
            }
        }

        return ['processed' => $processed, 'invited' => $invited, 'fallback' => $fallback];
    }

    /**
     * Re-invite the next batch of providers for requests where the invited
     * providers have stayed silent past the urgency-based window. Driven by the
     * `provider_followups` cron task.
     *
     * @return array<string,mixed>
     */
    public function escalate(): array
    {
        if (!self::enabled()) {
            return ['note' => 'auto_matching disabled; skipped'];
        }

        /** @var array<string,int> $hours */
        $hours = (array) config('matching.escalation_hours', ['urgent' => 3, 'high' => 8, 'medium' => 24, 'low' => 48]);
        $batch = $this->tunable('escalation_batch', 3);
        $maxTotal = $this->tunable('max_total_auto_invites', 12);

        $rows = Database::select(
            "SELECT sr.id, sr.reference, sr.urgency, sr.town_id, t.name AS town_name, "
            . "(SELECT MAX(m.invited_at) FROM service_request_matches m WHERE m.request_id = sr.id AND m.auto_invited = 1) AS last_invite, "
            . "(SELECT COUNT(*) FROM service_request_matches m WHERE m.request_id = sr.id AND m.auto_invited = 1) AS invite_count "
            . "FROM service_requests sr LEFT JOIN towns t ON t.id = sr.town_id "
            . "WHERE sr.status = 'matching' AND sr.auto_match_state = 'done' AND sr.deleted_at IS NULL "
            . "AND NOT EXISTS (SELECT 1 FROM service_request_matches m WHERE m.request_id = sr.id "
            . "AND m.status IN ('interested', 'more_info', 'offered', 'accepted')) "
            . "LIMIT 50"
        );

        $escalated = 0;
        $exhausted = 0;
        foreach ($rows as $r) {
            if ($r['last_invite'] === null) {
                continue;
            }
            $window = (int) ($hours[(string) $r['urgency']] ?? 24);
            if (strtotime((string) $r['last_invite']) > strtotime('-' . $window . ' hours')) {
                continue; // not due for escalation yet
            }

            $reqRow = ['id' => (int) $r['id'], 'reference' => (string) $r['reference'], 'town_name' => (string) ($r['town_name'] ?? '')];

            if ((int) $r['invite_count'] >= $maxTotal) {
                $this->fallbackToAdmin($reqRow, (int) $r['invite_count'], 'Reached the maximum automated invites without a response');
                $exhausted++;
                continue;
            }

            $new = $this->inviteNext((int) $r['id'], $reqRow, $batch);
            if ($new > 0) {
                $escalated += $new;
                $this->log((int) $r['id'], null, 'escalated', 'Invited ' . $new . ' more provider(s)');
            } else {
                $this->fallbackToAdmin($reqRow, (int) $r['invite_count'], 'No further providers available to invite');
                $exhausted++;
            }
        }

        return ['escalated_invites' => $escalated, 'exhausted' => $exhausted, 'checked' => count($rows)];
    }

    /**
     * Release the customer's contact details to a provider that has just
     * expressed interest. Honours the customer's sharing consent and a hard cap
     * on how many providers can be given contact for one request.
     *
     * @param array<string,mixed> $match    pre-update match row
     * @param array<string,mixed> $req       full service_requests row
     * @param array<string,mixed> $provider  full providers row
     * @return array<string,mixed>
     */
    public function releaseContactOnInterest(array $match, array $req, array $provider): array
    {
        if (!self::enabled()) {
            return ['released' => false, 'reason' => 'flag_off'];
        }
        if ((int) ($match['contact_released'] ?? 0) === 1) {
            return ['released' => false, 'reason' => 'already_released'];
        }

        $requiresConsent = (bool) config('matching.auto_release_requires_consent', true);
        if ($requiresConsent && (int) ($req['consent_share'] ?? 0) !== 1) {
            $this->log((int) $req['id'], (int) $provider['id'], 'release_blocked', 'Customer has not consented to share contact');
            return ['released' => false, 'reason' => 'no_consent'];
        }

        $cap = max(1, $this->tunable('contact_release_max_providers', 2));
        $already = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_request_matches WHERE request_id = ? AND contact_released = 1',
            [(int) $req['id']]
        );
        if ($already >= $cap) {
            Database::query(
                "UPDATE service_requests SET auto_match_state = 'locked', updated_at = NOW() WHERE id = ?",
                [(int) $req['id']]
            );
            return ['released' => false, 'reason' => 'cap_reached'];
        }

        Database::query(
            "UPDATE service_request_matches SET contact_released = 1, released_at = NOW(), "
            . "release_reason = 'auto_on_interest', updated_at = NOW() WHERE id = ?",
            [(int) $match['id']]
        );
        Database::query(
            'UPDATE service_requests SET interested_count = interested_count + 1, updated_at = NOW() WHERE id = ?',
            [(int) $req['id']]
        );

        $to = $this->providerEmail($provider);
        if ($to !== null) {
            $contact = trim((string) $req['contact_name']) . ' — ' . (string) $req['contact_email']
                . ($req['contact_phone'] ? ' / ' . (string) $req['contact_phone'] : '');
            EmailQueue::queueRaw(
                $to,
                (string) $provider['business_name'],
                'Customer contact released for ' . (string) $req['reference'],
                '<p>You expressed interest in request <strong>' . e((string) $req['reference']) . '</strong>, '
                . 'and the customer has agreed to be contacted.</p>'
                . '<p><strong>' . e($contact) . '</strong></p>'
                . '<p>Please get in touch promptly. '
                . '<a href="' . e(url('provider/requests/' . (int) $match['id'])) . '">View the request</a></p>',
                'Customer contact for ' . (string) $req['reference'] . ': ' . $contact
            );
        }

        if ($already + 1 >= $cap) {
            Database::query(
                "UPDATE service_requests SET auto_match_state = 'locked', updated_at = NOW() WHERE id = ?",
                [(int) $req['id']]
            );
        }

        $this->log((int) $req['id'], (int) $provider['id'], 'contact_released', 'auto_on_interest');
        AuditLog::record('match.auto_contact_released', 'service_request', (string) $req['id'], null, (string) $provider['id']);

        return ['released' => true];
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Filter scored candidates down to those eligible for an automated invite:
     * above the score threshold, not opted out, and available for the dates.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function eligible(array $candidates, int $minScore): array
    {
        return array_values(array_filter($candidates, static fn ($c) =>
            (int) $c['score'] >= $minScore
            && (int) ($c['auto_invite_opt_out'] ?? 0) === 0
            && !empty($c['available'])
            && empty($c['already_matched'])));
    }

    /**
     * Invite up to $max providers, skipping any that have hit their daily cap.
     *
     * @param array<string,mixed>            $req
     * @param array<int,array<string,mixed>> $candidates
     */
    private function inviteBatch(array $req, array $candidates, int $max): int
    {
        $dailyCap = $this->tunable('auto_invite_provider_daily_cap', 8);
        $invited = 0;
        foreach ($candidates as $c) {
            if ($invited >= $max) {
                break;
            }
            $providerId = (int) $c['id'];
            if ($this->invitesToday($providerId) >= $dailyCap) {
                $this->log((int) $req['id'], $providerId, 'skipped_cap', 'Provider at daily invite cap');
                continue;
            }

            $reasons = implode(', ', array_slice((array) ($c['reasons'] ?? []), 0, 6));
            Database::query(
                'INSERT INTO service_request_matches '
                . '(request_id, provider_id, matched_by, auto_invited, invited_at, match_score, match_reasons, status, created_at, updated_at) '
                . 'VALUES (?, ?, NULL, 1, NOW(), ?, ?, ?, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), auto_invited = 1, invited_at = NOW(), '
                . 'match_score = VALUES(match_score), match_reasons = VALUES(match_reasons), updated_at = NOW()',
                [(int) $req['id'], $providerId, (float) $c['score'], substr($reasons, 0, 500), 'invited']
            );

            $this->sendInvite($req, $c, $providerId);
            $this->log((int) $req['id'], $providerId, 'invited', 'score ' . (int) $c['score']);
            AuditLog::record('match.auto_invited', 'service_request', (string) $req['id'], null, (string) $providerId);
            $invited++;
        }
        return $invited;
    }

    /**
     * Invite the next-best providers not already linked to the request.
     *
     * @param array<string,mixed> $req
     */
    private function inviteNext(int $requestId, array $req, int $batch): int
    {
        $minScore = $this->tunable('auto_invite_min_score', 45);
        $candidates = (new MatchingService())->suggest($requestId, 40);
        $eligible = $this->eligible($candidates, $minScore);
        if ($eligible === []) {
            return 0;
        }
        return $this->inviteBatch($req, $eligible, $batch);
    }

    /** @param array<string,mixed> $req @param array<string,mixed> $c */
    private function sendInvite(array $req, array $c, int $providerId): void
    {
        $to = $this->providerEmail($c);
        if ($to === null) {
            return;
        }
        $matchId = (int) Database::scalar(
            'SELECT id FROM service_request_matches WHERE request_id = ? AND provider_id = ?',
            [(int) $req['id'], $providerId]
        );
        EmailQueue::queueTemplate('provider_match_invitation', $to, (string) $c['business_name'], [
            'provider_name' => (string) $c['business_name'],
            'town_name'     => (string) ($req['town_name'] ?? ''),
            'action_url'    => url('provider/requests/' . $matchId),
        ]);
    }

    private function invitesToday(int $providerId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM service_request_matches WHERE provider_id = ? AND auto_invited = 1 AND DATE(invited_at) = CURDATE()',
            [$providerId]
        );
    }

    /** Resolve a usable email from a provider/candidate row. */
    private function providerEmail(array $p): ?string
    {
        $email = (string) ($p['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($p['user_id'])) {
            $email = (string) Database::scalar('SELECT email FROM users WHERE id = ?', [(int) $p['user_id']]);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = (string) ($p['public_email'] ?? '');
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Mark a request as needing manual attention and notify the administrator.
     *
     * @param array<string,mixed> $req
     */
    private function fallbackToAdmin(array $req, int $candidateCount, string $reason): void
    {
        Database::query(
            "UPDATE service_requests SET auto_match_state = 'fallback_admin', auto_matched_at = COALESCE(auto_matched_at, NOW()) WHERE id = ?",
            [(int) $req['id']]
        );
        $this->log((int) $req['id'], null, 'fallback_admin', $reason);

        try {
            Database::query(
                'INSERT INTO system_health_logs (metric_key, metric_value, created_at) VALUES (?, ?, NOW())',
                ['auto_match_fallback', json_encode(['request' => $req['reference'] ?? (int) $req['id'], 'candidates' => $candidateCount, 'reason' => $reason])]
            );
        } catch (Throwable) {
            // health logging is best-effort
        }

        $to = (string) (Settings::get('contact_email') ?: config('mail.from_address', ''));
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            EmailQueue::queueTemplate('admin_request_no_match', $to, 'VanAssist admin', [
                'request_reference' => (string) ($req['reference'] ?? ''),
                'town_name'         => (string) ($req['town_name'] ?? ''),
                'reason'            => $reason,
                'action_url'        => url('admin/matching/request?id=' . (int) $req['id']),
            ]);
        }
    }

    private function log(int $requestId, ?int $providerId, string $action, ?string $detail = null): void
    {
        try {
            Database::query(
                'INSERT INTO auto_match_log (request_id, provider_id, action, detail, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$requestId, $providerId, $action, $detail !== null ? substr($detail, 0, 255) : null]
            );
        } catch (Throwable) {
            // logging must never break the matching flow
        }
    }

    /** Live-tunable integer: site_settings('match_'.$key) overrides config('matching.'.$key). */
    private function tunable(string $key, int $default): int
    {
        $setting = Settings::get('match_' . $key);
        if ($setting !== null && $setting !== '' && is_numeric($setting)) {
            return (int) $setting;
        }
        return (int) config('matching.' . $key, $default);
    }
}
