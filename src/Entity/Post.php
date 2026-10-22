<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\Localized;
use App\Repository\PostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Blog article (body in Markdown, one text per language).
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'posts')]
#[ORM\Index(name: 'idx_posts_published', columns: ['is_published', 'published_at'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'tour.slug.already_used')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'tour.slug.invalid')]
    private string $slug = '';

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $title = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $excerpt = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $body = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\ManyToOne(targetEntity: Tour::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tour $tour = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $author = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function prepareSave(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        if ($this->isPublished && null === $this->publishedAt) {
            $this->publishedAt = $this->updatedAt;
        }
    }

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $context): void
    {
        foreach (['title' => $this->title, 'body' => $this->body] as $field => $values) {
            if ('' === Localized::pick($values, Localized::DEFAULT_LOCALE)) {
                $context->buildViolation('localized.default_required')->atPath($field)->addViolation();
            }
        }
    }

    public function isVisible(): bool
    {
        return $this->isPublished && null !== $this->publishedAt && $this->publishedAt <= new \DateTimeImmutable();
    }

    /**
     * Rough reading time in minutes (200 words per minute).
     */
    public function getReadingMinutes(?string $locale = null): int
    {
        $words = str_word_count(strip_tags(Localized::pick($this->body, $locale)), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюяІіЎў');

        return max(1, (int) ceil($words / 200));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = mb_strtolower(trim((string) $slug));

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTitle(): array
    {
        return $this->title;
    }

    /**
     * @param array<string, mixed> $title
     */
    public function setTitle(array $title): static
    {
        $this->title = Localized::normalize($title);

        return $this;
    }

    public function getLocalizedTitle(?string $locale = null): string
    {
        return Localized::pick($this->title, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function getExcerpt(): array
    {
        return $this->excerpt;
    }

    /**
     * @param array<string, mixed> $excerpt
     */
    public function setExcerpt(array $excerpt): static
    {
        $this->excerpt = Localized::normalize($excerpt);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function setBody(array $body): static
    {
        $this->body = Localized::normalize($body);

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getTour(): ?Tour
    {
        return $this->tour;
    }

    public function setTour(?Tour $tour): static
    {
        $this->tour = $tour;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $author = null !== $author ? trim($author) : null;
        $this->author = '' === $author ? null : $author;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
