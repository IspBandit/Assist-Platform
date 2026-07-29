<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailSuppression;

final class EmailPreferenceController extends Controller
{
    public function unsubscribe(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $signature = (string) $request->input('signature', '');
        if (!EmailSuppression::verify($email, $signature)) {
            return Response::html('<main><h1>That preference link is invalid</h1><p>No email settings were changed.</p></main>', 400);
        }

        EmailSuppression::suppressMarketing($email);
        return Response::html('<main><h1>You have been unsubscribed</h1><p>Marketing email to this address has stopped. Essential account and service messages may still be sent.</p></main>');
    }

    public function stopDirectoryNotices(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $signature = (string) $request->input('signature', '');
        if (!EmailSuppression::verifyDirectoryNotice($email, $signature)) {
            return Response::html('<main><h1>That preference link is invalid</h1><p>No email settings were changed.</p></main>', 400);
        }

        EmailSuppression::suppressDirectoryAccuracy($email);
        return Response::html('<main><h1>Directory notices stopped</h1><p>No further listing-accuracy notices will be sent to this address. Essential account or service messages are unaffected.</p></main>');
    }
}
