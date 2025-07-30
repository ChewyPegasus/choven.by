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

/**
 * Custom Symfony Form Type for handling phone numbers.
 *
 * This form type integrates with `libphonenumber` to allow users to input
 * phone numbers in various formats. It transforms the string input into a
 * `PhoneNumber` object for the underlying data and vice-versa for display.
 * It also applies a custom validation constraint for phone numbers.
 */
class PhoneNumberType extends AbstractType implements DataTransformerInterface
{
    /**
     * @var string The default region code used for parsing phone numbers (e.g., 'BY' for Belarus).
     */
    private string $defaultRegion;

    /**
     * Constructs a new PhoneNumberType instance.
     *
     * @param string $defaultRegion The default region code. Defaults to 'BY'.
     */
    public function __construct(string $defaultRegion = 'BY')
    {
        $this->defaultRegion = $defaultRegion;
    }

    /**
     * Builds the form by adding a model transformer.
     *
     * The `PhoneNumberType` itself acts as the data transformer between the
     * string representation in the form field and the `PhoneNumber` object in the entity.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @param array $options The options for this form type.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this);
    }

    /**
     * Configures the options for this form type.
     *
     * Sets default constraints (using `PhoneNumberConstraint`) and HTML attributes
     * like placeholder and CSS class for the input field.
     *
     * @param OptionsResolver $resolver The options resolver.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                // Apply the custom PhoneNumber constraint with the default region
                new PhoneNumberConstraint(['defaultRegion' => $this->defaultRegion]),
            ],
            'attr' => [
                'placeholder' => '+375XXXXXXXXX', // Example placeholder
                'class' => 'form-control', // Bootstrap class for styling
            ],
        ]);
    }

    /**
     * Returns the name of the parent type.
     *
     * This form type extends `TextType`, meaning it will render as a standard text input.
     *
     * @return string The parent type class name.
     */
    public function getParent(): string
    {
        return TextType::class;
    }

    /**
     * Transforms a `PhoneNumber` object into its string representation for the form field.
     *
     * This method is called when data is read from the underlying entity/object
     * and displayed in the form. It formats the `PhoneNumber` object into an
     * international string format.
     *
     * @param mixed $value The value from the entity (expected to be PhoneNumber or null).
     * @return string The formatted phone number string, or an empty string if null.
     */
    public function transform($value): string
    {
        if ($value instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            
            return $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
        }

        return '';
    }

    /**
     * Transforms a string value from the form field back into a `PhoneNumber` object.
     *
     * This method is called when the form is submitted and data is written back
     * to the underlying entity/object. It parses the string input into a
     * `PhoneNumber` object using the default region.
     *
     * @param mixed $value The string value from the form field.
     * @return PhoneNumber|null The parsed PhoneNumber object, or null if the input is empty.
     * @throws TransformationFailedException If the phone number string cannot be parsed.
     */
    public function reverseTransform($value): ?PhoneNumber
    {
        if (empty($value)) {
            return null;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        
        try {
            // Parse the phone number string using the default region
            return $phoneUtil->parse($value, $this->defaultRegion);
        } catch (NumberParseException $e) {
            // Throw a TransformationFailedException if parsing fails, which Symfony Form will catch
            throw new TransformationFailedException('Invalid phone number');
        }
    }
}