<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\PostFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PostFileRepository::class)]
#[ORM\Table(name: 'post_file')]
#[ORM\UniqueConstraint(name: 'uniq_post_file', fields: ['post', 'file'])]
#[ORM\Index(columns: ['post_id', 'position'], name: 'idx_post_file_position')]
class PostFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'files')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Post $post,
        #[ORM\ManyToOne(targetEntity: File::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private File $file,
        #[ORM\Column(type: 'string', length: 20, enumType: PostFileRole::class)]
        private PostFileRole $role = PostFileRole::ATTACHMENT,
        #[ORM\Column(type: 'integer')]
        private int $position = 0,
    ) {
        if ($this->position < 0) {
            throw new \InvalidArgumentException('Attachment position cannot be negative.');
        }
        $this->id = new Ulid();
        $this->post->addFile($this);
    }

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $downloadName = null;

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function getRole(): PostFileRole
    {
        return $this->role;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function getDownloadName(): ?string
    {
        return $this->downloadName;
    }

    public function setAltText(?string $altText): void
    {
        $this->altText = $altText ? trim($altText) : null;
    }

    public function setCaption(?string $caption): void
    {
        $this->caption = $caption ? trim($caption) : null;
    }

    public function setDownloadName(?string $downloadName): void
    {
        $this->downloadName = $downloadName ? trim($downloadName) : null;
    }

    /** @throws \InvalidArgumentException */
    public function setPosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Attachment position cannot be negative.');
        }
        $this->position = $position;
    }

    /** @throws \DomainException */
    public function setRole(PostFileRole $role): void
    {
        if ($role === PostFileRole::COVER) {
            if ($this->post->getCoverAttachment() !== null && $this->post->getCoverAttachment() !== $this) {
                throw new \DomainException('A post can have only one cover attachment.');
            }
        }
        $this->role = $role;
    }
}
