<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Review>
 */
final class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authorName', TextType::class, [
                'label' => 'review.form.name',
                'help' => 'review.form.name_help',
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'review.form.rating',
                'choices' => ['5' => 5, '4' => 4, '3' => 3, '2' => 2, '1' => 1],
                'expanded' => true,
                'choice_translation_domain' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'review.form.text',
                'attr' => ['rows' => 5, 'maxlength' => 2000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Review::class]);
    }
}
