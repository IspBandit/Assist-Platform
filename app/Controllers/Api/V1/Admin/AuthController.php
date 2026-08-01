<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiAuthService;
use App\Services\Api\AdminApiContext;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiServiceAccountService;

final class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $sessionLabel = $request->input('session_label');
        $fields = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fields['email'] = ['A valid email address is required.'];
        }
        if ($password === '') {
            $fields['password'] = ['Password is required.'];
        }
        if ($fields !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $fields);
        }

        $result = (new AdminApiAuthService())->login(
            $email,
            $password,
            $request,
            is_string($sessionLabel) ? trim($sessionLabel) : null
        );

        return AdminApiEnvelope::data($result);
    }

    public function refresh(Request $request): Response
    {
        $refresh = trim((string) $request->input('refresh_token', ''));
        if ($refresh === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['refresh_token' => ['Refresh token is required.']]
            );
        }

        return AdminApiEnvelope::data((new AdminApiAuthService())->refresh($refresh, $request));
    }

    public function token(Request $request): Response
    {
        $clientKey = trim((string) $request->input('client_key', ''));
        $secret = (string) $request->input('client_secret', '');

        return AdminApiEnvelope::data(
            (new AdminApiServiceAccountService())->issueAccessToken($clientKey, $secret, $request)
        );
    }

    public function logout(Request $request): Response
    {
        $all = filter_var($request->input('all_sessions', false), FILTER_VALIDATE_BOOL);
        $refresh = $request->input('refresh_token');
        (new AdminApiAuthService())->logout(
            is_string($refresh) ? $refresh : null,
            AdminApiContext::accessTokenId(),
            $request,
            $all === true
        );

        return AdminApiEnvelope::data(['logged_out' => true]);
    }

    public function me(Request $request): Response
    {
        if (!AdminApiContext::isHuman() && !AdminApiContext::isService()) {
            throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
        }

        $payload = [
            'scopes' => AdminApiContext::scopes(),
            'actor_type' => AdminApiContext::actorType(),
        ];

        if (AdminApiContext::isHuman()) {
            $user = AdminApiContext::user();
            if ($user === null) {
                throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
            }
            $payload['user'] = (new AdminApiAuthService())->publicUser($user);
        } else {
            $client = AdminApiContext::client();
            if ($client === null) {
                throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
            }
            $payload['client'] = (new AdminApiServiceAccountService())->publicClient($client);
        }

        return AdminApiEnvelope::data($payload);
    }

    public function sessions(Request $request): Response
    {
        $userId = AdminApiContext::userId();
        if ($userId === null) {
            throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
        }

        return AdminApiEnvelope::collection((new AdminApiAuthService())->listSessions($userId));
    }

    public function revokeSession(Request $request): Response
    {
        $userId = AdminApiContext::userId();
        if ($userId === null) {
            throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
        }
        $sessionId = trim((string) $request->route('id', ''));
        if ($sessionId === '') {
            throw new AdminApiException(404, 'not_found', 'Session not found.');
        }

        (new AdminApiAuthService())->revokeSession($userId, $sessionId);

        return AdminApiEnvelope::data(['revoked' => true, 'id' => $sessionId]);
    }
}
