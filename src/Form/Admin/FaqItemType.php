<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\FaqItem;
use App\Form\Type\LocalizedTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<FaqItem>
 */
final class FaqItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', LocalizedTextType::class, ['label' => 'admin.faq.field.question', 'max_length' => 255])
            ->add('answer', LocalizedTextType::class, [
                'label' => 'admin.faq.field.answer',
                'help' => 'admin.common.markdown_help',
                'multiline' => true,
                'rows' => 5,
            ])
            ->add('position', IntegerType::class, ['label' => 'admin.common.position', 'help' => 'admin.common.position_help'])
            ->add('showOnHome', CheckboxType::class, ['label' => 'admin.faq.field.show_on_home', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'admin.faq.field.active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FaqItem::class]);
    }
}
