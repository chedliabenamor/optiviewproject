<?php

namespace App\Entity;

use App\Repository\ProductModelImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ProductModelImageRepository::class)]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
class ProductModelImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productModelImages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Product $product = null;

    // Vich Uploadable Field for the image file
    #[Vich\UploadableField(mapping: 'product_images', fileNameProperty: 'imageUrl')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)] // Changed to nullable: true as filename might not be set initially
    private ?string $imageUrl = null; // Stores the filename of the image
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $altText = null;
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static // Allow null
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function __toString(): string
    {
        return $this->imageUrl ?? 'Image';
    }
    public function getAltText(): ?string
{
    return $this->altText;
}

public function setAltText(?string $altText): static
{
    $this->altText = $altText;
    return $this;
}

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        // This will be set by setImageFile if a file is uploaded.
        // If no file is uploaded but other fields change, set it here.
        if ($this->updatedAt === null) {
             $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
