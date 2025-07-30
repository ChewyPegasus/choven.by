<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Form\Type\PhoneNumberType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Symfony Form Type for user registration.
 *
 * This form defines the fields and their configurations for new user sign-up,
 * including email, phone number, password (with repetition), and terms agreement.
 * It applies various validation constraints and uses translation keys for labels and messages.
 */
#[UniqueEntity(fields: ['email'], message: 'registration.email.already_used')]
#[UniqueEntity(fields: ['phone'], message: 'registration.phone.already_used')]
class RegistrationForm extends AbstractType
{
    /**
     * Constructs a new RegistrationForm instance.
     *
     * @param TranslatorInterface $translator The translator service for internationalization.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Builds the form by adding fields and their configurations.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @param array $options The options for this form type.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('registration.form.email'),
                'attr' => [
                    'class' => 'form-control', // Bootstrap class for styling
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('registration.form.error.email_required'),
                    ]),
                ],
            ])
            ->add('phone', PhoneNumberType::class, [
                'label' => $this->translator->trans('registration.form.phone'),
                // PhoneNumberType handles its own constraints and transformations
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false, // This field is not mapped directly to the User entity's 'password' property
                'attr' => ['autocomplete' => 'new-password'], // Browser autocomplete hint
                'first_options' => [
                    'label' => $this->translator->trans('registration.form.password'),
                    'attr' => ['class' => 'form-control'],
                ],
                'second_options' => [
                    'label' => $this->translator->trans('registration.form.repeat_password'),
                    'attr' => ['class' => 'form-control'],
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('registration.form.error.password_required'),
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => $this->translator->trans('registration.form.error.password_too_short', ['%limit%' => 6]),
                        'max' => 4096, // Max length for security reasons (password hashing)
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false, // This field is not mapped directly to the User entity
                'label' => $this->translator->trans('registration.form.agree_terms'),
                'constraints' => [
                    new IsTrue([
                        'message' => $this->translator->trans('registration.form.error.agree_terms'),
                    ]),
                ],
            ])
        ;
    }

    /**
     * Configures the options for this form type.
     *
     * Sets the default data class to `User::class`, indicating that this form
     * is intended to create or update `User` entities.
     *
     * @param OptionsResolver $resolver The options resolver.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class, // The entity this form will map to
        ]);
    }
}