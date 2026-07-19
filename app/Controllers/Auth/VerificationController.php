<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

final class VerificationController extends Controller
{
    public function verify(Request $request): Response
    {
        $userId = (int) $request->query('id');
        $token = (string) $request->query('token');

        $record = $userId > 0 && $token !== '' ? Database::selectOne(
            'SELECT * FROM email_verifications WHERE user_id = ? AND token_hash = ? AND verified_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$userId, hash('sha256', $token)]
        ) : null;

        if ($record === null) {
            Session::flash('error', 'This verification link is invalid or has expired.');
            return $this->redirect('/');
        }

        Database::query('UPDATE email_verifications SET verified_at = NOW() WHERE id = ?', [$record['id']]);
        User::update($userId, ['email_verified_at' => date('Y-m-d H:i:s')]);

        Session::flash('success', 'Your email address has been verified. Thank you.');
        return $this->redirect(auth()->check() ? 'account' : 'login');
    }
}
