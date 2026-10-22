<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Order;
use App\Entity\WaitlistEntry;
use App\Form\Type\HoneypotType;
use App\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * "Tell me when places become free" form, offered when the chosen dates are sold out.
 *
 * @extends AbstractType<WaitlistEntry>
 */
final class WaitlistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'booking.form.start_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'booking.form.duration',
                'attr' => ['min' => Order::MIN_DAYS, 'max' => Order::MAX_DAYS],
            ])
            ->add('people', IntegerType::class, [
                'label' => 'booking.form.people',
                'attr' => ['min' => Order::MIN_PEOPLE, 'max' => Order::MAX_PEOPLE],
            ])
            ->add('name', TextType::class, ['label' => 'booking.form.name', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'booking.form.email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', PhoneNumberType::class, ['label' => 'waitlist.form.phone', 'required' => false])
            ->add('tourSlug', HiddenType::class, ['mapped' => false, 'data' => $options['tour_slug']])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'booking.form.agree',
                'label_html' => true,
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'form.agree_required')],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WaitlistEntry::class,
            'tour_slug' => null,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setAllowedTypes('tour_slug', ['null', 'string']);
    }
}
