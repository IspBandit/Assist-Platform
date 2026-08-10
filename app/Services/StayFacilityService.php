<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use RuntimeException;

final class StayFacilityService
{
    public const TYPES = [
        'toilets' => 'Toilets', 'accessible_toilets' => 'Accessible toilets', 'showers' => 'Showers',
        'hot_showers' => 'Hot showers', 'dump_point' => 'Dump point', 'water' => 'Water',
        'potable_water_fill' => 'Potable water fill', 'rubbish_bins' => 'Rubbish bins', 'recycling' => 'Recycling',
        'picnic_tables' => 'Picnic tables', 'shelter' => 'Shelter', 'barbecue' => 'BBQ',
        'camp_kitchen' => 'Camp kitchen', 'fire_pits' => 'Fire pits', 'campfires' => 'Campfires',
        'generators' => 'Generators', 'powered_sites' => 'Powered sites', 'unpowered_sites' => 'Unpowered sites',
        'caravan_suitable' => 'Caravan suitable', 'camper_trailer_suitable' => 'Camper trailer suitable',
        'motorhome_suitable' => 'Motorhome suitable', 'tent_camping' => 'Tent camping', 'big_rig_suitable' => 'Big-rig suitable',
        'pet_friendly' => 'Pet friendly', 'accessibility' => 'Accessibility facilities', 'mobile_coverage' => 'Mobile coverage',
        'wifi' => 'Wi-Fi', 'laundry' => 'Laundry', 'fuel' => 'Fuel', 'food_store' => 'Food or store',
        'ev_charging' => 'EV charging', 'other' => 'Other facility',
    ];

    private const SOURCE_RANK = [
        'government' => 600, 'operator' => 500, 'admin_verified' => 400,
        'user_approved' => 300, 'trusted_import' => 200, 'open_data' => 100,
    ];

    /** @return array<string,array<string,mixed>> */
    public function forPark(int $parkId): array
    {
        return $this->resolve(Database::select(
            'SELECT * FROM stay_facility_claims WHERE park_id = ? AND superseded_at IS NULL ORDER BY id DESC',
            [$parkId]
        ));
    }

    /** @param list<int> $parkIds @return array<int,array<string,array<string,mixed>>> */
    public function forParks(array $parkIds): array
    {
        $parkIds = array_values(array_unique(array_filter($parkIds, static fn (int $id): bool => $id > 0)));
        if ($parkIds === [] || !Database::tableExists('stay_facility_claims')) {
            return [];
        }
        $rows = Database::select(
            'SELECT * FROM stay_facility_claims WHERE park_id IN (' . implode(',', array_fill(0, count($parkIds), '?')) . ') AND superseded_at IS NULL ORDER BY id DESC',
            $parkIds
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['park_id']][] = $row;
        }
        foreach ($grouped as $parkId => $claims) {
            $grouped[$parkId] = $this->resolve($claims);
        }
        return $grouped;
    }

    /** @param list<array<string,mixed>> $claims @return array<string,array<string,mixed>> */
    public function resolve(array $claims): array
    {
        $resolved = [];
        foreach ($claims as $claim) {
            $type = (string) ($claim['facility_type'] ?? '');
            if ($type === '') {
                continue;
            }
            $specificity = ($claim['source_specificity'] ?? 'facility') === 'facility' ? 1000 : 0;
            $rank = $specificity + (self::SOURCE_RANK[(string) ($claim['source_type'] ?? '')] ?? 0)
                + (int) ($claim['source_confidence'] ?? 0);
            $time = strtotime((string) ($claim['verified_at'] ?? $claim['last_seen_at'] ?? $claim['updated_at'] ?? '')) ?: 0;
            if (!isset($resolved[$type]) || [$rank, $time, (int) $claim['id']] > $resolved[$type]['_sort']) {
                $claim['_sort'] = [$rank, $time, (int) $claim['id']];
                $claim['label'] = self::TYPES[$type] ?? ucwords(str_replace('_', ' ', $type));
                $claim['display'] = $this->display($claim);
                $resolved[$type] = $claim;
            }
        }
        foreach ($resolved as &$claim) {
            unset($claim['_sort']);
        }
        return $resolved;
    }

    /** @param array<string,mixed> $claim */
    private function display(array $claim): string
    {
        $status = (string) ($claim['facility_status'] ?? 'unknown');
        $value = (string) ($claim['facility_value'] ?? '');
        if (($claim['facility_type'] ?? '') === 'water') {
            return match ($value) {
                'potable' => 'Available — drinking water',
                'untreated', 'non_potable' => 'Available — treat before drinking',
                'seasonal' => 'Seasonal — confirm before arrival',
                default => $status === 'no' ? 'No' : ($status === 'unknown' ? 'Unknown' : 'Available'),
            };
        }
        return match ($status) {
            'yes' => 'Yes', 'no' => 'No', 'conditional' => $value !== '' ? ucwords(str_replace('_', ' ', $value)) : 'Conditions apply',
            default => 'Unknown',
        };
    }

    /** @param list<array<string,string>> $items */
    public function submit(int $parkId, array $items, string $comment, ?string $evidenceUrl, Request $request): array
    {
        if ($items === []) {
            throw new RuntimeException('Select at least one facility.');
        }
        $current = $this->forPark($parkId);
        $fingerprint = hash('sha256', $request->ip() . '|' . strtolower(trim((string) ($request->input('submitter_email', '')))));
        $duplicateId = null;
        if (count($items) === 1) {
            $item = $items[0];
            $duplicateId = Database::scalar(
                "SELECT fc.id FROM facility_contributions fc JOIN facility_contribution_items fci ON fci.contribution_id=fc.id
                 WHERE fc.park_id=? AND fc.status IN ('pending','under_review') AND fci.facility_type=? AND fci.suggested_status=?
                   AND COALESCE(fci.suggested_value,'')=COALESCE(?,'') ORDER BY fc.id LIMIT 1",
                [$parkId, $item['facility_type'], $item['suggested_status'], $item['suggested_value'] ?: null]
            );
        }
        Database::beginTransaction();
        try {
            $id = Database::insert(
                'INSERT INTO facility_contributions (park_id,submitter_user_id,submitter_name,submitter_email,submitter_fingerprint,comment,evidence_url,status,duplicate_of_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [$parkId, auth()->id(), trim((string) $request->input('submitter_name')) ?: null,
                    strtolower(trim((string) $request->input('submitter_email'))) ?: null, $fingerprint, $comment ?: null,
                    $evidenceUrl, $duplicateId ? 'duplicate' : 'pending', $duplicateId ?: null]
            );
            foreach ($items as $item) {
                $type = $item['facility_type'];
                $existing = $current[$type] ?? null;
                Database::query(
                    'INSERT INTO facility_contribution_items (contribution_id,facility_type,existing_status,existing_value,suggested_status,suggested_value,suggested_details,decision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
                    [$id, $type, $existing['facility_status'] ?? null, $existing['facility_value'] ?? null,
                        $item['suggested_status'], $item['suggested_value'] ?: null, $item['suggested_details'] ?: null,
                        $duplicateId ? 'duplicate' : 'pending']
                );
            }
            if ($duplicateId) {
                Database::query('INSERT IGNORE INTO facility_contribution_confirmations (contribution_id,confirmer_user_id,confirmer_fingerprint,created_at) VALUES (?,?,?,NOW())', [(int) $duplicateId, auth()->id(), $fingerprint]);
            }
            Database::commit();
            AuditLog::record('facility.contribution_submitted', 'facility_contribution', (string) $id, null, json_encode(['park_id' => $parkId, 'duplicate_of' => $duplicateId]));
            return ['id' => $id, 'duplicate_of_id' => $duplicateId];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** @param array<int,array<string,string>> $edits */
    public function moderate(int $contributionId, string $action, array $edits, string $notes, int $moderatorId): void
    {
        $contribution = Database::selectOne('SELECT * FROM facility_contributions WHERE id=?', [$contributionId]);
        if ($contribution === null) {
            throw new RuntimeException('Contribution not found.');
        }
        $items = Database::select('SELECT * FROM facility_contribution_items WHERE contribution_id=? ORDER BY id', [$contributionId]);
        $previous = (string) $contribution['status'];
        $newStatus = match ($action) {
            'approve' => 'approved', 'approve_edit' => 'approved', 'partial' => 'partially_approved',
            'reject' => 'rejected', 'duplicate' => 'duplicate', default => throw new RuntimeException('Invalid moderation action.'),
        };
        Database::beginTransaction();
        try {
            foreach ($items as $item) {
                $edit = $edits[(int) $item['id']] ?? [];
                $approve = in_array($action, ['approve', 'approve_edit'], true) || ($action === 'partial' && ($edit['approve'] ?? '') === '1');
                if ($approve) {
                    $status = $edit['status'] ?? (string) $item['suggested_status'];
                    $value = $edit['value'] ?? (string) ($item['suggested_value'] ?? '');
                    $details = $edit['details'] ?? (string) ($item['suggested_details'] ?? '');
                    $claimId = Database::insert(
                        'INSERT INTO stay_facility_claims (park_id,facility_type,facility_status,facility_value,details,source_type,source_name,source_confidence,source_specificity,contribution_item_id,verified_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW())',
                        [(int) $contribution['park_id'], $item['facility_type'], $status, $value ?: null, $details ?: null, 'user_approved', 'Approved VanAssist community contribution', 70, 'facility', (int) $item['id']]
                    );
                    Database::query("UPDATE facility_contribution_items SET decision=?,resulting_claim_id=?,updated_at=NOW() WHERE id=?", [$action === 'approve_edit' ? 'edited' : 'approved', $claimId, (int) $item['id']]);
                } else {
                    Database::query("UPDATE facility_contribution_items SET decision=?,updated_at=NOW() WHERE id=?", [$action === 'duplicate' ? 'duplicate' : 'rejected', (int) $item['id']]);
                }
            }
            Database::query('UPDATE facility_contributions SET status=?,moderator_user_id=?,moderator_notes=?,moderated_at=NOW(),updated_at=NOW() WHERE id=?', [$newStatus, $moderatorId, $notes ?: null, $contributionId]);
            Database::query('INSERT INTO facility_moderation_actions (contribution_id,moderator_user_id,action,previous_status,new_status,old_value,new_value,notes,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [$contributionId,$moderatorId,$action,$previous,$newStatus,json_encode($items),json_encode($edits),$notes ?: null]);
            Database::commit();
            AuditLog::record('facility.contribution_moderated', 'facility_contribution', (string) $contributionId, $previous, json_encode(['status' => $newStatus, 'notes' => $notes]));
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
