<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\GarageAsset;
use App\Services\FileStorage;
use RuntimeException;

final class GarageController extends Controller
{
    /** @var array<string,string> */
    private const DOCUMENT_TYPES = [
        'registration' => 'Registration',
        'insurance' => 'Insurance',
        'roadworthy' => 'Roadworthy certificate',
        'inspection' => 'Inspection report',
        'engineering_certificate' => 'Engineering certificate',
        'modification_approval' => 'Modification approval',
        'service_record' => 'Service record',
        'receipt' => 'Receipt',
        'warranty' => 'Warranty',
        'other' => 'Other document',
    ];

    public function index(Request $request): Response
    {
        return $this->view('account.garage', [
            'title' => 'My Garage',
            'assets' => GarageAsset::forOwner($this->userId()),
            'types' => GarageAsset::TYPES,
            'jurisdictions' => GarageAsset::JURISDICTIONS,
        ]);
    }

    public function create(Request $request): Response
    {
        $data = $this->validatedAsset($request);
        if (isset($data['error'])) {
            return $this->redirectWith('/account/garage', 'error', (string) $data['error']);
        }

        $assetId = Database::insert(
            'INSERT INTO garage_assets (user_id, created_in_brand_id, asset_type, nickname, make, model, model_year, '
            . 'registration_jurisdiction, tare_kg, gvm_kg, gcm_kg, atm_kg, max_braked_towing_kg, max_towball_kg, notes, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $this->userId(), current_brand()->databaseId(), $data['asset_type'], $data['nickname'],
                $data['make'], $data['model'], $data['model_year'], $data['registration_jurisdiction'],
                $data['tare_kg'], $data['gvm_kg'], $data['gcm_kg'], $data['atm_kg'],
                $data['max_braked_towing_kg'], $data['max_towball_kg'], $data['notes'],
            ]
        );
        $this->activity($assetId, 'created');

        return $this->redirectWith('/account/garage/' . $assetId, 'success', 'Added to your shared Garage.');
    }

    public function show(Request $request): Response
    {
        $asset = $this->ownedAsset((int) $request->route('id'));
        if ((int) $asset['created_in_brand_id'] !== current_brand()->databaseId()) {
            $this->activity((int) $asset['id'], 'viewed', ['created_in_brand' => $asset['created_in_brand_key']]);
        }

        return $this->view('account.garage-show', [
            'title' => (string) $asset['nickname'] . ' — My Garage',
            'asset' => $asset,
            'documents' => GarageAsset::documents((int) $asset['id']),
            'types' => GarageAsset::TYPES,
            'jurisdictions' => GarageAsset::JURISDICTIONS,
            'documentTypes' => self::DOCUMENT_TYPES,
            'rulesVehicle' => GarageAsset::rulesVehicle((string) $asset['asset_type']),
        ]);
    }

    public function update(Request $request): Response
    {
        $asset = $this->ownedAsset((int) $request->route('id'));
        $data = $this->validatedAsset($request);
        if (isset($data['error'])) {
            return $this->redirectWith('/account/garage/' . (int) $asset['id'], 'error', (string) $data['error']);
        }

        Database::affecting(
            'UPDATE garage_assets SET asset_type=?, nickname=?, make=?, model=?, model_year=?, registration_jurisdiction=?, '
            . 'tare_kg=?, gvm_kg=?, gcm_kg=?, atm_kg=?, max_braked_towing_kg=?, max_towball_kg=?, notes=?, updated_at=NOW() '
            . 'WHERE id=? AND user_id=? AND deleted_at IS NULL',
            [
                $data['asset_type'], $data['nickname'], $data['make'], $data['model'], $data['model_year'],
                $data['registration_jurisdiction'], $data['tare_kg'], $data['gvm_kg'], $data['gcm_kg'],
                $data['atm_kg'], $data['max_braked_towing_kg'], $data['max_towball_kg'], $data['notes'],
                (int) $asset['id'], $this->userId(),
            ]
        );
        $this->activity((int) $asset['id'], 'updated');

        return $this->redirectWith('/account/garage/' . (int) $asset['id'], 'success', 'Garage details updated.');
    }

    public function remove(Request $request): Response
    {
        $asset = $this->ownedAsset((int) $request->route('id'));
        Database::affecting(
            'UPDATE garage_assets SET deleted_at=NOW(), updated_at=NOW() WHERE id=? AND user_id=? AND deleted_at IS NULL',
            [(int) $asset['id'], $this->userId()]
        );
        return $this->redirectWith('/account/garage', 'success', 'Removed from your Garage. Private documents remain retained until account-data deletion.');
    }

    public function uploadDocument(Request $request): Response
    {
        $asset = $this->ownedAsset((int) $request->route('id'));
        $type = (string) $request->input('document_type', 'other');
        if (!isset(self::DOCUMENT_TYPES[$type])) {
            $type = 'other';
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $label = self::DOCUMENT_TYPES[$type];
        }
        $expiry = $this->dateOrNull($request->input('expires_at'));
        $issueDate = $this->dateOrNull($request->input('issue_date'));

        try {
            $meta = FileStorage::storeUpload(
                $request->file('document') ?? [],
                'garage_documents',
                (array) config('uploads.allowed_document_mimes', []),
                (int) config('uploads.max_document_mb', 10) * 1024 * 1024
            );
        } catch (RuntimeException $e) {
            return $this->redirectWith('/account/garage/' . (int) $asset['id'], 'error', $e->getMessage());
        }

        Database::insert(
            'INSERT INTO garage_documents (garage_asset_id, document_type, label, issuing_authority, issue_date, expires_at, '
            . 'stored_name, original_name, mime_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                (int) $asset['id'], $type, mb_substr($label, 0, 150),
                $this->nullableText($request->input('issuing_authority'), 150), $issueDate, $expiry,
                $meta['stored_name'], $meta['original_name'], $meta['mime_type'], $meta['file_size'],
            ]
        );
        if ($expiry !== null) {
            Database::query(
                "INSERT INTO garage_reminder_preferences (user_id, garage_asset_id, reminder_kind, lead_days, email_enabled, enabled, created_at) "
                . "VALUES (?, ?, 'document_expiry', 30, 1, 1, NOW()) ON DUPLICATE KEY UPDATE enabled=1, updated_at=NOW()",
                [$this->userId(), (int) $asset['id']]
            );
        }
        $this->activity((int) $asset['id'], 'document_uploaded', ['document_type' => $type]);

        return $this->redirectWith('/account/garage/' . (int) $asset['id'], 'success', 'Document added to your private wallet.');
    }

    public function downloadDocument(Request $request): Response
    {
        $document = GarageAsset::ownedDocument((int) $request->input('id'), $this->userId());
        if ($document === null) {
            $this->abort(404);
        }
        return FileStorage::serve(
            'garage_documents',
            (string) $document['stored_name'],
            (string) $document['original_name'],
            (string) $document['mime_type'],
            false
        );
    }

    public function removeDocument(Request $request): Response
    {
        $document = GarageAsset::ownedDocument((int) $request->input('document_id'), $this->userId());
        if ($document === null) {
            $this->abort(404);
        }
        FileStorage::delete('garage_documents', (string) $document['stored_name']);
        Database::query('DELETE FROM garage_documents WHERE id=?', [(int) $document['id']]);
        return $this->redirectWith('/account/garage/' . (int) $document['garage_asset_id'], 'success', 'Document removed.');
    }

    /** @return array<string,mixed> */
    private function validatedAsset(Request $request): array
    {
        $type = (string) $request->input('asset_type', '');
        $nickname = trim((string) $request->input('nickname', ''));
        $jurisdiction = strtoupper(trim((string) $request->input('registration_jurisdiction', '')));
        $year = filter_var($request->input('model_year'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => (int) date('Y') + 2]]);

        if (!isset(GarageAsset::TYPES[$type])) {
            return ['error' => 'Choose a valid vehicle or towable type.'];
        }
        if ($nickname === '') {
            return ['error' => 'Give this vehicle or towable a name.'];
        }
        if ($jurisdiction !== '' && !isset(GarageAsset::JURISDICTIONS[$jurisdiction])) {
            return ['error' => 'Choose a valid registration state or territory.'];
        }
        if ($request->input('model_year') !== '' && $year === false) {
            return ['error' => 'Enter a valid model year.'];
        }

        return [
            'asset_type' => $type,
            'nickname' => mb_substr($nickname, 0, 100),
            'make' => $this->nullableText($request->input('make'), 100),
            'model' => $this->nullableText($request->input('model'), 100),
            'model_year' => $year === false ? null : $year,
            'registration_jurisdiction' => $jurisdiction === '' ? null : $jurisdiction,
            'tare_kg' => $this->positiveNumberOrNull($request->input('tare_kg')),
            'gvm_kg' => $this->positiveNumberOrNull($request->input('gvm_kg')),
            'gcm_kg' => $this->positiveNumberOrNull($request->input('gcm_kg')),
            'atm_kg' => $this->positiveNumberOrNull($request->input('atm_kg')),
            'max_braked_towing_kg' => $this->positiveNumberOrNull($request->input('max_braked_towing_kg')),
            'max_towball_kg' => $this->positiveNumberOrNull($request->input('max_towball_kg')),
            'notes' => $this->nullableText($request->input('notes'), 2000),
        ];
    }

    /** @return array<string,mixed> */
    private function ownedAsset(int $assetId): array
    {
        $asset = GarageAsset::owned($assetId, $this->userId());
        if ($asset === null) {
            $this->abort(404);
        }
        return $asset;
    }

    private function userId(): int
    {
        $userId = (int) (current_user()['id'] ?? 0);
        if ($userId < 1) {
            $this->abort(401);
        }
        return $userId;
    }

    private function positiveNumberOrNull(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $number !== false && $number > 0 && $number <= 9999999.9 ? (float) $number : null;
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $date = trim((string) $value);
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year) ? $date : null;
    }

    /** @param array<string,mixed> $context */
    private function activity(int $assetId, string $type, array $context = []): void
    {
        Database::insert(
            'INSERT INTO garage_brand_activity (user_id, garage_asset_id, brand_id, activity_type, context_json, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [$this->userId(), $assetId, current_brand()->databaseId(), $type, $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR)]
        );
    }
}
