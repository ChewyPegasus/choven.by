<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Validator\PhoneNumber as PhoneNumberConstraint;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhoneNumberType extends AbstractType implements DataTransformerInterface
{
    private string $defaultRegion;

    public function __construct(string $defaultRegion = 'BY')
    {
        $this->defaultRegion = $defaultRegion;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new PhoneNumberConstraint(['defaultRegion' => $this->defaultRegion]),
            ],
            'attr' => [
                'placeholder' => '+375XXXXXXXXX',
                'class' => 'form-control',
            ],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function transform($value): string
    {
        if ($value instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            
            return $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
        }

        return '';
    }

    public function reverseTransform($value): ?PhoneNumber
    {
        if (empty($value)) {
            return null;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        
        try {
            return $phoneUtil->parse($value, $this->defaultRegion);
        } catch (NumberParseException $e) {
            throw new TransformationFailedException('Invalid phone number');
        }
    }
}