<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ExtraUnit;
use App\Model\Localized;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An add-on chosen for a booking. Name and price are copied, so later price changes do not alter old bookings.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_extras')]
class OrderExtra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'extras')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Extra::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Extra $extra;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $name;

    #[ORM\Column(length: 10, enumType: ExtraUnit::class)]
    private ExtraUnit $unit;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $quantity;

    #[ORM\Column]
    private int $unitPrice;

    #[ORM\Column]
    private int $total;

    public function __construct(Order $order, Extra $extra, int $quantity, int $total)
    {
        $this->order = $order;
        $this->extra = $extra;
        $this->name = $extra->getName();
        $this->unit = $extra->getUnit();
        $this->quantity = $quantity;
        $this->unitPrice = $extra->getPrice();
        $this->total = $total;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getExtra(): ?Extra
    {
        return $this->extra;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    public function getLocalizedName(?string $locale = null): string
    {
        return Localized::pick($this->name, $locale);
    }

    public function getUnit(): ExtraUnit
    {
        return $this->unit;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
