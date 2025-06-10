<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailTemplate: string {
    case ORDER_CONFIRMATION = 'order_confirmation';
    case VERIFICATION = 'verification';

    public function getHtmlTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => "mailer/order/index.html.twig",
            self::VERIFICATION => "mailer/verification/index.html.twig",
        };
    }

    public function getTextTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'mailer/order/index.txt.twig',
            self::VERIFICATION => 'mailer/verification/index.txt.twig',
        };
    }

    public function getSubjectKey(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'email.order_confirmation.subject',
            self::VERIFICATION => 'email.verification.subject',
        };
    }
}