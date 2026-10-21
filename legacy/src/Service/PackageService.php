<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Package;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for retrieving and translating package details.
 *
 * This service provides methods to get detailed information about individual
 * `Package` enum cases, including their translated names, descriptions, and features,
 * or to retrieve details for all available packages.
 */
class PackageService 
{
    /**
     * Constructs a new PackageService instance.
     *
     * @param TranslatorInterface $translator The Symfony translator service for internationalization.
     */
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * Retrieves the translated details for a specific package.
     *
     * This method takes a `Package` enum case and returns an associative array
     * containing its translated name, description, and a list of translated features.
     *
     * @param Package $package The Package enum case for which to retrieve details.
     * @return array<string, mixed> An associative array with 'name', 'description', and 'features' (array of strings).
     */
    public function getPackageDetails(Package $package): array
    {
        return [
            'name' => $this->translator->trans($package->getLabel()),
            'description' => $this->translator->trans($package->getDescription()),
            'features' => array_map(
                fn($feature) => $this->translator->trans($feature), // Translate each feature key
                $package->getFeatures()
            ),
        ];
    }

    /**
     * Retrieves translated details for all available packages.
     *
     * This method iterates through all `Package` enum cases and uses `getPackageDetails`
     * to compile a comprehensive list of all packages with their translated information.
     * The returned array is keyed by the raw string value of the `Package` enum case.
     *
     * @return array<string, array<string, mixed>> An associative array where keys are package string values
     * and values are arrays of package details.
     */
    public function getAllPackages(): array
    {
        $packages = [];
        foreach (Package::cases() as $package) {
            $packages[$package->value] = $this->getPackageDetails($package);
        }
        
        return $packages;
    }
}