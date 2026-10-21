<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The chosen dates were taken by another booking between validation and saving.
 */
final class DatesUnavailableException extends \RuntimeException
{
    /**
     * @param array<string, string|int> $problems "Y-m-d" => "blocked" or free places
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('The selected dates are no longer available.');
    }
}
