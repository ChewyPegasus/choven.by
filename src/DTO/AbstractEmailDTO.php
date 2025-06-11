<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\EmailTemplate;

/**
 * Базовый класс для всех DTO, связанных с отправкой писем
 */
readonly abstract class AbstractEmailDTO implements DTO
{
    abstract public function getEmailTemplate(): EmailTemplate;
    
    public function getAdditionalContext(): array
    {
        return [];
    }
}
