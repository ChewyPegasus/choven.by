<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

#[UniqueEntity(fields: ['email'], message: 'registration.email.already_used')]
class RegistrationForm extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('registration.form.email'),
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('registration.form.error.email_required'),
                    ]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => $this->translator->trans('registration.form.phone'),
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->translator->trans('registration.form.phone_placeholder'),
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('registration.form.error.phone_required'),
                    ]),
                    new Regex([
                        'pattern' => '/^\+375\d{9}$/',
                        'message' => $this->translator->trans('registration.form.error.phone_invalid'),
                    ]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
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
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => $this->translator->trans('registration.form.agree_terms'),
                'constraints' => [
                    new IsTrue([
                        'message' => $this->translator->trans('registration.form.error.agree_terms'),
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
