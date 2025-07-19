<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Entity\User;
use App\Repository\UserRepository;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<User>
 */
class UserProvider implements UserProviderInterface
{
    /**
     * @var array<int, array{detector: callable(string): bool, finder: callable(string): ?User, name: string}>
     */
    private array $identifierStrategies;

    public function __construct(private readonly UserRepository $userRepository)
    {
        $this->initializeStrategies();
    }

    private function initializeStrategies(): void
    {
        $this->identifierStrategies = [
            [
                'detector' => fn(string $identifier): bool => str_contains($identifier, '@'),
                'finder' => fn(string $identifier): ?User => $this->findByEmail($identifier),
                'name' => 'email'
            ],
            [
                'detector' => fn(string $identifier): bool => $this->isPhoneNumber($identifier),
                'finder' => fn(string $identifier): ?User => $this->findByPhone($identifier),
                'name' => 'phone'
            ],
        ];
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        foreach ($this->identifierStrategies as $strategy) {
            if ($strategy['detector']($identifier)) {
                $user = $strategy['finder']($identifier);
                if ($user) {
                    return $user;
                }
            }
        }

        throw new UserNotFoundException(sprintf('User with identifier "%s" not found.', $identifier));
    }

    /**
     * Checks if the identifier looks like a phone number.
     */
    private function isPhoneNumber(string $identifier): bool
    {
        return preg_match('/^[\+\d\s\-\(\)]+$/', $identifier) === 1;
    }

    private function findByEmail(string $identifier): ?User
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        return $user instanceof User ? $user : null;
    }

    /**
     * Finds a user by phone number using libphonenumber for normalization.
     */
    private function findByPhone(string $identifier): ?User
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $phoneNumber = $phoneUtil->parse($identifier, 'BY');
            $formattedPhone = $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);

            foreach ($this->userRepository->findAll() as $user) {
                if ($user instanceof User && $user->getPhone()) {
                    $userPhoneFormatted = $phoneUtil->format($user->getPhone(), \libphonenumber\PhoneNumberFormat::E164);
                    if ($userPhoneFormatted === $formattedPhone) {
                        return $user;
                    }
                }
            }
        } catch (NumberParseException) {
            // Failed to parse phone number
            return null;
        }

        return null;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}