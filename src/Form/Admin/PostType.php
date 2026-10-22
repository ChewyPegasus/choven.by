<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Post;
use App\Entity\Tour;
use App\Form\Type\LocalizedTextType;
use App\Repository\TourRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * @extends AbstractType<Post>
 */
final class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', LocalizedTextType::class, ['label' => 'admin.posts.field.title', 'max_length' => 160])
            ->add('slug', TextType::class, ['label' => 'admin.tours.field.slug', 'help' => 'admin.tours.field.slug_help'])
            ->add('excerpt', LocalizedTextType::class, [
                'label' => 'admin.posts.field.excerpt',
                'help' => 'admin.posts.field.excerpt_help',
                'required' => false,
                'multiline' => true,
                'rows' => 2,
                'max_length' => 300,
            ])
            ->add('body', LocalizedTextType::class, [
                'label' => 'admin.posts.field.body',
                'help' => 'admin.common.markdown_help',
                'multiline' => true,
                'rows' => 16,
                'max_length' => 50000,
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'admin.posts.field.cover',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Image(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'admin.tours.error.image_type')],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ])
            ->add('tour', EntityType::class, [
                'label' => 'admin.posts.field.tour',
                'help' => 'admin.posts.field.tour_help',
                'class' => Tour::class,
                'required' => false,
                'placeholder' => 'admin.common.none',
                'choice_label' => static fn (Tour $tour): string => $tour->getLocalizedTitle(),
                'query_builder' => static fn (TourRepository $repo) => $repo->createQueryBuilder('t')->orderBy('t.position', \SortDirection::Ascending),
            ])
            ->add('author', TextType::class, ['label' => 'admin.posts.field.author', 'required' => false])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'admin.posts.field.published_at',
                'help' => 'admin.posts.field.published_at_help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('isPublished', CheckboxType::class, ['label' => 'admin.posts.field.published', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Post::class]);
    }
}
