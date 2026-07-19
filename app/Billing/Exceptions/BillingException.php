<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

use RuntimeException;

/**
 * Thrown when a billing operation is requested that cannot be performed,
 * e.g. a real payment action while billing is disabled or a gateway is
 * configured but not yet implemented.
 */
final class BillingException extends RuntimeException
{
}
