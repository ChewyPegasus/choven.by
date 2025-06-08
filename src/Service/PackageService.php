<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Package;
use Symfony\Contracts\Translation\TranslatorInterface;

class PackageService {
    public function __construct(
        private readonly TranslatorInterface $translator
    )
    {
    }

    public function getPackageDetails(Package $package): array {
        return [
            'name' => $this->translator->trans($package->getLabel()),
            'description' => $this->translator->trans($package->getDescription()),
            'features' => array_map(
                fn($feature) => $this->translator->trans($feature),
                $package->getFeatures()
            ),
        ];
    }

    public function getAllPackages(): array
    {
        $packages = [];
        foreach (Package::cases() as $package) {
            $packages[$package->value] = $this->getPackageDetails($package);
        }
        
        return $packages;
    }
}