<?php

declare(strict_types=1);

/**
 * Authoritative public legal page bodies (COM-005).
 * Merged over database/seeds/content.php by Seeder and Maintenance seedContent.
 * Effective 18 September 2026 — operator-approved for publication; formal solicitor
 * review may refine wording later without changing the core operating facts.
 */

$va = '<a href="mailto:support@vanassist.com.au">support@vanassist.com.au</a>';
$ts = '<a href="mailto:support@towsmart.com.au">support@towsmart.com.au</a>';
$tw = '<a href="mailto:support@trailerwise.com.au">support@trailerwise.com.au</a>';
$contacts = $va . ', ' . $ts . ' or ' . $tw;
$operator = 'Glen Condren (sole trader), ABN 76 553 821 887';
$brands = 'VanAssist (<code>vanassist.com.au</code>), TowSmart (<code>towsmart.com.au</code>) and TrailerWise (<code>trailerwise.com.au</code>)';

return [
    'privacy' => [
        'page_key' => 'privacy',
        'slug' => 'privacy-policy',
        'title' => 'Privacy policy',
        'body' => '<p><strong>Effective 18 September 2026.</strong> This policy explains how the Assist Platform brands — '
            . $brands . ' — operated by ' . $operator
            . ', collect, hold, use and disclose personal information. We aim to handle personal information consistently with the Australian Privacy Principles and to comply with the <em>Privacy Act 1988</em> (Cth) where it applies. We also consider the <em>Spam Act 2003</em> (Cth) for electronic marketing.</p>'
            . '<h2>Scope</h2>'
            . '<p>This policy covers people who visit or use VanAssist, TowSmart or TrailerWise, create accounts, submit requests, claim provider listings, contact us, or otherwise deal with us online. It does not cover independent providers, parks, payment processors or other third-party sites. LocalTorque and Polaris are not active public brands.</p>'
            . '<h2>What we collect</h2>'
            . '<p>Depending on how you use the Platform, we may collect: name, email, phone, town or postcode; account credentials and roles; MFA enrolment data if enabled; saved listings and towing combinations; service-request details, private addresses and vehicle information you supply; messages and uploaded images; provider or park business details including ABN, licences or insurance evidence when requested; optional device location or derived town/region; IP address, browser/device data, security and rate-limit logs; first-party analytics events; consent and audit records; email delivery records; billing records if paid features are enabled; and, where AI-assisted search is enabled, search prompts and related usage metadata subject to retention controls.</p>'
            . '<h2>How we collect it</h2>'
            . '<p>We collect information directly from you, from people authorised by you, from providers or parks connected to a request, automatically through website use, from lawful public or licensed sources for clearly marked unclaimed listings, and from subprocessors who help us host, send email, resolve maps/routes, or (if enabled) provide AI, CAPTCHA or payments. If you provide information about another person, you must be authorised to do so.</p>'
            . '<h2>Why we use it</h2>'
            . '<p>We use personal information to operate accounts; display directories, stays and discovery tools; process assistance requests and connect users with providers; operate TowSmart calculators and saved combinations; verify provider claims; respond to enquiries; prevent fraud and abuse; measure demand and improve the service; send service messages; send marketing only where permitted; meet legal and dispute obligations; and transfer the business subject to appropriate safeguards if we sell or restructure. We do not sell personal information.</p>'
            . '<h2>Location, matching and AI features</h2>'
            . '<p>Location access is optional where offered. Coordinates may be used to find nearby towns, stays, facilities or providers and to prepare directions. Ranking and matching may use automated rules such as category, distance, service area, availability and verification. These tools assist discovery and do not make legal, credit, employment or insurance decisions. Where AI-assisted search is enabled, prompts may be processed by an approved provider under our configuration. Do not submit unnecessary sensitive information into free-text prompts. Provider verification and material account actions may involve human review.</p>'
            . '<h2>Who receives information</h2>'
            . '<p>We disclose personal information only as reasonably needed to providers or parks you contact or ask us to match; authorised staff and contractors; hosting, CDN/DNS, email, security, analytics, maps/routes and (if enabled) AI, CAPTCHA or payment providers; professional advisers; regulators or law enforcement where required; and a purchaser or successor if the business is transferred, subject to appropriate safeguards. Public provider profiles may contain business information. Unclaimed listings are identified as such. Personal service requests and private customer contact details are not published as open directory listings.</p>'
            . '<h2>Overseas processing</h2>'
            . '<p>Some technology providers may process or back up information outside Australia. Locations can change. We take reasonable steps when selecting and managing providers and will provide further information about relevant providers on request where practicable. Common candidates include BinaryLane hosting (documented Brisbane VPS), Cloudflare, Microsoft email delivery, Google Routes or Maps Platform APIs if enabled, and optional AI, CAPTCHA, payment or backup providers if configured.</p>'
            . '<h2>Cookies and analytics</h2>'
            . '<p>Essential cookies support security, sessions and preferences. When first-party analytics are enabled, a randomly generated session identifier helps us count visits and understand page, search and provider-interest activity. Analytics reporting is designed not to store visitor IP addresses for anonymous reporting; security logs may separately record technical information to protect the service. Optional advertising technologies will be used only as disclosed.</p>'
            . '<h2>Security and retention</h2>'
            . '<p>We use access controls, encryption where appropriate, logging, backups and operational safeguards. No online system is risk-free. We retain personal information only while needed for the purposes described, legitimate business records, dispute handling, security and legal obligations, then delete or de-identify it where reasonably practicable. Backups may retain copies until rotated.</p>'
            . '<h2>Access, correction and deletion</h2>'
            . '<p>You may request access to or correction of your personal information, or ask us to delete information where practicable. Legal, security, fraud-prevention and record-keeping obligations may require some information to be retained. We may need to verify your identity before acting.</p>'
            . '<h2>Direct marketing</h2>'
            . '<p>Marketing is sent only where permitted. You can unsubscribe using the link in a marketing message or by contacting us. Transactional service messages may still be sent when needed. Providers who receive customer details through the Platform must not use them for unsolicited marketing contrary to the Spam Act or Privacy Act.</p>'
            . '<h2>Children</h2>'
            . '<p>The Platform is not directed to children under 18. A parent or guardian should contact us if they believe a child has provided personal information without appropriate authority.</p>'
            . '<h2>Data breaches</h2>'
            . '<p>We assess suspected eligible data breaches. Where the Notifiable Data Breaches scheme applies, we notify affected individuals and the Office of the Australian Information Commissioner as required.</p>'
            . '<h2>Complaints and contact</h2>'
            . '<p>Contact ' . $contacts . ' with privacy questions or complaints. We will acknowledge and investigate within a reasonable time. If you are not satisfied, you may contact the <a href="https://www.oaic.gov.au" rel="nofollow noopener" target="_blank">Office of the Australian Information Commissioner</a>.</p>'
            . '<h2>Changes</h2>'
            . '<p>We may update this policy as the service or law changes. Material changes will be identified on this page and, where appropriate, notified directly to registered users.</p>',
    ],

    'terms' => [
        'page_key' => 'terms',
        'slug' => 'terms-of-use',
        'title' => 'Terms of use',
        'body' => '<p><strong>Effective 18 September 2026.</strong> These terms govern use of the Assist Platform brands — '
            . $brands . ' — operated by ' . $operator
            . ' (together, the “Platform”). By using the Platform you agree to these terms. If you use the Platform for a business, you confirm you are authorised to bind that business.</p>'
            . '<h2>Platform role</h2>'
            . '<p>Unless we expressly say otherwise in writing, we operate a directory, matching, information and coordination platform. We are not the mechanic, gas fitter, electrician, engineer, towing operator, caravan park operator, insurer, certifier, booking agent or seller of third-party goods or services featured on the Platform. A quote, booking, service, payment, warranty or dispute relating to a listed business is an arrangement between you and that business, except for any Platform fee you pay us directly if and when enabled.</p>'
            . '<h2>Brand roles</h2>'
            . '<ul>'
            . '<li><strong>VanAssist</strong> — caravan/RV provider directory, assistance requests, stays/town discovery and related traveller facilities information.</li>'
            . '<li><strong>TowSmart</strong> — towing combination calculators, saved combinations and related safety guidance tools.</li>'
            . '<li><strong>TrailerWise</strong> — trailer services discovery and related ownership/marketplace functionality as enabled on that brand.</li>'
            . '</ul>'
            . '<p>Feature availability differs by brand. LocalTorque and Polaris are not active public brands.</p>'
            . '<h2>Eligibility and accounts</h2>'
            . '<p>You must be at least 18, provide accurate information, protect your account and promptly report unauthorised access. You are responsible for activity under your account unless caused by our failure to take reasonable care. We may require identity or authority verification before approving provider claims or elevated access.</p>'
            . '<h2>Searches, location, maps and AI features</h2>'
            . '<p>Distances, opening information, availability, map results and rankings are estimates and may be incomplete. Confirm details before travelling. Do not use the Platform as an emergency navigation or safety service — in an emergency call 000. Location access is optional where offered. Where AI-assisted search is enabled, outputs are general information aids and can be wrong. They do not replace professional advice.</p>'
            . '<h2>TowSmart calculations</h2>'
            . '<p>TowSmart calculators and catalogue figures are planning and education aids. Results depend on your inputs and available data. They do not certify legality, roadworthiness or engineering compliance and do not replace manufacturer specifications, weighbridge results, licensed engineer assessment or applicable laws.</p>'
            . '<h2>Listings and third-party information</h2>'
            . '<p>Providers are responsible for claimed listing accuracy, licences and insurance. Unclaimed listings use lawful public or licensed sources and may be incomplete. A listing, verification badge, featured position or advertisement is not a guarantee or endorsement. Facility and stay dataset content may come from government or third-party sources — verify before relying on it for travel or safety decisions.</p>'
            . '<h2>Your responsibilities</h2>'
            . '<p>Give accurate request and vehicle information, assess provider suitability, obtain a written scope and price where appropriate, confirm licensing for safety-critical work, and follow manufacturer and road safety guidance.</p>'
            . '<h2>Provider responsibilities</h2>'
            . '<p>Listed providers must also follow the <a href="/provider-terms">Provider terms</a>: keep listings accurate, offer only lawful competent services, maintain required registrations and insurance, protect customer information, and avoid misleading claims or ranking manipulation.</p>'
            . '<h2>Fees, promotions and advertising</h2>'
            . '<p>Core consumer use of public directory and calculator features may be free. Any subscription, listing, advertising or payment fee will be disclosed before you commit. Live charging may be disabled until we enable it. Sponsored placements will be identified. GST treatment follows the operator’s registration status at the time of supply (currently not GST-registered unless that status changes).</p>'
            . '<h2>Acceptable use</h2>'
            . '<p>You must not misuse accounts or personal information, scrape or copy the directory at scale without permission, bypass security, introduce malware, impersonate others, manipulate rankings or reviews, infringe rights, harass others, or use the Platform unlawfully.</p>'
            . '<h2>Content and intellectual property</h2>'
            . '<p>You retain ownership of content you submit and grant us a non-exclusive licence to host, reproduce and display it as needed to operate, promote and improve the Platform. Platform software, branding and original content remain protected.</p>'
            . '<h2>Privacy</h2>'
            . '<p>Our handling of personal information is described in the <a href="/privacy-policy">Privacy policy</a>.</p>'
            . '<h2>Suspension and termination</h2>'
            . '<p>We may restrict or remove content or access where reasonably necessary for security, unlawful conduct, material breach, substantiated risk, non-payment or protection of users. Where appropriate we will give reasons and a reasonable opportunity to respond. You may stop using the Platform at any time.</p>'
            . '<h2>Australian Consumer Law and liability</h2>'
            . '<p>Nothing in these terms excludes, restricts or modifies a guarantee, right or remedy that cannot lawfully be excluded, including under the Australian Consumer Law. To the extent permitted by law, we are not liable for provider or user acts or omissions, your decisions based on Platform information or AI outputs, third-party information, or indirect loss where that exclusion is lawful. Where liability cannot be excluded but may lawfully be limited, it is limited to the remedies permitted by law. Also read the <a href="/disclaimer">Disclaimer</a>.</p>'
            . '<h2>Changes and governing law</h2>'
            . '<p>We may update these terms for legal, security or service changes. Material changes will be notified reasonably where practicable. Queensland law applies and courts in Queensland may hear disputes, subject to any non-excludable right to another forum.</p>'
            . '<h2>Contact</h2>'
            . '<p>Questions or complaints: ' . $contacts . '. If part of these terms is unenforceable, the remainder continues in effect.</p>',
    ],

    'provider-terms' => [
        'page_key' => 'provider-terms',
        'slug' => 'provider-terms',
        'title' => 'Provider terms',
        'body' => '<p><strong>Effective 18 September 2026.</strong> These additional terms apply to businesses and individuals who list, claim, manage or receive leads through '
            . $brands . ', operated by ' . $operator
            . '. They supplement the <a href="/terms-of-use">Terms of use</a> and <a href="/privacy-policy">Privacy policy</a>.</p>'
            . '<h2>Eligibility and authority</h2>'
            . '<p>You must be at least 18 and authorised to represent the business you list or claim. We may require evidence of identity, ABN, licensing or insurance before approving a claim or verification badge.</p>'
            . '<h2>Listing accuracy</h2>'
            . '<p>Keep business name, contacts, services, service areas and trading status accurate. Do not list services you are not qualified, licensed or insured to perform. Prices and availability statements must not be misleading. Unclaimed listings compiled from public or licensed sources may be claimed after verification.</p>'
            . '<h2>Licensing, insurance and safety-critical work</h2>'
            . '<p>You are solely responsible for required licences, registrations and insurance (including gas, electrical, engineering, towing and roadworthy work where applicable). Verification badges mean specified evidence was reviewed at a point in time — not a warranty of future workmanship or ongoing compliance.</p>'
            . '<h2>Customer information and marketing</h2>'
            . '<p>Use customer personal information only to respond to and deliver the requested service, unless you have a separate lawful basis. Do not add Platform-sourced customer details to marketing lists for unsolicited electronic marketing without compliant consent. Electronic marketing must comply with the <em>Spam Act 2003</em> (Cth) and the <em>Privacy Act 1988</em> (Cth).</p>'
            . '<h2>Conduct</h2>'
            . '<p>Deal fairly, provide clear quotes where appropriate, honour Australian Consumer Law obligations, and do not post fake reviews, manipulate rankings or make false verification claims.</p>'
            . '<h2>Fees and advertising</h2>'
            . '<p>Listing may be free during launch periods. Free access is not perpetual. Before listing, subscription, lead or advertising fees apply we will give clear notice. Sponsored placements will be identified to end users. Live payment collection may be disabled until billing is activated.</p>'
            . '<h2>Suspension and removal</h2>'
            . '<p>We may suspend or remove listings, limit lead access or terminate provider accounts for breach, unlawful conduct, substantiated serious complaints, security risk, non-payment, or to protect users.</p>'
            . '<h2>Our role</h2>'
            . '<p>We are not a party to contracts between you and customers unless a separate written agreement says otherwise. We do not guarantee lead volume, conversion, ranking or revenue.</p>'
            . '<h2>Contact</h2>'
            . '<p>' . $contacts . '.</p>',
    ],

    'disclaimer' => [
        'page_key' => 'disclaimer',
        'slug' => 'disclaimer',
        'title' => 'Disclaimer',
        'body' => '<p><strong>Effective 18 September 2026.</strong> VanAssist, TowSmart and TrailerWise help people discover and contact caravan, RV, towing, trailer and traveller services. Information is general and must be checked for your circumstances.</p>'
            . '<h2>Not an emergency service</h2>'
            . '<p>For immediate danger, injury, fire or another emergency call <strong>000</strong>. Do not rely on the Platform for emergency response, remote-area rescue, road-closure warnings or real-time safety instructions.</p>'
            . '<h2>No professional advice</h2>'
            . '<p>Content is not mechanical, electrical, gas, engineering, legal, financial, insurance, roadworthiness or safety advice. Use appropriately qualified and licensed professionals and verify requirements with the relevant authority.</p>'
            . '<h2>Providers and listings</h2>'
            . '<p>We do not guarantee a provider, listing, licence, insurance, availability, price, response time, workmanship or fitness for a particular job. Verification means specified evidence was reviewed at a point in time. Unclaimed listings may be incomplete or out of date.</p>'
            . '<h2>Weights, calculations and specifications</h2>'
            . '<p>TowSmart calculators and catalogue figures are planning aids. Results depend on accurate inputs and do not replace a weighbridge result, manufacturer specification, compliance plate, engineer assessment or legal limit. Allow for passengers, accessories, fluids, cargo and measurement uncertainty.</p>'
            . '<h2>Location, maps and travel information</h2>'
            . '<p>Distances may be estimates. Addresses, routes, fuel, stays, opening hours, access and road suitability may change. Confirm details directly and use current official travel and emergency information.</p>'
            . '<h2>Advertising and ranking</h2>'
            . '<p>Sponsored or featured placements are identified and do not amount to endorsement.</p>'
            . '<h2>Australian Consumer Law</h2>'
            . '<p>Nothing in this disclaimer excludes, restricts or modifies rights or remedies that cannot lawfully be excluded. Read this disclaimer with the <a href="/terms-of-use">Terms of use</a> and <a href="/privacy-policy">Privacy policy</a>.</p>'
            . '<h2>Corrections</h2>'
            . '<p>Report inaccurate or unsafe information to ' . $contacts . '.</p>',
    ],
];
