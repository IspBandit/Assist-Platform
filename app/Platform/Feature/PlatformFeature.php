<?php

declare(strict_types=1);

namespace App\Platform\Feature;

enum PlatformFeature: string
{
    case SharedIdentity = 'identity.shared';
    case ProviderMessaging = 'providers.messaging';
    case Reviews = 'reviews.enabled';
    case Billing = 'billing.enabled';
    case Advertising = 'advertising.enabled';
    case ServiceHistory = 'service_history.enabled';
    case Reminders = 'reminders.enabled';
}
