<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailTemplate: string {
    case ORDER_CONFIRMATION = 'order_confirmation';

    public function getHtmlTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'mailer/index.html.twig',
        };
    }

    public function getTextTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'mailer/index.txt.twig',
        };
    }

    public function getSubjectKey(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'email.order_confirmation.subject',
        };
    }
}