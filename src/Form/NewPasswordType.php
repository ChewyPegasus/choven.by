<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * New password (with confirmation); optionally asks for the current one first.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'profile.password.current',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'registration.error.password_required'),
                    new UserPassword(message: 'profile.password.current_invalid'),
                ],
            ]);
        }

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'label' => false,
            'invalid_message' => 'registration.error.passwords_mismatch',
            'first_options' => [
                'label' => 'reset_password.form.new_password',
                'help' => 'registration.form.password_help',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'registration.form.repeat_password',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => [
                new NotBlank(message: 'registration.error.password_required'),
                new Length(min: RegistrationType::MIN_PASSWORD_LENGTH, max: 4096, minMessage: 'registration.error.password_too_short'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['require_current' => false]);
        $resolver->setAllowedTypes('require_current', 'bool');
    }
}
