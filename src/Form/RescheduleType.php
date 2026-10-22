<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * New start date of a booking (customer self-service).
 *
 * @extends AbstractType<array{startDate: \DateTimeImmutable|null}>
 */
final class RescheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $builder->add('startDate', DateType::class, [
            'label' => 'reschedule.new_date',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'constraints' => [
                new NotNull(message: 'order.error.date_required'),
                new GreaterThan('today', message: 'order.error.date_in_past'),
                new LessThanOrEqual('+1 year', message: 'order.error.date_too_far'),
            ],
            'attr' => [
                'min' => $tomorrow->format('Y-m-d'),
                'max' => $tomorrow->modify('+1 year')->format('Y-m-d'),
            ],
        ]);
    }
}
