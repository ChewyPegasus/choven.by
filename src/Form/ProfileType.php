<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Form\Type\PhoneNumberType;
use App\Model\Localized;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'profile.form.name',
                'required' => false,
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('phone', PhoneNumberType::class, [
                'label' => 'profile.form.phone',
                'required' => false,
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'profile.form.locale',
                'help' => 'profile.form.locale_help',
                'choices' => array_combine(
                    array_map(static fn (string $l): string => 'locale.'.$l, Localized::LOCALES),
                    Localized::LOCALES,
                ),
            ])
            ->add('marketingConsent', CheckboxType::class, [
                'label' => 'registration.form.marketing',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
