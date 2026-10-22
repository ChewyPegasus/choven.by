<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GiftCertificate;
use App\Form\Type\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Buying a gift certificate on the site.
 *
 * @extends AbstractType<GiftCertificate>
 */
final class GiftCertificateType extends AbstractType
{
    public const PRESETS = [100, 150, 200, 300, 500];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', IntegerType::class, [
                'label' => 'gift.form.amount',
                'help' => 'gift.form.amount_help',
                'help_translation_parameters' => ['%min%' => GiftCertificate::MIN_AMOUNT, '%max%' => GiftCertificate::MAX_AMOUNT],
                'attr' => ['min' => GiftCertificate::MIN_AMOUNT, 'max' => GiftCertificate::MAX_AMOUNT, 'step' => 10, 'data-gift-target' => 'amount', 'data-action' => 'gift#update'],
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'gift.form.recipient',
                'required' => false,
                'attr' => ['maxlength' => 100, 'data-gift-target' => 'recipient', 'data-action' => 'gift#update'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'gift.form.message',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 300, 'placeholder' => 'gift.form.message_placeholder', 'data-gift-target' => 'message', 'data-action' => 'gift#update'],
            ])
            ->add('buyerName', TextType::class, [
                'label' => 'gift.form.buyer_name',
                'attr' => ['autocomplete' => 'name', 'maxlength' => 100],
            ])
            ->add('buyerEmail', EmailType::class, [
                'label' => 'gift.form.buyer_email',
                'help' => 'gift.form.buyer_email_help',
                'attr' => ['autocomplete' => 'email'],
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
            'data_class' => GiftCertificate::class,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
