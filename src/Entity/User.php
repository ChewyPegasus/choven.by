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

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_exists')]
#[UniqueEntity(fields: ['phone'], message: 'user.phone.already_exists')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'user.email.not_blank')]
    #[Assert\Email(message: 'user.email.invalid')]
    private ?string $email = null;

    #[ORM\Column(type: 'phone_number', unique: true, nullable: true)]
    #[Assert\NotBlank(message: 'user.phone.not_blank')]
    private ?PhoneNumber $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;
    
    private ?string $plainPassword = null;

    /**
     * @var Role[]
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $confirmationCode = null;

    #[ORM\Column]
    private ?bool $isConfirmed = null;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

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
     * @return Role[]
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
     * @param Role[]|string[] $roles
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

    public function isAdmin(): bool
    {
        // check for enum objects
        if (in_array(Role::ADMIN, $this->roles, true)) {
            return true;
        }
        
        // Check strings (for backward compatibility)
        foreach ($this->roles as $role) {
            if (is_string($role) && (strtolower($role) === 'admin' || $role === 'ROLE_ADMIN')) {
                return true;
            }
    }
    
    return false;
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function addRole(Role $role): self
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
            // $this->roles = array_unique($this->roles);
        }
        
        return $this;
    }

    public function removeRole(Role $role): self
    {
        // enum
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

    public function getConfirmationCode(): ?string
    {
        return $this->confirmationCode;
    }

    public function setConfirmationCode(?string $confirmationCode): static
    {
        $this->confirmationCode = $confirmationCode;
        return $this;
    }

    public function isConfirmed(): ?bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string 
    {
        return $this->email;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }
        
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }
        
        return $this;
    }

    public function __construct()
    {
        $this->roles = [Role::USER];
        $this->isConfirmed = false;
        $this->orders = new ArrayCollection();
    }

    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function setPhone(?PhoneNumber $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getPhoneString(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        
        return $phoneUtil->format($this->phone, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
    }
}