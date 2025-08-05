<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\DTO\AbstractEmailDTO;
use App\Entity\Order;
use App\Enum\EmailTemplate;
use App\Enum\Package;
use App\Enum\River;
use DateInterval;
use DateTime;

/**
 * Data Transfer Object (DTO) for Order entities, extending AbstractEmailDTO.
 *
 * This DTO is specifically designed to transfer data from an `Order` entity,
 * particularly for purposes related to email confirmations. It encapsulates
 * key details of an order in an immutable, readable format and provides
 * methods to retrieve the data, including the associated email template and
 * context for rendering emails.
 */
readonly class OrderDTO extends AbstractEmailDTO
{
    private string $email;
    private string $description;
    private River $river;
    private int $amountOfPeople;
    private Package $package;
    private DateTime $startDate;
    private DateInterval $duration;
    private int $id;
    
    /**
     * Constructs an OrderDTO from an Order entity.
     *
     * Initializes the DTO properties by extracting relevant data from the
     * provided `Order` entity, ensuring immutability once created.
     *
     * @param Order $order The Order entity from which to populate the DTO.
     */
    public function __construct(Order $order)
    {
        $this->email = $order->getEmail();
        $this->description = $order->getDescription();
        $this->river = $order->getRiver();
        $this->amountOfPeople = $order->getAmountOfPeople();
        $this->package = $order->getPackage();
        $this->startDate = $order->getStartDate();
        $this->duration = $order->getDuration();
        $this->id = $order->getId();
    }

    /**
     * Retrieves the email address associated with the order.
     *
     * @return string The order's email address.
     */
    public function getEmail(): string
    {
        return $this->email;
    }
    
    /**
     * Retrieves the description of the order.
     *
     * @return string The order's description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * Retrieves the River enum case for the order.
     *
     * @return River The river selected for the order.
     */
    public function getRiver(): River
    {
        return $this->river;
    }
    
    /**
     * Retrieves the number of people for the order.
     *
     * @return int The amount of people for the order.
     */
    public function getAmountOfPeople(): int
    {
        return $this->amountOfPeople;
    }
    
    /**
     * Retrieves the Package enum case for the order.
     *
     * @return Package The package type selected for the order.
     */
    public function getPackage(): Package
    {
        return $this->package;
    }
    
    /**
     * Retrieves the start date of the order.
     *
     * @return DateTime The start date of the order.
     */
    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }
    
    /**
     * Retrieves the duration of the order as a DateInterval.
     *
     * @return DateInterval The duration of the order.
     */
    public function getDuration(): DateInterval
    {
        return $this->duration;
    }
    
    /**
     * Retrieves the ID of the order.
     *
     * @return int The order's ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Specifies the email template to be used for this order DTO.
     *
     * For OrderDTO, this is always `EmailTemplate::ORDER_CONFIRMATION`.
     *
     * @return EmailTemplate The email template for order confirmation.
     */
    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::ORDER_CONFIRMATION;
    }
    
    /**
     * Provides the context data needed for rendering the order confirmation email.
     *
     * Returns an associative array containing all relevant order details that
     * can be used by a Twig template to generate the email content.
     *
     * @return array An associative array of context data for the email template.
     */
    public function getContext(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'river' => $this->river,
            'amountOfPeople' => $this->amountOfPeople,
            'package' => $this->package,
            'startDate' => $this->startDate,
            'duration' => $this->duration,
            'email' => $this->email
        ];
    }

    /**
     * Retrieves the duration of the order in days.
     *
     * Extracts the 'd' property from the DateInterval to get the number of days.
     *
     * @return int The duration of the order in days.
     */
    public function getDurationDays(): int
    {
        return $this->duration->d;
    }
}