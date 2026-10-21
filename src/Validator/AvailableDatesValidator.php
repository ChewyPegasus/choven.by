<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Order;
use App\Service\AvailabilityChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AvailableDatesValidator extends ConstraintValidator
{
    public function __construct(private readonly AvailabilityChecker $checker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableDates) {
            throw new UnexpectedTypeException($constraint, AvailableDates::class);
        }
        if (!$value instanceof Order) {
            throw new UnexpectedValueException($value, Order::class);
        }

        $start = $value->getStartDate();
        $days = $value->getDurationDays();
        $people = $value->getAmountOfPeople();
        if (null === $start || null === $days || null === $people || $days < 1 || $people < 1) {
            return; // the field constraints report these
        }

        $problems = $this->checker->check($start, $days, $people, $value->getId() ? $value : null);
        if ([] === $problems) {
            return;
        }

        $day = array_key_first($problems);
        $reason = $problems[$day];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        $formatted = false !== $date ? $date->format('d.m.Y') : $day;

        $message = match (true) {
            'blocked' === $reason => $constraint->blockedMessage,
            0 === $reason => $constraint->soldOutMessage,
            default => $constraint->notEnoughMessage,
        };

        $this->context->buildViolation($message)
            ->setParameter('{{ date }}', $formatted)
            ->setParameter('{{ free }}', (string) $reason)
            ->atPath('startDate')
            ->addViolation();
    }
}
