<?php

declare(strict_types=1);

/**
 * Default CMS content: homepage blocks, static/legal pages and FAQs.
 * All of this is editable through the admin portal (Content → Pages & Blocks)
 * after install. Bodies are HTML.
 */

return [
    // Homepage editable sections (block_group = 'homepage'), rendered in the
    // "How VanAssist works" grid in order of sort_order.
    'homepage_blocks' => [
        ['block_key' => 'intro_owners', 'title' => 'For caravan owners',
         'subtitle' => 'Tell us what you need',
         'body' => 'Register the service you need and the town or postcode you are in. VanAssist finds specialists already covering your area — mobile and workshop — and lets you know when a provider is planning a visit to your region.',
         'button_label' => 'Request assistance', 'button_url' => '/request-assistance', 'sort_order' => 1],
        ['block_key' => 'intro_providers', 'title' => 'For service providers',
         'subtitle' => 'Turn regional demand into organised runs',
         'body' => 'See where demand is building across regional towns and plan profitable service runs around real, registered requests. You stay in control of which jobs you accept. Free founding-provider access during the initial launch.',
         'button_label' => 'Join as a provider', 'button_url' => '/for-providers', 'sort_order' => 2],
        ['block_key' => 'intro_parks', 'title' => 'For caravan parks',
         'subtitle' => 'Help your guests find the right service',
         'body' => 'Refer guests to trusted local caravan and RV services in seconds, and see upcoming provider visits near your park — without maintaining outdated contact lists.',
         'button_label' => 'Partner with us', 'button_url' => '/for-caravan-parks', 'sort_order' => 3],
        ['block_key' => 'trust', 'title' => 'Trust and verification',
         'subtitle' => 'Know who you are dealing with',
         'body' => 'Providers can verify their licences and insurance; verified businesses display a badge. VanAssist is a matching and coordination platform — it does not perform the work or guarantee workmanship, so always confirm suitability before booking.',
         'button_label' => 'How it works', 'button_url' => '/how-it-works', 'sort_order' => 4],
    ],

    // Static / legal pages (page_key, slug, title, body). Seeded as published,
    // system pages. Legal pages are practical defaults — have them reviewed by a
    // legal professional before relying on them.
    'pages' => [
        ['page_key' => 'about', 'slug' => 'about', 'title' => 'About VanAssist',
         'body' =>
            '<p>VanAssist helps owners of caravans, RVs, motorhomes and camper trailers find the right service and repair providers across regional Australia — wherever the road takes you.</p>'
            . '<h2>Why VanAssist exists</h2>'
            . '<p>When something goes wrong with your van far from home, finding a trustworthy specialist who actually covers your area can be slow and stressful. Local knowledge is scattered across forums, social media and word of mouth. VanAssist brings it together: tell us what you need and where you are, and we surface the providers who service that town or region.</p>'
            . '<h2>How it works</h2>'
            . '<ol>'
            . '<li><strong>Tell us what you need.</strong> Choose a service and enter your town or postcode.</li>'
            . '<li><strong>See who covers your area.</strong> We list mobile and workshop providers based in or servicing your location, with an approximate distance and a clear "mobile service" indicator.</li>'
            . '<li><strong>Connect.</strong> Contact a provider directly, or register a request so relevant providers — and upcoming service "runs" — can reach you.</li>'
            . '</ol>'
            . '<h2>Who it is for</h2>'
            . '<ul>'
            . '<li><strong>Caravan &amp; RV owners</strong> — find help fast, on the road or at home.</li>'
            . '<li><strong>Service providers</strong> — turn regional demand into organised, profitable runs.</li>'
            . '<li><strong>Caravan parks</strong> — refer guests to trusted local services in seconds.</li>'
            . '</ul>'
            . '<h2>Where we operate</h2>'
            . '<p>VanAssist covers every Australian state and territory, with our first on-the-ground launch focused on Central Queensland and the Wide Bay–Burnett region. Search any town or postcode to see who covers your area.</p>'
            . '<h2>Trust &amp; verification</h2>'
            . '<p>Providers can verify their licences and insurance with us; verified businesses display a badge. Some listings are shown as <em>unclaimed</em> — compiled from public sources so you can still find help in your area. Always confirm details directly with the business before booking. VanAssist is a matching and coordination platform and does not perform the work or guarantee workmanship.</p>'
            . '<h2>About the operator</h2>'
            . '<p>VanAssist is operated by Glen Condren (sole trader), ABN 76 553 821 887. It is free for caravan owners, service providers and participating caravan parks during the initial launch.</p>'
            . '<p><a href="/request-assistance">Request assistance</a> &middot; <a href="/for-providers">Register as a provider</a> &middot; <a href="/contact">Contact us</a></p>'],

        ['page_key' => 'contact', 'slug' => 'contact', 'title' => 'Contact us',
         'body' =>
            '<p>We are here to help. The fastest way to get the right service is to <a href="/request-assistance">register a request</a> — but you are welcome to reach us directly.</p>'
            . '<ul class="list-plain">'
            . '<li><strong>Email:</strong> <a href="mailto:vanassist@condrendigital.com.au">vanassist@condrendigital.com.au</a></li>'
            . '<li><strong>Phone:</strong> <a href="tel:0448007334">0448 007 334</a></li>'
            . '</ul>'
            . '<p>We aim to respond within one business day.</p>'
            . '<p>Are you a service provider? <a href="/for-providers/register">Register your interest</a> to join during the free launch.</p>'
            . '<p class="muted">VanAssist is operated by Glen Condren (sole trader), ABN 76 553 821 887.</p>'],

        ['page_key' => 'faqs', 'slug' => 'faqs', 'title' => 'Frequently asked questions',
         'body' =>
            '<p>Answers to common questions about using VanAssist to find caravan and RV service across regional Australia. Can\'t find what you need? <a href="/contact">Contact us</a> and we\'ll help.</p>'],

        ['page_key' => 'privacy', 'slug' => 'privacy-policy', 'title' => 'Privacy policy',
         'body' =>
            '<p>VanAssist respects your privacy and handles personal information in line with the Australian Privacy Principles (APPs) under the <em>Privacy Act 1988</em> (Cth). This policy explains what we collect, why, and your rights. It applies to the VanAssist website and services operated by Glen Condren (sole trader), ABN 76 553 821 887 ("VanAssist", "we", "us").</p>'
            . '<h2>Information we collect</h2>'
            . '<ul>'
            . '<li><strong>Details you give us:</strong> name, email, phone, town/postcode, vehicle details and the description of the service you need when you register a request or an account.</li>'
            . '<li><strong>Provider details:</strong> business name, contact details, services, service areas, and (privately) any licence or insurance documents you upload for verification.</li>'
            . '<li><strong>Usage data:</strong> pages viewed, searches made and basic device/browser information, used to operate and improve the service.</li>'
            . '</ul>'
            . '<h2>How we use your information</h2>'
            . '<ul>'
            . '<li>To match your request with relevant service providers and coordinate service "runs".</li>'
            . '<li>To operate your account, respond to enquiries and send service-related messages.</li>'
            . '<li>To verify provider licences and insurance, and to maintain trust and safety.</li>'
            . '<li>To understand demand by area and improve our coverage and the website.</li>'
            . '</ul>'
            . '<h2>When we share information</h2>'
            . '<p>Your name and exact contact details are <strong>not</strong> shown publicly. When you are matched with a provider and consent to being contacted, we share the details needed to arrange the service. We may use trusted service providers (e.g. email delivery and hosting) who process data on our behalf, and we may disclose information where required by law.</p>'
            . '<h2>Unclaimed business listings</h2>'
            . '<p>Some provider listings are compiled from publicly available sources and marked "unclaimed". We display only the contact details a business already publishes. A business can claim, correct or request removal of its listing at any time by contacting us.</p>'
            . '<h2>Cookies &amp; analytics</h2>'
            . '<p>We use essential cookies to keep you signed in and secure the site. If analytics are enabled, we use aggregated, non-identifying usage data to improve the service. You can control cookies through your browser settings.</p>'
            . '<h2>Security &amp; retention</h2>'
            . '<p>We take reasonable steps to protect your information from misuse, loss and unauthorised access, and we keep it only as long as needed for the purposes above or as required by law.</p>'
            . '<h2>Access, correction &amp; complaints</h2>'
            . '<p>You can ask to access or correct your personal information, or raise a privacy concern, by emailing <a href="mailto:vanassist@condrendigital.com.au">vanassist@condrendigital.com.au</a>. If you are not satisfied with our response, you may contact the Office of the Australian Information Commissioner (OAIC) at <a href="https://www.oaic.gov.au" rel="nofollow noopener" target="_blank">oaic.gov.au</a>.</p>'
            . '<h2>Changes</h2>'
            . '<p>We may update this policy from time to time. The current version will always be available on this page.</p>'
            . '<p class="muted">This policy is provided as a practical default and should be reviewed by a legal professional for your circumstances.</p>'],

        ['page_key' => 'terms', 'slug' => 'terms-of-use', 'title' => 'Terms of use',
         'body' =>
            '<p>These terms govern your use of the VanAssist website and services, operated by Glen Condren (sole trader), ABN 76 553 821 887. By using VanAssist you agree to these terms.</p>'
            . '<h2>What VanAssist is</h2>'
            . '<p>VanAssist is a <strong>matching and coordination platform</strong>. We help connect caravan and RV owners with service providers and help providers plan service runs. We are not a party to any agreement you make with a provider, we do not perform the work, set provider pricing, or guarantee workmanship.</p>'
            . '<h2>Accounts &amp; eligibility</h2>'
            . '<p>You must provide accurate information and keep your account secure. You are responsible for activity under your account. You must be able to form a binding contract to use the service.</p>'
            . '<h2>Acceptable use</h2>'
            . '<ul>'
            . '<li>Do not misuse the service, attempt to disrupt it, or use it unlawfully.</li>'
            . '<li>Do not harvest other users\' details or send unsolicited marketing.</li>'
            . '<li>Provide truthful information in requests and listings.</li>'
            . '</ul>'
            . '<h2>Bookings are between you and the provider</h2>'
            . '<p>Any quote, booking, payment, work and warranty is a direct arrangement between you and the provider. Please confirm licensing, insurance, scope and price before work begins.</p>'
            . '<h2>Provider &amp; unclaimed listings</h2>'
            . '<p>Listings may include businesses compiled from public sources and marked "unclaimed". Information may be incomplete or out of date — confirm details directly with the business.</p>'
            . '<h2>Liability</h2>'
            . '<p>To the extent permitted by law, VanAssist is provided "as is" and we exclude liability for loss arising from your use of the service or from dealings with providers. Nothing in these terms excludes rights you have under the Australian Consumer Law that cannot lawfully be excluded.</p>'
            . '<h2>Privacy</h2>'
            . '<p>Our handling of personal information is described in our <a href="/privacy-policy">Privacy policy</a>.</p>'
            . '<h2>Changes &amp; governing law</h2>'
            . '<p>We may update these terms; the current version is always on this page. These terms are governed by the laws of Queensland, Australia.</p>'
            . '<p class="muted">These terms are a practical default and should be reviewed by a legal professional before launch.</p>'],

        ['page_key' => 'provider-terms', 'slug' => 'provider-terms', 'title' => 'Provider terms',
         'body' =>
            '<p>These additional terms apply to service providers listed or registered on VanAssist, and supplement our <a href="/terms-of-use">Terms of use</a>.</p>'
            . '<h2>Listing accuracy</h2>'
            . '<p>Keep your business name, contact details, services and service areas accurate and current. Do not list services you are not qualified or licensed to provide.</p>'
            . '<h2>Licensing &amp; insurance</h2>'
            . '<p>You are responsible for holding all licences, registrations and insurances required for your work (for example gas, electrical and roadworthy/safety-certificate work). Verification badges indicate documents we have sighted; they are not a warranty of your work.</p>'
            . '<h2>Customer information &amp; the Spam Act</h2>'
            . '<p>Use customer details only to provide the requested service. Do not use them for unsolicited marketing. Any electronic marketing must comply with the <em>Spam Act 2003</em> (Cth) and the <em>Privacy Act 1988</em> (Cth).</p>'
            . '<h2>Conduct</h2>'
            . '<p>Deal fairly and professionally with customers, provide clear quotes, and honour your obligations under the Australian Consumer Law.</p>'
            . '<h2>Claiming an unclaimed listing</h2>'
            . '<p>If your business appears as an unclaimed listing, you may claim it to manage your details. We may ask you to verify ownership.</p>'
            . '<h2>Fees</h2>'
            . '<p>VanAssist is free for providers during the initial launch. We will give clear notice before any fees apply, and you may decline and remove your listing at that time.</p>'
            . '<h2>Suspension &amp; removal</h2>'
            . '<p>We may suspend or remove listings that breach these terms, receive substantiated complaints, or misrepresent qualifications.</p>'
            . '<p class="muted">These terms are a practical default and should be reviewed by a legal professional before launch.</p>'],

        ['page_key' => 'disclaimer', 'slug' => 'disclaimer', 'title' => 'Disclaimer',
         'body' =>
            '<p>VanAssist is a matching and coordination platform that helps you find caravan and RV service providers. We are not the service provider.</p>'
            . '<ul>'
            . '<li>We do not perform repairs or services and do not guarantee any provider\'s workmanship, availability or pricing.</li>'
            . '<li>Listing on VanAssist is not an endorsement. Always confirm a provider\'s licensing, insurance and suitability for your job before booking.</li>'
            . '<li>"Unclaimed" listings are compiled from public sources and may be incomplete or out of date — verify details directly with the business.</li>'
            . '<li>Distances shown are approximate (straight-line) estimates to help you compare options, not driving distances.</li>'
            . '</ul>'
            . '<p>For safety-critical work such as gas, electrical and structural repairs, use appropriately licensed professionals — see our <a href="/safety-information">Safety information</a>.</p>'],

        ['page_key' => 'safety', 'slug' => 'safety-information', 'title' => 'Safety information',
         'body' =>
            '<p>Caravans and RVs combine gas, electrical, structural and towing systems. Much of this work is safety-critical and, in most states, legally must be done by a licensed professional. The following is general guidance only.</p>'
            . '<h2>Gas (LPG)</h2>'
            . '<p>Gas installation and repairs must be carried out by a licensed gas fitter. Never ignore the smell of gas. Fit and test a gas/LPG alarm and a carbon monoxide alarm, and have appliances and lines checked periodically and after any incident.</p>'
            . '<h2>Electrical</h2>'
            . '<p>240-volt wiring and appliances must be worked on by a licensed electrician. 12-volt, solar, battery and DC-DC systems should be installed correctly to avoid fire risk — use a qualified auto electrician for anything you are unsure about.</p>'
            . '<h2>Chassis, brakes, bearings &amp; towing</h2>'
            . '<p>Have brakes, wheel bearings, suspension and the coupling inspected regularly. Stay within your vehicle\'s and van\'s rated limits (Tare, ATM, GTM, ball weight and tow capacity). Check tyre age, pressures and load rating before long trips.</p>'
            . '<h2>Before you travel</h2>'
            . '<ul>'
            . '<li>Check tyres (including the spare), wheel nuts and bearings.</li>'
            . '<li>Test brakes, lights and the breakaway system.</li>'
            . '<li>Check gas connections and that alarms work.</li>'
            . '<li>Secure your load and confirm weights are within limits.</li>'
            . '</ul>'
            . '<h2>In an emergency</h2>'
            . '<p>In a life-threatening emergency call <strong>000</strong>. For breakdowns, consider a roadside assistance provider; many state motoring clubs offer caravan-friendly cover.</p>'],

        ['page_key' => 'complaints', 'slug' => 'complaints-process', 'title' => 'Complaints process',
         'body' =>
            '<p>We want VanAssist to be trustworthy. If something goes wrong, here is how to raise it.</p>'
            . '<h2>1. Contact us</h2>'
            . '<p>Email <a href="mailto:vanassist@condrendigital.com.au">vanassist@condrendigital.com.au</a> with the details. Please include your name, the provider or request involved, dates, and what happened.</p>'
            . '<h2>2. What happens next</h2>'
            . '<p>We aim to acknowledge complaints within two business days and to resolve them as quickly as is reasonable. We may contact the provider involved to understand both sides.</p>'
            . '<h2>3. Disputes with a provider</h2>'
            . '<p>Because work is arranged directly between you and the provider, we encourage you to raise service or workmanship issues with them first. We can help facilitate contact and will act on substantiated concerns about a listing.</p>'
            . '<h2>4. External avenues</h2>'
            . '<p>If we cannot resolve your concern, you may contact your state or territory consumer protection agency (for example, the Office of Fair Trading in Queensland) or the ACCC at <a href="https://www.accc.gov.au" rel="nofollow noopener" target="_blank">accc.gov.au</a>.</p>'],

        ['page_key' => 'accessibility', 'slug' => 'accessibility-statement', 'title' => 'Accessibility statement',
         'body' =>
            '<p>VanAssist aims to be usable by everyone, including people travelling on phones and tablets in regional areas.</p>'
            . '<h2>Our approach</h2>'
            . '<p>We aim to meet the practical expectations of the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA, including readable text, sufficient colour contrast, keyboard navigation, descriptive links and labelled form fields.</p>'
            . '<h2>Known limitations</h2>'
            . '<p>Some third-party content and map features may not yet fully meet these goals. We are continually improving.</p>'
            . '<h2>Feedback</h2>'
            . '<p>If you have trouble accessing any part of VanAssist, please email <a href="mailto:vanassist@condrendigital.com.au">vanassist@condrendigital.com.au</a> and we will help and work to fix the issue.</p>'],
    ],

    'faqs' => [
        ['category' => 'customers', 'question' => 'How much does VanAssist cost?',
         'answer' => 'VanAssist is free for caravan owners, service providers and participating caravan parks during the initial launch.', 'sort_order' => 1],
        ['category' => 'customers', 'question' => 'How do I find a provider?',
         'answer' => 'Choose a service and enter your town or postcode. We show mobile and workshop providers based in or servicing your area, with an approximate distance and a clear "mobile service" indicator. You can contact a provider directly or register a request.', 'sort_order' => 2],
        ['category' => 'customers', 'question' => 'What if no provider is listed for my area?',
         'answer' => 'Register your request anyway. We notify relevant providers, and your demand helps bring a provider to your area — and can trigger a service "run" when enough people in a region need help.', 'sort_order' => 3],
        ['category' => 'customers', 'question' => 'What is the difference between mobile and workshop providers?',
         'answer' => 'Mobile providers travel to you (great when you are on the road or your van is hard to move). Workshop providers operate from a fixed location you visit. Some offer both — the listing shows which.', 'sort_order' => 4],
        ['category' => 'customers', 'question' => 'Is my personal information shared publicly?',
         'answer' => 'No. Your name and exact contact details are never shown publicly. A provider only receives the details needed to help after you are matched and have consented. See our Privacy policy for more.', 'sort_order' => 5],
        ['category' => 'customers', 'question' => 'What are "unclaimed" listings?',
         'answer' => 'Some businesses are listed from publicly available information so you can still find help in your area. They are clearly badged "unclaimed" and show only details the business already publishes. Always confirm directly before booking.', 'sort_order' => 6],
        ['category' => 'providers', 'question' => 'How do I join as a provider?',
         'answer' => 'Visit the For providers page and register your interest. We will send your secure onboarding details. Founding-provider access is free during the launch.', 'sort_order' => 7],
        ['category' => 'providers', 'question' => 'Do I have to accept every job?',
         'answer' => 'No. You stay in control and choose which requests and runs you take on.', 'sort_order' => 8],
        ['category' => 'providers', 'question' => 'How does verification work?',
         'answer' => 'You can upload licences and insurance privately. Once our team verifies them, a verified badge appears on your profile. Verification confirms documents were sighted; it is not a guarantee of work.', 'sort_order' => 9],
        ['category' => 'providers', 'question' => 'My business is listed as "unclaimed" — can I manage it?',
         'answer' => 'Yes. You can claim your listing to correct details, add services and control how you are contacted. Contact us to verify ownership.', 'sort_order' => 10],
        ['category' => 'parks', 'question' => 'How can our caravan park use VanAssist?',
         'answer' => 'You can refer guests to trusted local services in seconds and see upcoming provider visits near your park. Partnering is free during the launch — see the For caravan parks page.', 'sort_order' => 11],
        ['category' => 'general', 'question' => 'Which areas does VanAssist cover?',
         'answer' => 'Every Australian state and territory, with our first on-the-ground launch in Central Queensland and the Wide Bay–Burnett region. Search any town or postcode to see who covers your area.', 'sort_order' => 12],
    ],
];
