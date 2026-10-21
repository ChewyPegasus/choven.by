<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum River: string implements TranslatableInterface
{
    case SVISLOCH = 'svisloch';
    case ISLOCH = 'isloch';
    case BEREZINA = 'berezina';
    case NEMAN = 'neman';
    case NAROCHANKA = 'narochanka';
    case STRACHA = 'stracha';
    case SARYANKA = 'saryanka';
    case SLUCH = 'sluch';
    case VILIYA = 'viliya';
    case SULA = 'sula';
    case USA = 'usa';
    case SMERD = 'smerd';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return 'river.'.$this->value;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getLabel(), [], 'messages', $locale);
    }
}
