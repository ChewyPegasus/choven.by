<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Order;
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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class OrderForm extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('order.form.email'),
                'attr' => [
                    'placeholder' => $this->translator->trans('order.form.email_placeholder'),
                    'class' => 'form-control',
                    'readonly' => $options['is_authenticated'],
                ],
                'constraints' => $options['is_authenticated'] ? [] : [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.email_required'),
                    ]),
                    new Email([
                        'message' => $this->translator->trans('order.form.error.email_invalid'),
                    ]),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => $this->translator->trans('order.form.start_date'),
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.date_required'),
                    ]),
                ],
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => $this->translator->trans('order.form.duration'),
                'mapped' => false,
                'attr' => [
                    'min' => 1,
                    'max' => 7,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.duration_required'),
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 7,
                        'notInRangeMessage' => $this->translator->trans('order.form.error.duration_range'),
                    ]),
                ]
            ])
            ->add('river', EnumType::class, [
                'label' => $this->translator->trans('order.form.river'),
                'class' => River::class,
                'choice_label' => function(River $river) {
                    
                    return $this->translator->trans($river->getLabel());
                },
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.river_required'),
                    ]),
                ],
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => $this->translator->trans('order.form.people_count'),
                'attr' => [
                    'min' => 1,
                    'max' => 50,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.people_required'),
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 50,
                        'notInRangeMessage' => $this->translator->trans('order.form.error.people_range')]),
                    ],
            ])
            ->add('package', EnumType::class, [
                'label' => $this->translator->trans('order.form.type'),
                'class' => Package::class,
                'choice_label' => function(Package $package) {

                    return $this->translator->trans($package->getLabel());
                },
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('order.form.error.type_required'),
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('order.form.description'),
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => $this->translator->trans('order.form.description_placeholder'),
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'is_authenticated' => false,
        ]);
    }
}
