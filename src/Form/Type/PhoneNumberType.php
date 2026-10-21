<?php

declare(strict_types=1);

namespace App\Form\Type;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Text input mapped to a libphonenumber PhoneNumber; local numbers are read as Belarusian.
 *
 * @extends AbstractType<\libphonenumber\PhoneNumber>
 */
final class PhoneNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $util = PhoneNumberUtil::getInstance();
        $region = $options['default_region'];

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?PhoneNumber $phone): string => null !== $phone ? $util->format($phone, PhoneNumberFormat::INTERNATIONAL) : '',
            static function (?string $value) use ($util, $region): ?PhoneNumber {
                if (null === $value || '' === trim($value)) {
                    return null;
                }

                try {
                    return $util->parse($value, $region);
                } catch (NumberParseException $e) {
                    $failure = new TransformationFailedException($e->getMessage(), 0, $e);
                    $failure->setInvalidMessage('phone.invalid');

                    throw $failure;
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_region' => 'BY',
            'invalid_message' => 'phone.invalid',
            'attr' => [
                'placeholder' => '+375 29 123-45-67',
                'autocomplete' => 'tel',
                'inputmode' => 'tel',
            ],
        ]);
        $resolver->setAllowedTypes('default_region', 'string');
    }

    public function getParent(): string
    {
        return TelType::class;
    }
}
