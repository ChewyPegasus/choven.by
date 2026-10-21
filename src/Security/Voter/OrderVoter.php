<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A booking belongs to the account that made it; administrators can see everything.
 * (Guests access their bookings through the secret link from the confirmation email instead.).
 *
 * @extends Voter<string, Order>
 */
final class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';
    public const CANCEL = 'ORDER_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::CANCEL], true) && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isOwner = null !== $subject->getUser() && $subject->getUser()->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $isOwner || $user->isAdmin(),
            self::CANCEL => $isOwner && $subject->canBeCancelledByCustomer(),
            default => false,
        };
    }
}
