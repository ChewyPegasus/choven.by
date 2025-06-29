<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\EmailTemplate;

/**
 * Basic class for all DTO that connected with mail sending
 */
readonly abstract class AbstractEmailDTO implements DTO
{
    abstract public function getEmailTemplate(): EmailTemplate;

    abstract public function getEmail(): string;

    abstract public function getContext(): array;
}
