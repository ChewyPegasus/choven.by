<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Blank;

/**
 * Invisible field that humans leave empty and naive spam bots fill in.
 *
 * @extends AbstractType<string>
 */
final class HoneypotType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'label' => false,
            'constraints' => [new Blank(message: 'form.spam_detected')],
            'attr' => [
                'class' => 'hp-field',
                'tabindex' => '-1',
                'autocomplete' => 'off',
                'aria-hidden' => 'true',
            ],
            'row_attr' => ['class' => 'hp-row', 'aria-hidden' => 'true'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
