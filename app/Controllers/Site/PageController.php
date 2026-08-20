<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Provider;
use App\Models\Town;
use App\Services\ClaimFirstOnboardingService;
use App\Services\EmailQueue;
use App\Services\Settings;
use Throwable;

/**
 * Renders editable CMS pages (about, contact, legal) and informational
 * landing pages. Sections delivered in later build phases are shown as a
 * clear "opening soon" page rather than a broken route.
 */
final class PageController extends Controller
{
    /** Render a CMS-managed page by its slug. */
    public function cms(Request $request): Response
    {
        $slug = (string) ($request->route('page') ?? ltrim($request->path(), '/'));
        if (current_brand()->id() !== 'vanassist') {
            return $this->view('brands.page', [
                'title' => $this->brandPageTitle($slug),
                'slug' => $slug,
                'brand' => current_brand(),
                'canonical' => current_brand()->url() . '/' . $slug,
            ]);
        }
        $page = $this->findPage($slug);

        if ($page === null) {
            $this->abort(404, 'Page not found.');
        }

        return $this->view('public.page', [
            'title'           => $page['seo_title'] ?: $page['title'],
            'metaDescription' => $page['seo_description'] ?? null,
            'page'            => $page,
        ]);
    }

    private function brandPageTitle(string $slug): string
    {
        return match ($slug) {
            'about' => 'About ' . current_brand()->name(),
            'contact' => 'Contact ' . current_brand()->name(),
            'privacy-policy' => 'Privacy policy',
            'terms-of-use' => 'Terms of use',
            'provider-terms' => 'Provider terms',
            'disclaimer' => 'Important disclaimer',
            'safety-information' => 'Safety information',
            'complaints-process' => 'Complaints process',
            'accessibility-statement' => 'Accessibility statement',
            default => current_brand()->name(),
        };
    }

    public function howItWorks(Request $request): Response
    {
        return $this->view('public.how-it-works', ['title' => 'How VanAssist works']);
    }

    public function forProviders(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist') {
            return $this->view('brands.for-providers', ['title' => 'For ' . current_brand()->name() . ' businesses']);
        }
        return $this->view('public.for-providers', [
            'title' => 'For providers — turn regional demand into organised runs',
        ]);
    }

    /** Provider "register interest" form (captures a warm prospect for the CRM). */
    public function providerInterest(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist') {
            return $this->view('brands.provider-interest', ['title' => 'Register your business with ' . current_brand()->name(), 'errors' => Session::errors()]);
        }

        $listingSlug = trim((string) $request->input('listing'));
        $listingProvider = $listingSlug !== '' ? Provider::findPublicBySlug($listingSlug) : null;

        if ($listingProvider !== null || !ClaimFirstOnboardingService::enabled()) {
            return $this->view('public.provider-interest', [
                'title'  => $listingProvider !== null
                    ? 'Request to claim or correct this listing'
                    : 'Register your interest — VanAssist for providers',
                'errors' => Session::errors(),
                'listingProvider' => $listingProvider,
                'step' => 'form',
                'matches' => [],
                'search' => [],
            ]);
        }

        $step = trim((string) $request->input('step', 'search'));
        if ($step === 'form' && Session::get('claim_first_confirmed_none')) {
            return $this->view('public.provider-interest', [
                'title' => 'Register your interest — VanAssist for providers',
                'errors' => Session::errors(),
                'listingProvider' => null,
                'step' => 'form',
                'matches' => [],
                'search' => Session::get('claim_first_search', []),
            ]);
        }

        if ($step === 'matches' && Session::has('claim_first_search')) {
            $search = Session::get('claim_first_search', []);
            $service = new ClaimFirstOnboardingService();
            $matches = $service->searchMatches(
                (string) ($search['business_name'] ?? ''),
                isset($search['base_town_id']) ? (int) $search['base_town_id'] : null,
                (string) ($search['town'] ?? ''),
            );

            return $this->view('public.provider-interest', [
                'title' => 'Is this your business?',
                'errors' => Session::errors(),
                'listingProvider' => null,
                'step' => 'matches',
                'matches' => $matches,
                'search' => $search,
            ]);
        }

        return $this->view('public.provider-interest', [
            'title' => 'Find your business listing',
            'errors' => Session::errors(),
            'listingProvider' => null,
            'step' => 'search',
            'matches' => [],
            'search' => Session::get('claim_first_search', []),
        ]);
    }

    public function searchProviderMatches(Request $request): Response
    {
        if (!ClaimFirstOnboardingService::enabled() || current_brand()->id() !== 'vanassist') {
            return $this->redirect('/for-providers/register');
        }

        $business = trim((string) $request->input('business_name'));
        $town = trim((string) $request->input('town'));
        $townId = (int) $request->input('region_id') ?: null;
        if ($townId === null && $town !== '') {
            $townMatches = Town::searchActive($town);
            $townId = isset($townMatches[0]['id']) ? (int) $townMatches[0]['id'] : null;
        }

        if ($business === '') {
            Session::flashErrors(['business_name' => 'Please enter your business name to search.']);
            Session::flashInput($request->only(['business_name', 'town', 'region_id', 'region']));
            return $this->redirect('/for-providers/register');
        }

        Session::set('claim_first_search', [
            'business_name' => $business,
            'town' => $town,
            'region' => trim((string) $request->input('region')),
            'base_town_id' => $townId,
        ]);
        Session::forget('claim_first_confirmed_none');

        return $this->redirect('/for-providers/register?step=matches');
    }

    public function confirmNewProviderListing(Request $request): Response
    {
        if (!ClaimFirstOnboardingService::enabled() || current_brand()->id() !== 'vanassist') {
            return $this->redirect('/for-providers/register');
        }

        if (!$request->input('confirm_none')) {
            Session::flash('error', 'Please confirm that none of the suggested listings match your business.');
            return $this->redirect('/for-providers/register?step=matches');
        }

        Session::set('claim_first_confirmed_none', true);

        return $this->redirect('/for-providers/register?step=form');
    }

    /**
     * Stores a public provider-interest submission as a warm prospect in the
     * outreach CRM (outreach_status = interested) and notifies the site inbox.
     */
    public function submitProviderInterest(Request $request): Response
    {
        // Honeypot: silently accept bot submissions without storing them.
        if (trim((string) $request->input('company_url')) !== '') {
            return $this->redirectWith('/for-providers/register', 'success', 'Thanks — we\'ll be in touch.');
        }

        $business = trim((string) $request->input('business_name'));
        $contact  = trim((string) $request->input('contact_name'));
        $email    = trim((string) $request->input('email'));
        $phone    = trim((string) $request->input('phone'));
        $town     = trim((string) $request->input('town'));
        $region   = trim((string) $request->input('region'));
        $services = trim((string) $request->input('services'));
        $message  = trim((string) $request->input('message'));
        $listingSlug = trim((string) $request->input('listing_slug'));
        $listingProvider = $listingSlug !== '' ? Provider::findPublicBySlug($listingSlug) : null;
        $marketingOptIn = $request->input('marketing_opt_in') ? 1 : 0;

        // Explicit mobile / workshop questions drive the stored service model.
        $offersMobile = (string) $request->input('offers_mobile');
        $hasWorkshop  = (string) $request->input('has_workshop');
        $isMobile   = $offersMobile === 'yes';
        $isWorkshop = $hasWorkshop === 'yes';
        $model = $isMobile && $isWorkshop ? 'both' : ($isMobile ? 'mobile' : ($isWorkshop ? 'workshop' : 'unknown'));

        $errors = [];
        if ($business === '') {
            $errors['business_name'] = 'Please enter your business name.';
        }
        if ($email === '' && $phone === '') {
            $errors['email'] = 'Please give us an email or phone so we can reach you.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'That email address does not look valid.';
        }
        if (!in_array($offersMobile, ['yes', 'no'], true)) {
            $errors['offers_mobile'] = 'Please let us know if you offer a mobile service.';
        }

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->only(['business_name', 'contact_name', 'email', 'phone', 'town', 'region', 'region_id', 'services', 'message', 'offers_mobile', 'has_workshop', 'listing_slug', 'marketing_opt_in']));
            return $this->redirect('/for-providers/register' . ($listingProvider !== null ? '?listing=' . rawurlencode((string) $listingProvider['slug']) : '?step=form'));
        }

        $submission = [
            'business_name' => $business,
            'contact_name' => $contact,
            'email' => $email,
            'phone' => $phone,
            'town' => $town,
            'region' => $region,
            'region_id' => (int) $request->input('region_id') ?: null,
            'base_town_id' => (int) $request->input('region_id') ?: null,
            'services' => $services,
            'message' => $message,
            'service_model' => $model,
            'marketing_opt_in' => $marketingOptIn,
        ];

        $confirmedNone = $listingProvider === null
            && ClaimFirstOnboardingService::enabled()
            && (bool) Session::get('claim_first_confirmed_none');

        if ($listingProvider === null && ClaimFirstOnboardingService::enabled() && !$confirmedNone) {
            Session::flash('error', 'Please search for an existing listing and confirm whether it matches your business before registering a new one.');
            return $this->redirect('/for-providers/register');
        }

        $onboarding = new ClaimFirstOnboardingService();
        $evaluation = $onboarding->evaluateSubmission($submission, $confirmedNone);
        if ($evaluation['outcome'] === 'duplicate_hold' && $evaluation['duplicate'] !== null) {
            try {
                $hold = $onboarding->createDuplicateHold($submission, $evaluation['duplicate']);
            } catch (Throwable) {
                return $this->redirectWith('/for-providers/register?step=form', 'error', 'Sorry, something went wrong saving your details. Please email us instead.');
            }

            Session::forget('claim_first_search');
            Session::forget('claim_first_confirmed_none');
            $this->notifyInterest($business, $contact, $email, $phone, $town, $region, $services, $message . ' [duplicate hold #' . ($hold['provider_id'] ?? 0) . ']', $model);

            return $this->redirectWith(
                '/for-providers/register',
                'success',
                'Thanks — your request is recorded for review. We found a similar existing listing and will verify before publishing anything new.'
            );
        }

        // Combine the base town and its region for the CRM "towns serviced" field.
        $based = $town;
        if ($region !== '') {
            $based = $town !== '' ? $town . ' (' . $region . ')' : $region;
        }

        $noteParts = ['Registered interest via the ' . current_brand()->name() . ' website (For providers page).'];
        if ($based !== '') {
            $noteParts[] = 'Based in: ' . $based;
        }
        $noteParts[] = 'Offers mobile service: ' . ($isMobile ? 'Yes' : 'No') . '; has workshop: ' . ($isWorkshop ? 'Yes' : 'No') . '.';
        if ($message !== '') {
            $noteParts[] = 'Message: ' . $message;
        }
        if ($listingProvider !== null) {
            $noteParts[] = 'Requested claim/correction for provider #' . (int) $listingProvider['id'] . ' (' . (string) $listingProvider['slug'] . '). Ownership is not granted by this submission.';
        }
        if ($marketingOptIn) {
            $noteParts[] = 'Optional promotional email consent given through the provider registration form.';
        }

        try {
            Database::query(
                'INSERT INTO provider_prospects (business_name, contact_name, phone, email, services_observed, '
                . 'service_model, towns_serviced, source, outreach_status, consent_recorded, marketing_consented_at, marketing_consent_basis, marketing_consent_evidence, notes, created_at, updated_at) '
                . "VALUES (?, ?, ?, ?, ?, ?, ?, 'other', 'interested', ?, " . ($marketingOptIn ? 'NOW()' : 'NULL') . ", ?, ?, ?, NOW(), NOW())",
                [
                    $business,
                    $contact ?: null,
                    $phone ?: null,
                    $email ?: null,
                    $services ?: null,
                    $model,
                    $based ?: null,
                    $marketingOptIn,
                    $marketingOptIn ? 'express_web' : null,
                    $marketingOptIn ? 'Optional provider marketing checkbox affirmatively selected on the VanAssist provider registration form.' : null,
                    implode("\n", $noteParts),
                ]
            );
        } catch (Throwable) {
            return $this->redirectWith('/for-providers/register', 'error', 'Sorry, something went wrong saving your details. Please email us instead.');
        }

        $this->notifyInterest($business, $contact, $email, $phone, $town, $region, $services, $message, $model);

        Session::forget('claim_first_search');
        Session::forget('claim_first_confirmed_none');

        return $this->redirectWith('/for-providers/register', 'success', 'Thanks ' . ($contact !== '' ? $contact : $business) . '! Your request is recorded. We will check your authority before any listing access is granted.');
    }

    private function notifyInterest(string $business, string $contact, string $email, string $phone, string $town, string $region, string $services, string $message, string $model): void
    {
        $to = (string) Settings::get('contact_email', 'support@vanassist.com.au');
        if ($to === '') {
            return;
        }
        $rows = [
            'Business'      => $business,
            'Contact'       => $contact,
            'Email'         => $email,
            'Phone'         => $phone,
            'Town'          => $town,
            'Region'        => $region,
            'Services'      => $services,
            'Service model' => $model,
            'Message'       => $message,
        ];
        $html = '<h2>New provider interest</h2><table cellpadding="6" style="border-collapse:collapse">';
        $text = "New provider interest\n";
        foreach ($rows as $label => $value) {
            if (trim($value) === '') {
                continue;
            }
            $html .= '<tr><td style="font-weight:bold;vertical-align:top">' . e($label) . '</td><td>' . nl2br(e($value)) . '</td></tr>';
            $text .= $label . ': ' . $value . "\n";
        }
        $html .= '</table><p style="color:#8a8f94">Saved to Admin → Provider prospects (status: interested).</p>';
        $text .= "\nSaved to Admin > Provider prospects (status: interested).";

        try {
            EmailQueue::queueRaw($to, current_brand()->name(), current_brand()->name() . ' provider interest: ' . $business, $html, $text, 'provider_interest');
        } catch (Throwable) {
            // Non-fatal: the prospect is already stored for follow-up.
        }
    }

    public function forCaravanParks(Request $request): Response
    {
        return $this->view('public.for-parks', [
            'title' => 'For caravan parks — help your guests find the right service',
        ]);
    }

    /** Generic placeholder for sections delivered in upcoming phases. */
    public function comingSoon(Request $request): Response
    {
        $titles = [
            '/find'                => 'Find a service',
            '/service-runs'        => 'Service runs',
            '/providers'           => 'Provider directory',
            '/request-assistance'  => 'Request assistance',
        ];
        $path = $request->path();
        $title = $titles[$path] ?? 'Coming soon';

        return $this->view('public.coming-soon', [
            'title'   => $title,
            'heading' => $title,
        ]);
    }

    private function findPage(string $slug): ?array
    {
        try {
            return Database::selectOne(
                'SELECT * FROM content_pages WHERE slug = ? AND is_published = 1 LIMIT 1',
                [$slug]
            );
        } catch (Throwable) {
            return null;
        }
    }
}
