<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\FormOrderDTO;
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
            ])
            ->add('startDate', DateType::class, [
                'label' => $this->translator->trans('order.form.start_date'),
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => $this->translator->trans('order.form.duration'),
                'attr' => [
                    'min' => 1,
                    'max' => 7,
                    'class' => 'form-control',
                ],
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
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => $this->translator->trans('order.form.people_count'),
                'attr' => [
                    'min' => 1,
                    'max' => 50,
                    'class' => 'form-control',
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
            'data_class' => FormOrderDTO::class,
            'is_authenticated' => false,
        ]);
    }
}