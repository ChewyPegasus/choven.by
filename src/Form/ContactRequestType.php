<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContactRequest;
use App\Form\Type\HoneypotType;
use App\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * "Call me back" form.
 *
 * @extends AbstractType<ContactRequest>
 */
final class ContactRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'contact.form.name',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('phone', PhoneNumberType::class, ['label' => 'contact.form.phone'])
            ->add('email', EmailType::class, [
                'label' => 'contact.form.email',
                'required' => false,
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 2000],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'booking.form.agree',
                'label_html' => true,
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'form.agree_required')],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactRequest::class,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
