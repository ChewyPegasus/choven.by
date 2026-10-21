<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Model\Localized;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * One input per site language, bound to a {locale: text} array.
 * Only the default language (Russian) can be required; the others fall back to it.
 *
 * @extends AbstractType<array<string, string>>
 */
final class LocalizedTextType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Localized::LOCALES as $locale) {
            $isDefault = Localized::DEFAULT_LOCALE === $locale;
            $required = $isDefault && $options['required'];

            $constraints = [new Length(max: $options['max_length'])];
            if ($required) {
                $constraints[] = new NotBlank();
            }

            $builder->add($locale, $options['multiline'] ? TextareaType::class : TextType::class, [
                'label' => 'locale.'.$locale,
                'required' => $required,
                'constraints' => $constraints,
                'attr' => $options['multiline'] ? ['rows' => $options['rows']] : [],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiline' => false,
            'rows' => 3,
            'max_length' => 5000,
            'empty_data' => [],
            'attr' => ['class' => 'localized-fields'],
        ]);
        $resolver->setAllowedTypes('multiline', 'bool');
        $resolver->setAllowedTypes('rows', 'int');
        $resolver->setAllowedTypes('max_length', 'int');
    }

    public function getBlockPrefix(): string
    {
        return 'localized_text';
    }
}
