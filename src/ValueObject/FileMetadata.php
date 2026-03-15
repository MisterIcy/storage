<?php

declare(strict_types=1);

namespace MisterIcy\Storage\ValueObject;

class FileMetadata
{
    private int $size;
    private int $modifiedAt;
    private ?string $mimeType;
    /** @var array<string, mixed> */
    private array $extras;

    /**
     * @param array<string, mixed> $extras
     */
    public function __construct(
        int $size,
        int $modifiedAt,
        ?string $mimeType = null,
        array $extras = []
    ) {
        $this->size = $size;
        $this->modifiedAt = $modifiedAt;
        $this->mimeType = $mimeType;
        $this->extras = $extras;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getModifiedAt(): int
    {
        return $this->modifiedAt;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtras(): array
    {
        return $this->extras;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getExtra(string $key, $default = null)
    {
        return $this->extras[$key] ?? $default;
    }
}
