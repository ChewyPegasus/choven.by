<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Every few minutes: proves to /health that the worker and the scheduler are alive.
 */
final class RecordHeartbeat
{
}
