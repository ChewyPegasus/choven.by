<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Hourly job: cancels bookings whose required prepayment did not arrive in time
 * and closes abandoned payment checkouts.
 */
final class ExpireUnpaidOrders
{
}
