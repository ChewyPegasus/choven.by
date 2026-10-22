<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Extra;
use App\Enum\ExtraUnit;
use App\Form\Type\LocalizedTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Extra>
 */
final class ExtraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', LocalizedTextType::class, ['label' => 'admin.extras.field.name', 'max_length' => 120])
            ->add('description', LocalizedTextType::class, [
                'label' => 'admin.extras.field.description',
                'required' => false,
                'multiline' => true,
                'rows' => 2,
                'max_length' => 300,
            ])
            ->add('price', IntegerType::class, ['label' => 'admin.extras.field.price', 'attr' => ['min' => 0]])
            ->add('unit', EnumType::class, ['label' => 'admin.extras.field.unit', 'class' => ExtraUnit::class])
            ->add('perDay', CheckboxType::class, ['label' => 'admin.extras.field.per_day', 'required' => false])
            ->add('maxQuantity', IntegerType::class, [
                'label' => 'admin.extras.field.max_quantity',
                'help' => 'admin.extras.field.max_quantity_help',
                'attr' => ['min' => 1, 'max' => 50],
            ])
            ->add('icon', TextType::class, [
                'label' => 'admin.extras.field.icon',
                'help' => 'admin.extras.field.icon_help',
                'required' => false,
                'attr' => ['placeholder' => 'fa-van-shuttle'],
            ])
            ->add('position', IntegerType::class, ['label' => 'admin.common.position', 'help' => 'admin.common.position_help'])
            ->add('isActive', CheckboxType::class, ['label' => 'admin.extras.field.active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Extra::class]);
    }
}
