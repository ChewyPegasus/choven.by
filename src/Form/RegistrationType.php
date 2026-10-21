<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Form\Type\HoneypotType;
use App\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class RegistrationType extends AbstractType
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'registration.form.name',
                'required' => false,
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'registration.form.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', PhoneNumberType::class, [
                'label' => 'registration.form.phone',
                'required' => false,
                'help' => 'registration.form.phone_help',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'label' => false,
                'mapped' => false,
                'invalid_message' => 'registration.error.passwords_mismatch',
                'first_options' => [
                    'label' => 'registration.form.password',
                    'help' => 'registration.form.password_help',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'registration.form.repeat_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'registration.error.password_required'),
                    new Length(min: self::MIN_PASSWORD_LENGTH, max: 4096, minMessage: 'registration.error.password_too_short'),
                ],
            ])
            ->add('marketingConsent', CheckboxType::class, [
                'label' => 'registration.form.marketing',
                'required' => false,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'registration.form.agree',
                'label_html' => true,
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'form.agree_required')],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
