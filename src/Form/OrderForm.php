<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\Order\FormOrderDTO;
use App\Enum\River;
use App\Enum\Package;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form Type for creating and editing orders.
 *
 * This form defines the fields and their configurations for capturing order details,
 * including email, start date, duration, selected river, number of people,
 * package type, and an optional description. It uses translation keys for labels
 * and placeholders, and sets appropriate HTML attributes for styling and validation.
 */
class OrderForm extends AbstractType
{
    /**
     * Constructs a new OrderForm instance.
     *
     * @param TranslatorInterface $translator The translator service for internationalization.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Builds the form by adding fields and their configurations.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @param array $options The options for this form type.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('order.form.email'),
                'attr' => [
                    'placeholder' => $this->translator->trans('order.form.email_placeholder'),
                    'class' => 'form-control',
                    // Make email field read-only if the user is authenticated
                    'readonly' => $options['is_authenticated'],
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => $this->translator->trans('order.form.start_date'),
                'widget' => 'single_text', // Renders as a single HTML5 date input
                'html5' => true, // Enable HTML5 date input features
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'), // Set minimum date to today
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => $this->translator->trans('order.form.duration'),
                'attr' => [
                    'min' => 1,  // Minimum duration of 1 day
                    'max' => 7,  // Maximum duration of 7 days
                    'class' => 'form-control',
                ],
            ])
            ->add('river', EnumType::class, [
                'label' => $this->translator->trans('order.form.river'),
                'class' => River::class, // Associate with the River enum
                'choice_label' => function(River $river) {
                    // Translate the label for each river enum choice
                    return $this->translator->trans($river->getLabel());
                },
                'attr' => [
                    'class' => 'form-select', // Bootstrap class for select input
                ],
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => $this->translator->trans('order.form.people_count'),
                'attr' => [
                    'min' => 1,  // Minimum 1 person
                    'max' => 50, // Maximum 50 people
                    'class' => 'form-control',
                ],
            ])
            ->add('package', EnumType::class, [
                'label' => $this->translator->trans('order.form.type'),
                'class' => Package::class, // Associate with the Package enum
                'choice_label' => function(Package $package) {
                    // Translate the label for each package enum choice
                    return $this->translator->trans($package->getLabel());
                },
                'attr' => [
                    'class' => 'form-select', // Bootstrap class for select input
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('order.form.description'),
                'required' => false, // Make description optional
                'attr' => [
                    'rows' => 4, // Number of rows for the textarea
                    'placeholder' => $this->translator->trans('order.form.description_placeholder'),
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    /**
     * Configures the options for this form type.
     *
     * Sets the default data class to `FormOrderDTO` and introduces a custom
     * option `is_authenticated` to control the `email` field's `readonly` attribute.
     *
     * @param OptionsResolver $resolver The options resolver.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormOrderDTO::class, // The DTO this form will map to
            'is_authenticated' => false, // Custom option to indicate if the user is authenticated
        ]);
    }
}