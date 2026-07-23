<?php

declare(strict_types=1);

/**
 * Default transactional email templates. Admins can edit these later in the
 * CMS. Placeholders such as {{customer_name}} are replaced at send time.
 */

$wrap = static function (string $title, string $body): string {
    return "<div style=\"font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#2b2f33\">"
        . "<h2 style=\"color:#0f6e6e\">{$title}</h2>{$body}"
        . "<hr style=\"border:none;border-top:1px solid #e3e0d8;margin:24px 0\">"
        . "<p style=\"font-size:12px;color:#8a8f94\">{{brand_name}} &middot; {{brand_domain}} &middot; {{support_email}}</p></div>";
};

return [
    [
        'template_key' => 'email_verification',
        'name'    => 'Email verification',
        'subject' => 'Confirm your VanAssist email',
        'html_body' => $wrap('Confirm your email', '<p>Hi {{customer_name}},</p><p>Please confirm your email address to activate your VanAssist account or request.</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Confirm email</a></p><p>If the button does not work, copy this link: {{action_url}}</p>'),
        'text_body' => "Hi {{customer_name}},\n\nConfirm your email: {{action_url}}\n\nVanAssist",
    ],
    [
        'template_key' => 'password_reset',
        'name'    => 'Password reset',
        'subject' => 'Reset your VanAssist password',
        'html_body' => $wrap('Reset your password', '<p>Hi {{customer_name}},</p><p>We received a request to reset your password. This link expires soon.</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Reset password</a></p><p>If you did not request this, you can ignore this email.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nReset your password: {{action_url}}\n\nIf you did not request this, ignore this email.",
    ],
    [
        'template_key' => 'new_request_received',
        'name'    => 'New request received',
        'subject' => 'We received your request {{request_reference}}',
        'html_body' => $wrap('Request received', '<p>Hi {{customer_name}},</p><p>Thanks for submitting your request <strong>{{request_reference}}</strong> for {{town_name}}. We are reviewing it and looking for suitable providers.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nWe received your request {{request_reference}} for {{town_name}}.",
    ],
    [
        'template_key' => 'request_approved',
        'name'    => 'Request approved',
        'subject' => 'Your request {{request_reference}} is now live',
        'html_body' => $wrap('Request approved', '<p>Hi {{customer_name}},</p><p>Your request <strong>{{request_reference}}</strong> has been approved and is now visible to matched providers.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nYour request {{request_reference}} has been approved.",
    ],
    [
        'template_key' => 'provider_match_invitation',
        'name'    => 'Provider match invitation',
        'subject' => 'A customer request may suit you',
        'html_body' => $wrap('New request invitation', '<p>Hi {{provider_name}},</p><p>A customer near {{town_name}} is looking for assistance. View the request and let us know if you are interested.</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">View request</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nA customer near {{town_name}} needs assistance: {{action_url}}",
    ],
    [
        'template_key' => 'provider_interested',
        'name'    => 'Provider interested',
        'subject' => 'A provider is interested in your request {{request_reference}}',
        'html_body' => $wrap('Good news', '<p>Hi {{customer_name}},</p><p>{{provider_name}} has expressed interest in your request <strong>{{request_reference}}</strong>.</p>'),
        'text_body' => "Hi {{customer_name}},\n\n{{provider_name}} is interested in your request {{request_reference}}.",
    ],
    [
        'template_key' => 'information_requested',
        'name'    => 'Information requested',
        'subject' => 'More information needed for {{request_reference}}',
        'html_body' => $wrap('More information needed', '<p>Hi {{customer_name}},</p><p>A provider has asked for more detail about your request <strong>{{request_reference}}</strong>.</p><p><a href="{{action_url}}">Update your request</a></p>'),
        'text_body' => "Hi {{customer_name}},\n\nMore information needed for {{request_reference}}: {{action_url}}",
    ],
    [
        'template_key' => 'run_forming_notification',
        'name'    => 'Run forming notification',
        'subject' => 'A service run is forming near {{town_name}}',
        'html_body' => $wrap('A service run is forming', '<p>Hi {{customer_name}},</p><p>{{run_title}} is forming near {{town_name}}. Register your interest to help it go ahead.</p><p><a href="{{action_url}}">View run</a></p>'),
        'text_body' => "Hi {{customer_name}},\n\n{{run_title}} is forming near {{town_name}}: {{action_url}}",
    ],
    [
        'template_key' => 'run_confirmed',
        'name'    => 'Run confirmed',
        'subject' => '{{run_title}} is confirmed',
        'html_body' => $wrap('Run confirmed', '<p>Hi {{customer_name}},</p><p>Good news — {{run_title}} is now confirmed. Details: <a href="{{action_url}}">view run</a>.</p>'),
        'text_body' => "Hi {{customer_name}},\n\n{{run_title}} is confirmed: {{action_url}}",
    ],
    [
        'template_key' => 'appointment_offer',
        'name'    => 'Appointment offer',
        'subject' => 'Appointment offered for {{request_reference}}',
        'html_body' => $wrap('Appointment offered', '<p>Hi {{customer_name}},</p><p>{{provider_name}} has offered an appointment for your request <strong>{{request_reference}}</strong>.</p><p><a href="{{action_url}}">Review the offer</a></p>'),
        'text_body' => "Hi {{customer_name}},\n\nAppointment offered for {{request_reference}}: {{action_url}}",
    ],
    [
        'template_key' => 'run_reminder',
        'name'    => 'Run reminder',
        'subject' => 'Reminder: {{run_title}} is coming up',
        'html_body' => $wrap('Run reminder', '<p>Hi {{customer_name}},</p><p>This is a reminder that {{run_title}} is approaching.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nReminder: {{run_title}} is approaching.",
    ],
    [
        'template_key' => 'provider_claim_invite',
        'name'    => 'Provider listing claim invite',
        'subject' => '{{business_name}} — claim your free listing on VanAssist',
        'html_body' => $wrap(
            'Claim your free listing',
            '<p>Hi {{greeting}},</p>'
            . '<p><strong>VanAssist</strong> is a regional directory that helps caravan and RV travellers find repair, maintenance and roadside service help across Australia — especially in towns where options are limited.</p>'
            . '<p>Travellers search by town or postcode and service type. When they need help, they can register a request; matching providers in the area are notified. You stay in control — you only accept the work you want.</p>'
            . '<p>We have created a preliminary listing for <strong>{{business_name}}</strong> from publicly available information so travellers in your area can find you. If this is your business, you can claim it below.</p>'
            . '<div style="background:#f6f4ef;border:1px solid #e3e0d8;border-radius:8px;padding:14px 16px;margin:16px 0">'
            . '<p style="margin:0"><strong>{{business_name}}</strong></p>'
            . '{{town_line}}{{services_line}}'
            . '<p style="margin:10px 0 0;font-size:.9rem"><a href="{{listing_url}}">View the public listing</a></p>'
            . '</div>'
            . '<div style="background:#eef9f7;border-left:4px solid #0f6e6e;padding:12px 16px;margin:16px 0">'
            . '<p style="margin:0"><strong>Completely free during our launch.</strong> There are no listing fees, no subscription and no credit card required. Claiming lets you verify your details, update services and service areas, and receive customer requests when we match them to your business.</p>'
            . '</div>'
            . '<p>Learn more about VanAssist at <a href="{{site_url}}">{{site_url}}</a>.</p>'
            . '{{founding_offer_line}}'
            . '<p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;display:inline-block">Claim this listing — it&rsquo;s free</a></p>'
            . '<p style="font-size:.9rem">If the button does not work, copy this link into your browser:<br><a href="{{action_url}}">{{action_url}}</a></p>'
            . '<p style="font-size:.85rem;color:#8a8f94">This link is personal to your business and expires in {{expiry_days}} days. If this is not your business, you can ignore this email — no account will be created unless you claim the listing.</p>'
        ),
        'text_body' => "Hi {{greeting}},\n\n"
            . "VanAssist is a free regional directory (during our launch) that helps caravan and RV travellers find repair and service help across Australia.\n\n"
            . "Travellers search by town or postcode and service type. When they need help, they can register a request and matching providers are notified. You only accept the work you want.\n\n"
            . "We created a preliminary listing for {{business_name}} from publicly available information:\n"
            . "{{listing_url}}\n\n"
            . "CLAIMING IS FREE during our launch — no listing fees, no subscription and no credit card required. Claiming lets you verify your details, update services and coverage, and receive customer requests.\n\n"
            . "Claim your listing:\n{{action_url}}{{founding_offer_text}}\n\n"
            . "Learn more: {{site_url}}\n\n"
            . "This link expires in {{expiry_days}} days. If this is not your business, you can ignore this email.",
    ],
    [
        'template_key' => 'provider_founding_graphic_unlocked',
        'name'    => 'Founding free ad graphic unlocked',
        'subject' => 'Your free local ad graphics are ready to request',
        'html_body' => $wrap(
            'Free ad graphics',
            '<p>Hi {{provider_name}},</p>'
            . '<p>Your VanAssist profile is now <strong>verified</strong>. As a founding provider in a launch area, you can request your <strong>free local ad graphics</strong> (worth $99).</p>'
            . '<p>We will design a desktop banner (1200×400) and a mobile version (800×450) for travellers searching near {{town_name}}, and feature your business during our launch period.</p>'
            . '<p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;display:inline-block">Request my free graphics</a></p>'
            . '<p style="font-size:.9rem">If the button does not work, copy this link: {{action_url}}</p>'
        ),
        'text_body' => "Hi {{provider_name}},\n\n"
            . "Your profile is verified. Request your free local ad graphics (desktop + mobile, worth $99) for travellers near {{town_name}}:\n"
            . "{{action_url}}",
    ],
    [
        'template_key' => 'provider_founding_graphic_delivered',
        'name'    => 'Founding ad graphic delivered',
        'subject' => 'Your VanAssist ad graphics are ready',
        'html_body' => $wrap(
            'Your ad graphics are ready',
            '<p>Hi {{provider_name}},</p>'
            . '<p>Your free founding ad graphics are ready — desktop and mobile versions — and your business is now <strong>featured</strong> for travellers searching in your service area during our launch.</p>'
            . '<p><a href="{{action_url}}">View your promotion</a></p>'
            . '<p style="font-size:.9rem">Download: <a href="{{image_url}}">Desktop graphic</a>'
            . ' · <a href="{{image_url_mobile}}">Mobile graphic</a></p>'
        ),
        'text_body' => "Hi {{provider_name}},\n\nYour free founding ad graphics are ready.\nDesktop: {{image_url}}\nMobile: {{image_url_mobile}}\n\nView details: {{action_url}}",
    ],
    [
        'template_key' => 'provider_invitation',
        'name'    => 'Provider invitation',
        'subject' => 'You are invited to join VanAssist',
        'html_body' => $wrap('Join VanAssist', '<p>Hi {{provider_name}},</p><p>You have been invited to create your VanAssist provider profile and start receiving local customer requests.</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Accept your invitation</a></p><p>This link expires in 14 days. If the button does not work, copy this link: {{action_url}}</p>'),
        'text_body' => "Hi {{provider_name}},\n\nYou are invited to join VanAssist: {{action_url}}\n\nThis link expires in 14 days.",
    ],
    [
        'template_key' => 'provider_application_received',
        'name'    => 'Provider application received',
        'subject' => 'We received your provider application',
        'html_body' => $wrap('Application received', '<p>Hi {{provider_name}},</p><p>Thanks for applying to join VanAssist. We will review your details and verification documents.</p>'),
        'text_body' => "Hi {{provider_name}},\n\nWe received your provider application.",
    ],
    [
        'template_key' => 'provider_approved',
        'name'    => 'Provider approved',
        'subject' => 'Your VanAssist provider profile is approved',
        'html_body' => $wrap('You are approved', '<p>Hi {{provider_name}},</p><p>Your provider profile is approved and now public. You can create service runs and receive matching requests.</p><p><a href="{{action_url}}">Go to your dashboard</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nYour provider profile is approved: {{action_url}}",
    ],
    [
        'template_key' => 'provider_rejected',
        'name'    => 'Provider rejected',
        'subject' => 'About your VanAssist provider application',
        'html_body' => $wrap('Application update', '<p>Hi {{provider_name}},</p><p>Thank you for your interest. Unfortunately we are unable to approve your application at this time.</p>'),
        'text_body' => "Hi {{provider_name}},\n\nWe are unable to approve your application at this time.",
    ],
    [
        'template_key' => 'document_expiry_reminder',
        'name'    => 'Document expiry reminder',
        'subject' => 'A verification document is expiring soon',
        'html_body' => $wrap('Document expiring', '<p>Hi {{provider_name}},</p><p>One of your verification documents is expiring soon. Please upload an updated copy.</p><p><a href="{{action_url}}">Update documents</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nA verification document is expiring soon: {{action_url}}",
    ],
    [
        'template_key' => 'park_application_received',
        'name'    => 'Park application received',
        'subject' => 'We received your caravan park partner application',
        'html_body' => $wrap('Application received', '<p>Hi {{customer_name}},</p><p>Thanks for applying to become a VanAssist caravan park partner. We will be in touch shortly.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nWe received your caravan park partner application.",
    ],
    [
        'template_key' => 'contact_form_confirmation',
        'name'    => 'Contact form confirmation',
        'subject' => 'We received your message',
        'html_body' => $wrap('Thanks for getting in touch', '<p>Hi {{customer_name}},</p><p>We have received your message and will respond as soon as we can.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nWe received your message and will respond soon.",
    ],
    [
        'template_key' => 'outcome_followup',
        'name'    => 'Customer outcome follow-up',
        'subject' => 'How did your request {{request_reference}} go?',
        'html_body' => $wrap('Did you get the help you needed?', '<p>Hi {{customer_name}},</p><p>A little while ago you looked for help with <strong>{{request_reference}}</strong>. We would love to know how it went — it helps us confirm which providers are genuinely helping travellers and where we need more.</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Tell us how it went</a></p><p>If the button does not work, copy this link: {{action_url}}</p><p style="font-size:12px;color:#8a8f94">Not expecting this? You can ignore it and we won&rsquo;t ask again about this request.</p>'),
        'text_body' => "Hi {{customer_name}},\n\nHow did your request {{request_reference}} go? Let us know: {{action_url}}\n\nIf you'd rather not say, just ignore this email.",
    ],
    [
        'template_key' => 'admin_request_no_match',
        'name'    => 'Admin: request needs manual matching',
        'subject' => 'Action needed: no auto-match for {{request_reference}}',
        'html_body' => $wrap('A request needs you', '<p>Request <strong>{{request_reference}}</strong> near {{town_name}} could not be auto-matched.</p><p>Reason: {{reason}}</p><p><a href="{{action_url}}" style="background:#0f6e6e;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Open the matching console</a></p>'),
        'text_body' => "Request {{request_reference}} near {{town_name}} could not be auto-matched.\nReason: {{reason}}\nMatch it manually: {{action_url}}",
    ],
    [
        'template_key' => 'provider_welcome',
        'name' => 'Provider welcome',
        'subject' => 'Welcome to {{brand_name}}',
        'html_body' => $wrap('Welcome to {{brand_name}}', '<p>Hi {{provider_name}},</p><p>Your shared Assist Platform provider account is ready. Review your business details, services and coverage before publishing.</p><p><a href="{{action_url}}">Open your provider dashboard</a></p><p>Help: {{support_email}}</p>'),
        'text_body' => "Hi {{provider_name}},\n\nWelcome to {{brand_name}}. Review your profile: {{action_url}}\nHelp: {{support_email}}",
    ],
    [
        'template_key' => 'founding_member_invitation',
        'name' => 'Founding member invitation',
        'subject' => 'Founding member invitation from {{brand_name}}',
        'html_body' => $wrap('Founding member invitation', '<p>Hi {{provider_name}},</p><p>You are invited to join the Assist Platform founding-member programme. Membership benefits apply across participating brands under the shared membership policy.</p><p><a href="{{action_url}}">Review the invitation</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nReview your {{brand_name}} founding-member invitation: {{action_url}}",
    ],
    [
        'template_key' => 'profile_completion_reminder',
        'name' => 'Profile completion reminder',
        'subject' => 'Complete your {{brand_name}} business profile',
        'html_body' => $wrap('Finish your business profile', '<p>Hi {{provider_name}},</p><p>Your profile is {{completion_percent}}% complete. Add current contact, service, location and verification details so customers can make an informed choice.</p><p><a href="{{action_url}}">Complete the profile</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nYour {{brand_name}} profile is {{completion_percent}}% complete: {{action_url}}",
    ],
    [
        'template_key' => 'payment_succeeded',
        'name' => 'Payment succeeded',
        'subject' => 'Your Assist Platform payment was received',
        'html_body' => $wrap('Payment received', '<p>Hi {{provider_name}},</p><p>We received your payment of {{amount}} for {{plan_name}}. Your receipt is available in the billing area.</p><p><a href="{{action_url}}">View billing</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nPayment received: {{amount}} for {{plan_name}}. {{action_url}}",
    ],
    [
        'template_key' => 'payment_failed',
        'name' => 'Payment failed',
        'subject' => 'Action required for your Assist Platform membership',
        'html_body' => $wrap('Payment needs attention', '<p>Hi {{provider_name}},</p><p>We could not process the payment for {{plan_name}}. Update the billing method to prevent paid features being paused after the configured grace period.</p><p><a href="{{action_url}}">Update billing</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nPayment failed for {{plan_name}}. Update billing: {{action_url}}",
    ],
    [
        'template_key' => 'membership_cancelled',
        'name' => 'Membership cancelled',
        'subject' => 'Your Assist Platform membership has been cancelled',
        'html_body' => $wrap('Membership cancelled', '<p>Hi {{provider_name}},</p><p>Your paid membership has been cancelled. Your core business record remains available subject to the current free-listing policy.</p><p><a href="{{action_url}}">Review your account</a></p>'),
        'text_body' => "Hi {{provider_name}},\n\nYour paid membership has been cancelled. Review your account: {{action_url}}",
    ],
];
