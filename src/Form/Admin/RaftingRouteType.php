<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\RaftingRoute;
use App\Enum\River;
use App\Enum\RouteDifficulty;
use App\Form\Type\LocalizedTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RaftingRoute>
 */
final class RaftingRouteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', LocalizedTextType::class, [
                'label' => 'admin.routes.field.name',
                'max_length' => 120,
            ])
            ->add('slug', TextType::class, [
                'label' => 'admin.tours.field.slug',
                'help' => 'admin.tours.field.slug_help',
            ])
            ->add('river', EnumType::class, [
                'label' => 'booking.form.river',
                'class' => River::class,
                'required' => false,
                'placeholder' => 'admin.common.none',
            ])
            ->add('description', LocalizedTextType::class, [
                'label' => 'admin.routes.field.description',
                'required' => false,
                'multiline' => true,
            ])
            ->add('duration', LocalizedTextType::class, [
                'label' => 'admin.routes.field.duration',
                'help' => 'admin.routes.field.duration_help',
                'required' => false,
                'max_length' => 60,
            ])
            ->add('distanceKm', NumberType::class, [
                'label' => 'admin.routes.field.distance',
                'required' => false,
                'scale' => 1,
                'html5' => true,
                'attr' => ['step' => '0.1', 'min' => 0],
            ])
            ->add('difficulty', EnumType::class, [
                'label' => 'admin.routes.field.difficulty',
                'class' => RouteDifficulty::class,
            ])
            ->add('color', ColorType::class, [
                'label' => 'admin.routes.field.color',
            ])
            ->add('points', CollectionType::class, [
                'label' => 'admin.routes.field.points',
                'entry_type' => RoutePointType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'admin.common.position',
                'help' => 'admin.common.position_help',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'admin.routes.field.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RaftingRoute::class]);
    }
}
