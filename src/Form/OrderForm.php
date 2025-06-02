<?php

namespace App\Form;

use App\Entity\Order;
use App\Enum\River;
use App\Enum\Type;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Ваш email',
                'attr' => [
                    'placeholder' => 'Введите ваш email',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите email',
                    ]),
                    new Email([
                        'message' => 'Введите корректный email',
                    ]),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Дата сплава',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, выберите дату',
                    ]),
                ],
            ])
            ->add('duration', DateIntervalType::class, [
                'label' => 'Продолжительность (дней)',
                'with_years' => false,
                'with_months' => false,
                'with_days' => true,
                'days' => array_combine(range(1, 7), range(1, 7)),
                'attr' => [
                    'class' => 'form-control',
                    'list' => 'duration-options',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, укажите продолжительность сплава',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 7,
                        'notInRangeMessage' => 'Длительность должна быть от {{ min }} до {{ max }} дней'
                    ]),
                ]
            ])
            ->add('river', EnumType::class, [
                'label' => 'Река',
                'class' => River::class,
                'choice_label' => function(River $river) {
                    return $river->getLabel();
                },
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, выберите реку',
                    ]),
                ],
            ])
            ->add('amountOfPeople', IntegerType::class, [
                'label' => 'Количество участников',
                'attr' => [
                    'min' => 1,
                    'max' => 50,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, укажите количество участников',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 50,
                        'notInRangeMessage' => 'Количество участников должно быть от {{ min }} до {{ max }}']),
                    ],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Тип сплава',
                'class' => Type::class,
                'choice_label' => function(Type $type) {
                    return $type->getLabel();
                },
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, выберите тип сплава',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Дополнительная информация',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Укажите дополнительные пожелания или вопросы',
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
