<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\Localized;
use App\Repository\FaqItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A question and answer of the FAQ page (the answer is Markdown).
 */
#[ORM\Entity(repositoryClass: FaqItemRepository::class)]
#[ORM\Table(name: 'faq_items')]
#[ORM\HasLifecycleCallbacks]
class FaqItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $question = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $answer = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /** Shown in the short FAQ block on the landing page. */
    #[ORM\Column(options: ['default' => false])]
    private bool $showOnHome = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $context): void
    {
        foreach (['question' => $this->question, 'answer' => $this->answer] as $field => $values) {
            if ('' === Localized::pick($values, Localized::DEFAULT_LOCALE)) {
                $context->buildViolation('localized.default_required')->atPath($field)->addViolation();
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<string, string>
     */
    public function getQuestion(): array
    {
        return $this->question;
    }

    /**
     * @param array<string, mixed> $question
     */
    public function setQuestion(array $question): static
    {
        $this->question = Localized::normalize($question);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAnswer(): array
    {
        return $this->answer;
    }

    /**
     * @param array<string, mixed> $answer
     */
    public function setAnswer(array $answer): static
    {
        $this->answer = Localized::normalize($answer);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isShowOnHome(): bool
    {
        return $this->showOnHome;
    }

    public function setShowOnHome(bool $showOnHome): static
    {
        $this->showOnHome = $showOnHome;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
