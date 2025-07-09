<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = null;
        
        if (str_contains($identifier, '@')) {
            // This is an email
            $user = $this->userRepository->findOneBy(['email' => $identifier]);
        } else {
            // Try to find by phone number
            $phoneUtil = PhoneNumberUtil::getInstance();
            try {
                $phoneNumber = $phoneUtil->parse($identifier, 'BY');
                $formattedPhone = $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
                
                // Search for user by formatted phone number
                $users = $this->userRepository->findAll();
                foreach ($users as $potentialUser) {
                    if ($potentialUser->getPhone()) {
                        $userPhoneFormatted = $phoneUtil->format($potentialUser->getPhone(), \libphonenumber\PhoneNumberFormat::E164);
                        if ($userPhoneFormatted === $formattedPhone) {
                            $user = $potentialUser;
                            break;
                        }
                    }
                }
            } catch (NumberParseException $e) {
                // If parsing as phone failed, try to find as email
                $user = $this->userRepository->findOneBy(['email' => $identifier]);
            }
        }

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with identifier "%s" not found.', $identifier));
        }

        return $user;
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