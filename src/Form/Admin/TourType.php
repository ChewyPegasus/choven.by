<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\RaftingRoute;
use App\Entity\Tour;
use App\Enum\Package;
use App\Enum\River;
use App\Form\Type\LocalizedTextType;
use App\Repository\RaftingRouteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * @extends AbstractType<Tour>
 */
final class TourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', LocalizedTextType::class, [
                'label' => 'admin.tours.field.title',
                'max_length' => 120,
            ])
            ->add('slug', TextType::class, [
                'label' => 'admin.tours.field.slug',
                'help' => 'admin.tours.field.slug_help',
            ])
            ->add('summary', LocalizedTextType::class, [
                'label' => 'admin.tours.field.summary',
                'multiline' => true,
                'rows' => 2,
                'max_length' => 300,
            ])
            ->add('description', LocalizedTextType::class, [
                'label' => 'admin.tours.field.description',
                'help' => 'admin.tours.field.description_help',
                'required' => false,
                'multiline' => true,
                'rows' => 6,
            ])
            ->add('package', EnumType::class, ['label' => 'booking.form.package', 'class' => Package::class])
            ->add('river', EnumType::class, [
                'label' => 'booking.form.river',
                'class' => River::class,
                'required' => false,
                'placeholder' => 'admin.common.none',
            ])
            ->add('route', EntityType::class, [
                'label' => 'admin.tours.field.route',
                'class' => RaftingRoute::class,
                'required' => false,
                'placeholder' => 'admin.common.none',
                'choice_label' => static fn (RaftingRoute $route): string => $route->getLocalizedName(),
                'query_builder' => static fn (RaftingRouteRepository $repo) => $repo->createQueryBuilder('r')->orderBy('r.position'),
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'booking.form.duration',
                'required' => false,
            ])
            ->add('priceFrom', IntegerType::class, [
                'label' => 'admin.tours.field.price_from',
                'help' => 'admin.tours.field.price_help',
                'required' => false,
            ])
            ->add('priceUnit', ChoiceType::class, [
                'label' => 'admin.tours.field.price_unit',
                'choices' => [
                    'tour.price_unit.person' => Tour::PRICE_PER_PERSON,
                    'tour.price_unit.group' => Tour::PRICE_PER_GROUP,
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'admin.tours.field.image',
                'help' => 'admin.tours.field.image_help',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'admin.tours.error.image_type'),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'admin.common.position',
                'help' => 'admin.common.position_help',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'admin.tours.field.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tour::class]);
    }
}
