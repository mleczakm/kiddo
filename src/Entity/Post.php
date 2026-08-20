<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
#[ORM\UniqueConstraint(name: 'uniq_post_slug', fields: ['slug'])]
#[ORM\Index(columns: ['status', 'published_at'], name: 'idx_post_publication')]
class Post
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    /** @var Collection<int, PostFile> */
    #[ORM\OneToMany(
        targetEntity: PostFile::class,
        mappedBy: 'post',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $files;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\Column(type: 'string', length: 255)]
    public string $slug;

    #[ORM\Embedded(class: PostBody::class, columnPrefix: false)]
    public PostBody $body;

    #[ORM\Embedded(class: PostSeo::class, columnPrefix: false)]
    public PostSeo $seo;

    #[ORM\Column(type: 'string', length: 20, enumType: PostStatus::class)]
    public PostStatus $status = PostStatus::DRAFT;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PostVisibility::class)]
    public PostVisibility $visibility = PostVisibility::PUBLIC;

    public function __construct(string $title, User $author, ?string $slug = null)
    {
        $this->id = new Ulid();
        $this->author = $author;
        $this->body = new PostBody($title);
        $this->seo = new PostSeo();
        $this->slug = new AsciiSlugger()
            ->slug($slug === null || $slug === '' ? $title : $slug)
            ->lower()
            ->toString();
        $this->files = new ArrayCollection();
        $this->createdAt = Clock::get()->now();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function isPublished(?\DateTimeImmutable $now = null): bool
    {
        $now ??= Clock::get()->now();
        return $this->status === PostStatus::PUBLISHED && $this->publishedAt !== null && $this->publishedAt <= $now;
    }

    public function isScheduled(?\DateTimeImmutable $now = null): bool
    {
        $now ??= Clock::get()->now();
        return $this->status === PostStatus::PUBLISHED && $this->publishedAt !== null && $this->publishedAt > $now;
    }

    public function getCoverAttachment(): ?PostFile
    {
        foreach ($this->files as $file) {
            if ($file->getRole() === PostFileRole::COVER) {
                return $file;
            }
        }
        return null;
    }

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function addFile(PostFile $file): void
    {
        if ($file->getPost() !== $this) {
            throw new \InvalidArgumentException('The attachment belongs to a different post.');
        }
        if ($this->files->contains($file)) {
            return;
        }
        if ($file->getRole() === PostFileRole::COVER && $this->getCoverAttachment() !== null) {
            throw new \DomainException('A post can have only one cover attachment.');
        }
        $this->files->add($file);
        $this->touch();
    }

    public function removeFile(PostFile $file): void
    {
        if ($this->files->removeElement($file)) {
            $this->touch();
        }
    }

    public function publishAt(\DateTimeImmutable $publishedAt): void
    {
        $this->status = PostStatus::PUBLISHED;
        $this->publishedAt = $publishedAt;
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->status = PostStatus::DRAFT;
        $this->touch();
    }

    public function setVisibility(PostVisibility $visibility): void
    {
        $this->visibility = $visibility;
        $this->touch();
    }

    /**
     * Whether a viewer in this auth state may see the post — used both to
     * gate the public article page and to decide whether a menu hook link
     * pointing at it should render at all.
     */
    public function isVisibleTo(bool $isAuthenticated, bool $isStaff): bool
    {
        return match ($this->visibility) {
            PostVisibility::PUBLIC => true,
            PostVisibility::LOGGED_IN => $isAuthenticated,
            PostVisibility::STAFF_ONLY => $isStaff,
        };
    }

    public function markUpdated(): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = Clock::get()->now();
    }
}
