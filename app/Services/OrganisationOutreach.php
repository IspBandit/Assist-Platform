<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Evidence-backed organisation outreach register. A published address is only
 * send-eligible after a human records why the role and message are directly
 * relevant, confirms no contrary notice was found, and approves the record.
 */
final class OrganisationOutreach
{
    public const TYPES = [
        'club' => 'Caravan, RV or touring club',
        'club_federation' => 'Club federation or peak body',
        'industry_association' => 'Industry association',
        'manufacturer' => 'Manufacturer',
        'dealer_network' => 'Dealer or sales network',
        'rental_fleet' => 'Rental fleet or marketplace',
        'park_network' => 'Caravan park or campground network',
        'publication' => 'Publication or media',
        'tourism_body' => 'Tourism organisation',
        'touring_association' => '4WD or touring association',
        'other' => 'Other relevant organisation',
    ];

    public const STATUSES = [
        'research' => 'Research — not reviewed',
        'held' => 'Held — insufficient or unsuitable evidence',
        'eligible' => 'Eligible — reviewed role-relevant contact',
        'do_not_contact' => 'Do not contact',
    ];

    public const OUTCOMES = [
        'not_contacted' => 'Not contacted',
        'sent' => 'Sent',
        'replied' => 'Replied',
        'interested' => 'Interested',
        'shared' => 'Shared with audience',
        'declined' => 'Declined',
        'bounced' => 'Bounced',
        'opted_out' => 'Opted out',
    ];

    /** @return array{total:int,research:int,held:int,eligible:int,do_not_contact:int,contacted:int,positive:int,follow_ups_due:int,sent_by_platform:int} */
    public static function summary(): array
    {
        $rows = Database::select('SELECT review_status,COUNT(*) AS total FROM organisation_outreach_contacts GROUP BY review_status');
        $out = ['total' => 0, 'research' => 0, 'held' => 0, 'eligible' => 0, 'do_not_contact' => 0, 'contacted' => 0, 'positive' => 0, 'follow_ups_due' => 0, 'sent_by_platform' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['review_status'];
            $count = (int) $row['total'];
            if (array_key_exists($status, $out)) {
                $out[$status] = $count;
            }
            $out['total'] += $count;
        }
        $out['contacted'] = (int) Database::scalar('SELECT COUNT(*) FROM organisation_outreach_contacts WHERE last_contacted_at IS NOT NULL');
        $out['positive'] = (int) Database::scalar("SELECT COUNT(*) FROM organisation_outreach_contacts WHERE outcome_status IN ('interested','shared')");
        $out['follow_ups_due'] = (int) Database::scalar('SELECT COUNT(*) FROM organisation_outreach_contacts WHERE next_follow_up_at IS NOT NULL AND next_follow_up_at<=NOW() AND review_status<>\'do_not_contact\'');
        $out['sent_by_platform'] = (int) Database::scalar("SELECT COUNT(*) FROM organisation_outreach_events WHERE event_type='sent'");
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function search(string $query = '', string $status = '', string $type = '', string $state = ''): array
    {
        $where = ['1=1'];
        $params = [];
        if ($query !== '') {
            $where[] = '(organisation_name LIKE ? OR email LIKE ? OR contact_role LIKE ? OR coverage LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (isset(self::STATUSES[$status])) {
            $where[] = 'review_status=?';
            $params[] = $status;
        }
        if (isset(self::TYPES[$type])) {
            $where[] = 'organisation_type=?';
            $params[] = $type;
        }
        if (preg_match('/^[A-Z]{2,3}$/', $state) === 1) {
            $where[] = 'state_code=?';
            $params[] = $state;
        }
        return Database::select(
            'SELECT * FROM organisation_outreach_contacts WHERE ' . implode(' AND ', $where)
            . " ORDER BY FIELD(review_status,'research','held','eligible','do_not_contact'),organisation_name LIMIT 500",
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function eligibleRecipients(?string $type = null): array
    {
        $typeClause = isset(self::TYPES[$type ?? '']) ? ' AND organisation_type=?' : '';
        $params = $typeClause !== '' ? [$type] : [];
        $rows = Database::select(
            "SELECT id AS organisation_contact_id,NULL AS user_id,NULL AS provider_id,email,organisation_name AS name,"
            . "consent_basis AS marketing_consent_source,CONCAT(DATE_FORMAT(reviewed_at,'%Y-%m-%d'),': ',consent_basis,': ',consent_evidence) AS compliance_evidence,"
            . 'contact_role,relevance_reason,source_url FROM organisation_outreach_contacts '
            . "WHERE review_status='eligible' AND reviewed_at IS NOT NULL AND reviewed_by IS NOT NULL "
            . "AND consent_basis IN ('express_written','express_phone','express_web','inferred_role_relevant') "
            . "AND NULLIF(TRIM(consent_evidence),'') IS NOT NULL AND no_unsolicited_warning=0 AND personal_or_ambiguous=0 "
            . 'AND source_checked_at>=DATE_SUB(CURDATE(),INTERVAL 180 DAY)' . $typeClause . ' ORDER BY organisation_name',
            $params
        );
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) $row['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email]) || EmailSuppression::isSuppressed($email, 'marketing')) {
                continue;
            }
            $seen[$email] = true;
            $row['email'] = $email;
            $out[] = $row;
        }
        return $out;
    }

    public static function review(int $id, string $status, ?int $reviewerId, string $basis = '', string $evidence = ''): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new RuntimeException('Choose a valid review status.');
        }
        $contact = Database::selectOne('SELECT * FROM organisation_outreach_contacts WHERE id=?', [$id]);
        if ($contact === null) {
            throw new RuntimeException('Organisation contact not found.');
        }
        if ($status === 'eligible') {
            if (!in_array($basis, ['express_written', 'express_phone', 'express_web', 'inferred_role_relevant'], true)) {
                throw new RuntimeException('Record the actual consent or role-relevant basis.');
            }
            if (mb_strlen(trim($evidence)) < 20) {
                throw new RuntimeException('Record specific evidence explaining the published role, source and message relevance.');
            }
            if (!empty($contact['no_unsolicited_warning']) || !empty($contact['personal_or_ambiguous'])) {
                throw new RuntimeException('This record is held by a safety flag and cannot be made send-eligible.');
            }
            if (strtotime((string) $contact['source_checked_at']) < strtotime('-180 days')) {
                throw new RuntimeException('Recheck the official source before approving this contact.');
            }
        }
        Database::query(
            'UPDATE organisation_outreach_contacts SET review_status=?,consent_basis=?,consent_evidence=?,reviewed_at=NOW(),reviewed_by=?,updated_at=NOW() WHERE id=?',
            [$status, $status === 'eligible' ? $basis : null, $status === 'eligible' ? mb_substr(trim($evidence), 0, 1000) : null, $reviewerId, $id]
        );
        self::event($id, 'reviewed', null, null, $reviewerId, $status . ($status === 'eligible' ? ': ' . trim($evidence) : ''));
    }

    public static function recordOutcome(int $id, string $outcome, string $notes, ?string $followUp): void
    {
        if (!isset(self::OUTCOMES[$outcome])) {
            throw new RuntimeException('Choose a valid outreach outcome.');
        }
        $contact = Database::selectOne('SELECT email FROM organisation_outreach_contacts WHERE id=?', [$id]);
        if ($contact === null) {
            throw new RuntimeException('Organisation contact not found.');
        }
        $followUpAt = $followUp !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUp) === 1 ? $followUp . ' 09:00:00' : null;
        Database::query(
            'UPDATE organisation_outreach_contacts SET outcome_status=?,outcome_notes=?,next_follow_up_at=?,last_contacted_at=IF(?=\'not_contacted\',last_contacted_at,COALESCE(last_contacted_at,NOW())),review_status=IF(?=\'opted_out\',\'do_not_contact\',review_status),updated_at=NOW() WHERE id=?',
            [$outcome, mb_substr(trim($notes), 0, 1000) ?: null, $followUpAt, $outcome, $outcome, $id]
        );
        if ($outcome === 'opted_out') {
            EmailSuppression::suppressMarketing((string) $contact['email'], 'admin_pr_outreach_outcome');
        }
        $event = in_array($outcome, ['replied','interested','shared','declined','bounced','opted_out'], true)
            ? $outcome
            : 'follow_up';
        self::event($id, $event, null, null, isset(current_user()['id']) ? (int) current_user()['id'] : null, trim($notes));
    }

    public static function event(int $contactId, string $type, ?int $notificationId = null, ?int $recipientId = null, ?int $actorId = null, string $notes = ''): void
    {
        $allowed = ['reviewed','queued','sent','failed','suppressed','replied','interested','shared','declined','bounced','opted_out','follow_up'];
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException('Invalid organisation outreach event.');
        }
        Database::query(
            'INSERT INTO organisation_outreach_events (organisation_contact_id,notification_id,notification_recipient_id,event_type,actor_user_id,notes,created_at) VALUES (?,?,?,?,?,?,NOW())',
            [$contactId, $notificationId, $recipientId, $type, $actorId, mb_substr(trim($notes), 0, 1000) ?: null]
        );
    }
}
