<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Form\Type\PhoneNumberType;
use App\Model\Localized;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [new Length(min: RegistrationType::MIN_PASSWORD_LENGTH, max: 4096, minMessage: 'registration.error.password_too_short')];
        if ($options['is_new']) {
            $passwordConstraints[] = new NotBlank(message: 'registration.error.password_required');
        }

        $builder
            ->add('email', EmailType::class, ['label' => 'registration.form.email'])
            ->add('name', TextType::class, ['label' => 'registration.form.name', 'required' => false])
            ->add('phone', PhoneNumberType::class, ['label' => 'registration.form.phone', 'required' => false])
            ->add('locale', ChoiceType::class, [
                'label' => 'profile.form.locale',
                'choices' => array_combine(
                    array_map(static fn (string $l): string => 'locale.'.$l, Localized::LOCALES),
                    Localized::LOCALES,
                ),
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'admin.users.field.verified',
                'required' => false,
            ])
            ->add('admin', CheckboxType::class, [
                'label' => 'admin.users.field.admin',
                'required' => false,
                'disabled' => $options['is_self'],
                'help' => $options['is_self'] ? 'admin.users.field.admin_self_help' : null,
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => $options['is_new'] ? 'registration.form.password' : 'admin.users.field.new_password',
                'help' => $options['is_new'] ? null : 'admin.users.field.new_password_help',
                'mapped' => false,
                'required' => $options['is_new'],
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => $passwordConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
            'is_self' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
        $resolver->setAllowedTypes('is_self', 'bool');
    }
}
