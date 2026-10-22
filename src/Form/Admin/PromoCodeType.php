<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\PromoCode;
use App\Enum\PromoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PromoCode>
 */
final class PromoCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'admin.promo.field.code',
                'help' => 'admin.promo.field.code_help',
                'attr' => ['maxlength' => 32, 'autocapitalize' => 'characters', 'style' => 'text-transform: uppercase'],
            ])
            ->add('type', EnumType::class, ['label' => 'admin.promo.field.type', 'class' => PromoType::class])
            ->add('value', IntegerType::class, ['label' => 'admin.promo.field.value', 'attr' => ['min' => 1]])
            ->add('validFrom', DateType::class, [
                'label' => 'admin.promo.field.valid_from',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('validUntil', DateType::class, [
                'label' => 'admin.promo.field.valid_until',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('maxUses', IntegerType::class, [
                'label' => 'admin.promo.field.max_uses',
                'help' => 'admin.promo.field.max_uses_help',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('note', TextType::class, ['label' => 'admin.promo.field.note', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'admin.promo.field.active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PromoCode::class]);
    }
}
