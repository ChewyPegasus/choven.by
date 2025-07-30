<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Entity\User;
use App\Repository\Interfaces\UserRepositoryInterface;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Provides user loading capabilities for Symfony Security, supporting
 * authentication by email or phone number.
 *
 * This class implements the UserProviderInterface to load User entities
 * from the database based on a given identifier. It uses different strategies
 * to determine if the identifier is an email or a phone number.
 *
 * @implements UserProviderInterface<User>
 */
class UserProvider implements UserProviderInterface
{
    /**
     * @var array<int, array{detector: callable(string): bool, finder: callable(string): ?User, name: string}>
     * An array of strategies to detect and find users by different identifiers (e.g., email, phone).
     */
    private array $identifierStrategies;

    /**
     * Constructs a new UserProvider instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for accessing User entities.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
        $this->initializeStrategies();
    }

    /**
     * Initializes the identifier strategies.
     *
     * This method sets up the different ways to identify a user,
     * such as by email (checking for '@' symbol) or by phone number
     * (using a regex to detect phone-like strings).
     */
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

    /**
     * Loads a user by their unique identifier (email or phone number).
     *
     * This method iterates through the defined identifier strategies to find a user.
     * It first checks if the identifier matches a strategy's detector, then attempts
     * to find the user using the corresponding finder.
     *
     * @param string $identifier The unique identifier (email or phone) of the user.
     * @return UserInterface The loaded User object.
     * @throws UserNotFoundException If no user is found for the given identifier.
     */
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
     * Checks if the given identifier string appears to be a phone number.
     *
     * This method uses a regular expression to loosely validate if the string
     * contains characters typically found in phone numbers (digits, +, spaces, -, parentheses).
     *
     * @param string $identifier The string to check.
     * @return bool True if the identifier looks like a phone number, false otherwise.
     */
    private function isPhoneNumber(string $identifier): bool
    {
        return preg_match('/^[\+\d\s\-\(\)]+$/', $identifier) === 1;
    }

    /**
     * Finds a user by their email address.
     *
     * @param string $identifier The email address to search for.
     * @return User|null The User object if found, otherwise null.
     */
    private function findByEmail(string $identifier): ?User
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        return $user instanceof User ? $user : null;
    }

    /**
     * Finds a user by their phone number, normalizing it using libphonenumber.
     *
     * This method attempts to parse and format the given identifier as a phone number
     * (assuming 'BY' as the default region for parsing). It then compares the
     * E.164 formatted number with the E.164 formatted phone numbers of all users.
     *
     * @param string $identifier The phone number to search for.
     * @return User|null The User object if found, otherwise null.
     */
    private function findByPhone(string $identifier): ?User
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $phoneNumber = $phoneUtil->parse($identifier, 'BY'); // Assuming 'BY' as default region
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
            // Failed to parse phone number, treat as not found
            return null;
        }

        return null;
    }

    /**
     * Refreshes the user instance.
     *
     * This method is called by Symfony Security to reload the user from the session
     * or after a successful authentication. It ensures the user object is up-to-date.
     *
     * @param UserInterface $user The user instance to refresh.
     * @return UserInterface The refreshed User object.
     * @throws UnsupportedUserException If the user class is not supported by this provider.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        // Reload the user by their identifier to ensure the latest data
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class The class name of the user.
     * @return bool True if the class is supported, false otherwise.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}