<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Model\OrderFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET filter form of the admin order list: produces short, shareable URLs (?q=…&status=…).
 *
 * @extends AbstractType<OrderFilter>
 */
final class OrderFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => 'admin.orders.filter.query',
                'required' => false,
                'property_path' => 'query',
                'attr' => ['placeholder' => 'admin.orders.filter.query_placeholder'],
            ])
            ->add('status', EnumType::class, [
                'label' => 'admin.orders.field.status',
                'class' => OrderStatus::class,
                'required' => false,
                'placeholder' => 'admin.common.all',
            ])
            ->add('river', EnumType::class, [
                'label' => 'booking.form.river',
                'class' => River::class,
                'required' => false,
                'placeholder' => 'admin.common.all',
            ])
            ->add('package', EnumType::class, [
                'label' => 'booking.form.package',
                'class' => Package::class,
                'required' => false,
                'placeholder' => 'admin.common.all',
            ])
            ->add('from', DateType::class, [
                'label' => 'admin.orders.filter.from',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('to', DateType::class, [
                'label' => 'admin.orders.filter.to',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('sort', ChoiceType::class, [
                'label' => 'admin.orders.filter.sort',
                'empty_data' => 'start_desc',
                'required' => false,
                'placeholder' => false,
                'choices' => array_combine(
                    array_map(static fn (string $s): string => 'admin.orders.sort.'.$s, OrderFilter::SORTS),
                    OrderFilter::SORTS,
                ),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderFilter::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
