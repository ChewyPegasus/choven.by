<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\GiftCertificate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A certificate issued by the team (sold for cash, a prize, a compensation).
 *
 * @extends AbstractType<GiftCertificate>
 */
final class GiftIssueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', IntegerType::class, [
                'label' => 'gift.form.amount',
                'attr' => ['min' => GiftCertificate::MIN_AMOUNT, 'max' => GiftCertificate::MAX_AMOUNT],
            ])
            ->add('buyerName', TextType::class, ['label' => 'admin.gifts.field.buyer_name'])
            ->add('buyerEmail', EmailType::class, ['label' => 'admin.gifts.field.buyer_email'])
            ->add('recipientName', TextType::class, ['label' => 'gift.form.recipient', 'required' => false])
            ->add('message', TextareaType::class, ['label' => 'gift.form.message', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('sendEmail', CheckboxType::class, [
                'label' => 'admin.gifts.field.send_email',
                'mapped' => false,
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => GiftCertificate::class]);
    }
}
