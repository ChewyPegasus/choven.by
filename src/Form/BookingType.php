<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Extra;
use App\Entity\Order;
use App\Enum\Package;
use App\Enum\River;
use App\Form\Type\HoneypotType;
use App\Form\Type\PhoneNumberType;
use App\Validator\AvailableDates;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Public booking form.
 *
 * @extends AbstractType<Order>
 */
final class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');

        $builder
            ->add('name', TextType::class, [
                'label' => 'booking.form.name',
                'attr' => ['autocomplete' => 'name', 'maxlength' => 100],
            ])
            ->add('phone', PhoneNumberType::class, [
                'label' => 'booking.form.phone',
                'help' => 'booking.form.phone_help',
            ])
            ->add('email', EmailType::class, [
                'label' => 'booking.form.email',
                'attr' => ['autocomplete' => 'email', 'readonly' => $options['email_locked']],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'booking.form.start_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'min' => $today->format('Y-m-d'),
                    'max' => $today->modify('+1 year')->format('Y-m-d'),
                    'data-booking-target' => 'date',
                    'data-action' => 'booking#refresh',
                ],
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'booking.form.duration',
                'attr' => [
                    'min' => Order::MIN_DAYS,
                    'max' => Order::MAX_DAYS,
                    'data-booking-target' => 'days',
                    'data-action' => 'booking#refresh',
                ],
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => 'booking.form.people',
                'attr' => [
                    'min' => Order::MIN_PEOPLE,
                    'max' => Order::MAX_PEOPLE,
                    'data-booking-target' => 'people',
                    'data-action' => 'booking#refresh',
                ],
            ])
            ->add('river', EnumType::class, [
                'label' => 'booking.form.river',
                'class' => River::class,
                'placeholder' => 'booking.form.choose',
            ])
            ->add('package', EnumType::class, [
                'label' => 'booking.form.package',
                'class' => Package::class,
                'placeholder' => 'booking.form.choose',
            ]);

        /** @var list<Extra> $extras */
        $extras = $options['extras'];
        if ([] !== $extras) {
            $group = $builder->create('extras', FormType::class, ['mapped' => false, 'label' => false]);
            foreach ($extras as $extra) {
                $attr = [
                    'data-booking-target' => 'extra',
                    'data-action' => 'booking#refresh',
                    'data-price' => $extra->getPrice(),
                    'data-unit' => $extra->getUnit()->value,
                    'data-per-day' => $extra->isPerDay() ? '1' : '0',
                ];
                if ($extra->getUnit()->hasQuantity()) {
                    $group->add('e'.$extra->getId(), IntegerType::class, [
                        'label' => $extra->getLocalizedName(),
                        'translation_domain' => false,
                        'required' => false,
                        'empty_data' => '0',
                        'constraints' => [new Range(min: 0, max: $extra->getMaxQuantity())],
                        'attr' => $attr + ['min' => 0, 'max' => $extra->getMaxQuantity(), 'class' => 'extra-quantity'],
                    ]);
                } else {
                    $group->add('e'.$extra->getId(), CheckboxType::class, [
                        'label' => $extra->getLocalizedName(),
                        'translation_domain' => false,
                        'required' => false,
                        'attr' => $attr,
                    ]);
                }
            }
            $builder->add($group);
        }

        $builder
            ->add('promoCode', TextType::class, [
                'label' => 'booking.form.promo',
                'help' => 'booking.form.promo_help',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Length(max: 32)],
                'attr' => [
                    'maxlength' => 32,
                    'autocomplete' => 'off',
                    'autocapitalize' => 'characters',
                    'spellcheck' => 'false',
                    'data-booking-target' => 'promo',
                    'data-action' => 'change->booking#checkPromo',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'booking.form.comment',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 2000, 'placeholder' => 'booking.form.comment_placeholder'],
            ])
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
            'data_class' => Order::class,
            'validation_groups' => ['Default', 'booking'],
            'constraints' => [new AvailableDates()],
            'email_locked' => false,
            'extras' => [],
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setAllowedTypes('email_locked', 'bool');
        $resolver->setAllowedTypes('extras', 'array');
    }
}
