<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Order;
use App\Entity\Tour;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Form\Type\PhoneNumberType;
use App\Repository\TourRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Order>
 */
final class OrderAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'label' => 'admin.orders.field.status',
                'class' => OrderStatus::class,
            ])
            ->add('name', TextType::class, ['label' => 'booking.form.name'])
            ->add('phone', PhoneNumberType::class, ['label' => 'booking.form.phone'])
            ->add('email', EmailType::class, ['label' => 'booking.form.email'])
            ->add('startDate', DateType::class, [
                'label' => 'booking.form.start_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'booking.form.duration',
                'attr' => ['min' => Order::MIN_DAYS, 'max' => Order::MAX_DAYS],
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => 'booking.form.people',
                'attr' => ['min' => Order::MIN_PEOPLE, 'max' => Order::MAX_PEOPLE],
            ])
            ->add('river', EnumType::class, ['label' => 'booking.form.river', 'class' => River::class])
            ->add('package', EnumType::class, ['label' => 'booking.form.package', 'class' => Package::class])
            ->add('tour', EntityType::class, [
                'label' => 'admin.orders.field.tour',
                'class' => Tour::class,
                'required' => false,
                'placeholder' => 'admin.common.none',
                'choice_label' => static fn (Tour $tour): string => $tour->getLocalizedTitle(),
                'query_builder' => static fn (TourRepository $repo) => $repo->createQueryBuilder('t')->orderBy('t.position'),
            ])
            ->add('estimatedPrice', IntegerType::class, [
                'label' => 'admin.orders.field.price',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'admin.orders.field.customer_comment',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('adminNote', TextareaType::class, [
                'label' => 'admin.orders.field.admin_note',
                'help' => 'admin.orders.field.admin_note_help',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('notifyCustomer', CheckboxType::class, [
                'label' => $options['is_new'] ? 'admin.orders.field.send_confirmation' : 'admin.orders.field.notify_customer',
                'mapped' => false,
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'is_new' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
