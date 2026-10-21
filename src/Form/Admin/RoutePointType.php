<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\RaftingRoute;
use App\Form\Type\LocalizedTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

/**
 * One point of a route (array data: lat, lng, type, name, description).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class RoutePointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lat', NumberType::class, [
                'label' => 'admin.routes.point.lat',
                'scale' => 6,
                'html5' => true,
                'attr' => ['step' => 'any', 'data-route-editor-target' => 'lat'],
                'constraints' => [new NotNull(), new Range(min: -90, max: 90)],
            ])
            ->add('lng', NumberType::class, [
                'label' => 'admin.routes.point.lng',
                'scale' => 6,
                'html5' => true,
                'attr' => ['step' => 'any', 'data-route-editor-target' => 'lng'],
                'constraints' => [new NotNull(), new Range(min: -180, max: 180)],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'admin.routes.point.type',
                'choices' => array_combine(
                    array_map(static fn (string $t): string => 'admin.routes.point_type.'.$t, RaftingRoute::POINT_TYPES),
                    RaftingRoute::POINT_TYPES,
                ),
            ])
            ->add('name', LocalizedTextType::class, [
                'label' => 'admin.routes.point.name',
                'max_length' => 120,
            ])
            ->add('description', LocalizedTextType::class, [
                'label' => 'admin.routes.point.description',
                'required' => false,
                'multiline' => true,
                'rows' => 2,
                'max_length' => 1000,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => ['lat' => null, 'lng' => null, 'type' => 'point', 'name' => [], 'description' => []],
        ]);
    }
}
