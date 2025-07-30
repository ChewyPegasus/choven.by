<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a user of the application.
 *
 * This entity stores user information, including authentication credentials,
 * roles, contact details, and confirmation status. It also manages the
 * one-to-many relationship with orders placed by the user.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_exists')]
#[UniqueEntity(fields: ['phone'], message: 'user.phone.already_exists')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @var int|null The unique identifier for the user.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var string|null The user's email address, which must be unique.
     */
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'user.email.not_blank')]
    #[Assert\Email(message: 'user.email.invalid')]
    private ?string $email = null;

    /**
     * @var PhoneNumber|null The user's phone number, which must be unique.
     * This field uses a custom Doctrine type for libphonenumber's PhoneNumber object.
     */
    #[ORM\Column(type: 'phone_number', unique: true, nullable: true)]
    #[Assert\NotBlank(message: 'user.phone.not_blank')]
    private ?PhoneNumber $phone = null;

    /**
     * @var string|null The hashed password for the user.
     */
    #[ORM\Column(length: 255)]
    private ?string $password = null;
    
    /**
     * @var string|null A transient property to hold the plain password during registration/password change.
     * It is not persisted to the database.
     */
    private ?string $plainPassword = null;

    /**
     * @var array<Role> An array of roles assigned to the user.
     * Roles are stored as `Role` enum objects.
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * @var string|null A temporary code used for email confirmation.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $confirmationCode = null;

    /**
     * @var bool|null The confirmation status of the user's email address.
     */
    #[ORM\Column]
    private ?bool $isConfirmed = null;

    /**
     * @var Collection<int, Order> A collection of orders placed by this user.
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    /**
     * Constructs a new User instance.
     *
     * Initializes default roles (ROLE_USER), sets `isConfirmed` to false,
     * and initializes the orders collection.
     */
    public function __construct()
    {
        $this->roles = [Role::USER]; // Default role
        $this->isConfirmed = false;
        $this->orders = new ArrayCollection();
    }

    /**
     * Gets the unique identifier for the user.
     *
     * @return int|null The user ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the user's email address.
     *
     * @return string|null The email address.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the user's email address.
     *
     * @param string $email The email address to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Gets the user's hashed password.
     *
     * @return string|null The hashed password.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Sets the user's hashed password.
     *
     * @param string $password The hashed password to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Gets the transient plain password.
     *
     * This method is used by forms or services to retrieve the raw password
     * before hashing. It is not for persistence.
     *
     * @return string|null The plain password.
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * Sets the transient plain password.
     *
     * @param string $plainPassword The plain password to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setPlainPassword(string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    /**
     * Returns the roles granted to the user.
     *
     * This method converts the internal `Role` enum array into an array
     * of strings prefixed with 'ROLE_' as required by Symfony Security.
     * Ensures 'ROLE_USER' is always present.
     *
     * @return string[] An array of roles (e.g., ['ROLE_USER', 'ROLE_ADMIN']).
     */
    public function getRoles(): array
    {
        $roles = array_map(function($role) {
            // If role is already an enum object, return its string representation
            if ($role instanceof Role) {
                return 'ROLE_' . strtoupper($role->value);
            }
            // If role is a string, try to create enum from it
            if (is_string($role)) {
                // Check if string starts with ROLE_
                if (str_starts_with($role, 'ROLE_')) {
                    $enumValue = strtolower(str_replace('ROLE_', '', $role));
                } else {
                    $enumValue = strtolower($role);
                }
                
                // Try to create enum from string
                try {
                    $enumRole = Role::from($enumValue);
                    return 'ROLE_' . strtoupper($enumRole->value);
                } catch (\ValueError $e) {
                    // If enum creation failed, return string as is
                    return str_starts_with($role, 'ROLE_') ? $role : 'ROLE_' . strtoupper($role);
                }
            }
            
            return 'ROLE_USER'; // fallback
        }, $this->roles);
        
        // Ensure ROLE_USER is present
        $roles[] = 'ROLE_USER';
        
        return array_unique($roles);
    }

    /**
     * Returns the roles granted to the user as Role enum objects.
     *
     * Ensures `Role::USER` is always present in the returned array.
     *
     * @return Role[] An array of Role enum objects.
     */
    public function getRoleEnums(): array
    {
        // at least Role::USER
        if (!in_array(Role::USER, $this->roles, true)) {
            $this->roles[] = Role::USER;
        }
        
        $uniqueRoles = [];
        foreach ($this->roles as $role) {
            if (!in_array($role, $uniqueRoles, true)) {
                $uniqueRoles[] = $role;
            }
        }
        
        return $uniqueRoles;
    }

    /**
     * Sets the roles for the user.
     *
     * This method accepts an array of `Role` enum objects or strings,
     * normalizes them to `Role` enums, and ensures `Role::USER` is always included.
     *
     * @param Role[]|string[] $roles An array of roles to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setRoles(array $roles): static
    {
        $normalizedRoles = [];
        
        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $normalizedRoles[] = $role;
            } elseif (is_string($role)) {
                // Remove the ROLE_ prefix if present
                $enumValue = str_starts_with($role, 'ROLE_') 
                    ? strtolower(str_replace('ROLE_', '', $role))
                    : strtolower($role);
                
                try {
                    $normalizedRoles[] = Role::from($enumValue);
                } catch (\ValueError $e) {
                    // If enum creation failed, ignore this role
                    continue;
                }
            }
        }
        
        // Ensure Role::USER is present
        if (!in_array(Role::USER, $normalizedRoles, true)) {
            $normalizedRoles[] = Role::USER;
        }
        
        $uniqueRoles = [];
        foreach ($normalizedRoles as $role) {
            if (!in_array($role, $uniqueRoles, true)) {
                $uniqueRoles[] = $role;
            }
        }
        
        $this->roles = $uniqueRoles;
        return $this;
    }

    /**
     * Checks if the user has the 'ROLE_ADMIN' role.
     *
     * @return bool True if the user is an admin, false otherwise.
     */
    public function isAdmin(): bool
    {
        // check for enum objects
        if (in_array(Role::ADMIN, $this->roles, true)) {
            return true;
        }
        
        // Check strings (for backward compatibility if roles were not strictly enums initially)
        foreach ($this->roles as $role) {
            if (is_string($role) && (strtolower($role) === 'admin' || $role === 'ROLE_ADMIN')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Checks if the user has a specific role.
     *
     * @param Role $role The Role enum to check for.
     * @return bool True if the user has the role, false otherwise.
     */
    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Adds a role to the user.
     *
     * Adds the specified Role enum to the user's roles if not already present.
     *
     * @param Role $role The Role enum to add.
     * @return self Returns the current instance for method chaining.
     */
    public function addRole(Role $role): self
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
        }
        
        return $this;
    }

    /**
     * Removes a role from the user.
     *
     * Removes the specified Role enum from the user's roles.
     * Handles both enum objects and string representations for backward compatibility.
     *
     * @param Role $role The Role enum to remove.
     * @return self Returns the current instance for method chaining.
     */
    public function removeRole(Role $role): self
    {
        // Remove enum object
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
            return $this;
        }
        
        // Check strings (for backward compatibility)
        $roleString = $role->value; // 'admin'
        $roleSfString = 'ROLE_' . strtoupper($role->value); // 'ROLE_ADMIN'
        
        foreach ($this->roles as $index => $existingRole) {
            if (is_string($existingRole) && 
                ($existingRole === $roleString || $existingRole === $roleSfString)) {
                unset($this->roles[$index]);
                $this->roles = array_values($this->roles);
                break;
            }
        }
        
        return $this;
    }

    /**
     * Gets the confirmation code for email verification.
     *
     * @return string|null The confirmation code.
     */
    public function getConfirmationCode(): ?string
    {
        return $this->confirmationCode;
    }

    /**
     * Sets the confirmation code for email verification.
     *
     * @param string|null $confirmationCode The confirmation code to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setConfirmationCode(?string $confirmationCode): static
    {
        $this->confirmationCode = $confirmationCode;
        return $this;
    }

    /**
     * Checks if the user's email is confirmed.
     *
     * @return bool|null True if confirmed, false otherwise.
     */
    public function isConfirmed(): ?bool
    {
        return $this->isConfirmed;
    }

    /**
     * Sets the email confirmation status for the user.
     *
     * @param bool $isConfirmed The confirmation status to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;
        return $this;
    }

    /**
     * Removes sensitive data from the user object (e.g., plain password).
     *
     * Implemented as part of `UserInterface`.
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /**
     * Returns the unique identifier for the user.
     *
     * In this case, the email address is used as the user identifier.
     * Implemented as part of `UserInterface`.
     *
     * @return string The user's email address.
     */
    public function getUserIdentifier(): string 
    {
        return $this->email;
    }

    /**
     * Gets the collection of orders associated with this user.
     *
     * @return Collection<int, Order> A collection of Order entities.
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /**
     * Adds an order to the user's collection of orders.
     *
     * Also sets the user on the order, maintaining the bidirectional relationship.
     *
     * @param Order $order The Order entity to add.
     * @return static Returns the current instance for method chaining.
     */
    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }
        
        return $this;
    }

    /**
     * Removes an order from the user's collection of orders.
     *
     * Also removes the user from the order if it was associated,
     * maintaining the bidirectional relationship.
     *
     * @param Order $order The Order entity to remove.
     * @return static Returns the current instance for method chaining.
     */
    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }
        
        return $this;
    }

    /**
     * Gets the user's phone number as a PhoneNumber object.
     *
     * @return PhoneNumber|null The phone number object.
     */
    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    /**
     * Sets the user's phone number.
     *
     * @param PhoneNumber|null $phone The PhoneNumber object to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setPhone(?PhoneNumber $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * Gets the user's phone number as a formatted international string.
     *
     * Uses the `libphonenumber` library to format the phone number.
     *
     * @return string|null The formatted phone number string, or null if no phone is set.
     */
    public function getPhoneString(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        
        return $phoneUtil->format($this->phone, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
    }
}