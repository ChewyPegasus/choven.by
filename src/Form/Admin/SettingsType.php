<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Form\Type\LocalizedTextType;
use App\Service\SiteSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin-editable site settings (bound to the array from SiteSettings::all()).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $handle = new Regex(pattern: '/^[A-Za-z0-9_.]{2,64}$/', message: 'admin.settings.error.handle');

        $builder
            ->add(SiteSettings::PHONE, TextType::class, [
                'label' => 'admin.settings.field.phone',
                'constraints' => [new NotBlank(), new Length(max: 30)],
            ])
            ->add(SiteSettings::EMAIL, EmailType::class, [
                'label' => 'admin.settings.field.email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add(SiteSettings::INSTAGRAM, TextType::class, [
                'label' => 'admin.settings.field.instagram',
                'help' => 'admin.settings.field.handle_help',
                'required' => false,
                'constraints' => [$handle],
            ])
            ->add(SiteSettings::TELEGRAM, TextType::class, [
                'label' => 'admin.settings.field.telegram',
                'help' => 'admin.settings.field.handle_help',
                'required' => false,
                'constraints' => [$handle],
            ])
            ->add(SiteSettings::NOTIFICATION_EMAIL, EmailType::class, [
                'label' => 'admin.settings.field.notification_email',
                'help' => 'admin.settings.field.notification_email_help',
                'required' => false,
                'constraints' => [new Email()],
            ])
            ->add(SiteSettings::BOOKING_ENABLED, CheckboxType::class, [
                'label' => 'admin.settings.field.booking_enabled',
                'required' => false,
            ])
            ->add(SiteSettings::DAILY_CAPACITY, IntegerType::class, [
                'label' => 'admin.settings.field.daily_capacity',
                'help' => 'admin.settings.field.daily_capacity_help',
                'constraints' => [new PositiveOrZero()],
            ])
            ->add(SiteSettings::BLOCKED_DATES, TextareaType::class, [
                'label' => 'admin.settings.field.blocked_dates',
                'help' => 'admin.settings.field.blocked_dates_help',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => '2026-07-15'],
            ])
            ->add(SiteSettings::YANDEX_METRIKA, TextType::class, [
                'label' => 'admin.settings.field.yandex_metrika',
                'help' => 'admin.settings.field.analytics_help',
                'required' => false,
                'constraints' => [new Regex(pattern: '/^\d{5,12}$/', message: 'admin.settings.error.metrika')],
            ])
            ->add(SiteSettings::GOOGLE_ANALYTICS, TextType::class, [
                'label' => 'admin.settings.field.google_analytics',
                'help' => 'admin.settings.field.analytics_help',
                'required' => false,
                'attr' => ['placeholder' => 'G-XXXXXXXXXX'],
                'constraints' => [new Regex(pattern: '/^G-[A-Z0-9]{4,15}$/', message: 'admin.settings.error.ga')],
            ])
            ->add(SiteSettings::ANNOUNCEMENT, LocalizedTextType::class, [
                'label' => 'admin.settings.field.announcement',
                'help' => 'admin.settings.field.announcement_help',
                'required' => false,
                'max_length' => 300,
            ]);

        $builder->get(SiteSettings::BLOCKED_DATES)->addModelTransformer(new CallbackTransformer(
            static fn (mixed $dates): string => \is_array($dates) ? implode("\n", $dates) : '',
            static function (?string $text): array {
                $dates = [];
                foreach (preg_split('/[\s,;]+/', (string) $text, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $token)
                        ?: \DateTimeImmutable::createFromFormat('!d.m.Y', $token);
                    if (false !== $date) {
                        $dates[] = $date->format('Y-m-d');
                    }
                }
                $dates = array_values(array_unique($dates));
                sort($dates);

                return $dates;
            },
        ));
    }
}
