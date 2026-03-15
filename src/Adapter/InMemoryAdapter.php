<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Adapter;

use MisterIcy\Storage\Contract\DirectoryInterface;
use MisterIcy\Storage\Contract\InspectableInterface;
use MisterIcy\Storage\Contract\ListableInterface;
use MisterIcy\Storage\Contract\ReadableInterface;
use MisterIcy\Storage\Contract\StatableInterface;
use MisterIcy\Storage\Contract\TransferableInterface;
use MisterIcy\Storage\Contract\WritableInterface;
use MisterIcy\Storage\Exception\DirectoryNotFoundException;
use MisterIcy\Storage\Exception\FileNotFoundException;
use MisterIcy\Storage\ValueObject\FileMetadata;
use MisterIcy\Storage\ValueObject\Path;

class InMemoryAdapter implements
    ReadableInterface,
    WritableInterface,
    InspectableInterface,
    TransferableInterface,
    ListableInterface,
    DirectoryInterface,
    StatableInterface
{
    /**
     * @var array<string, array{content: string, size: int, modifiedAt: int}>
     */
    private array $files = [];

    /**
     * @var array<string, true>
     */
    private array $directories = [];

    // -------------------------------------------------------------------------
    // ReadableInterface
    // -------------------------------------------------------------------------

    /**
     * @return resource
     */
    public function read(Path $path)
    {
        $key = (string) $path;

        if (!isset($this->files[$key])) {
            throw new FileNotFoundException($path);
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Failed to open in-memory stream.');
        }
        fwrite($stream, $this->files[$key]['content']);
        rewind($stream);

        return $stream;
    }

    // -------------------------------------------------------------------------
    // WritableInterface
    // -------------------------------------------------------------------------

    /**
     * @param resource $stream
     */
    public function write(Path $path, $stream): void
    {
        $content = stream_get_contents($stream);
        if ($content === false) {
            $content = '';
        }

        $this->files[(string) $path] = [
            'content'    => $content,
            'size'       => strlen($content),
            'modifiedAt' => time(),
        ];
    }

    public function delete(Path $path): void
    {
        $key = (string) $path;

        if (!isset($this->files[$key])) {
            throw new FileNotFoundException($path);
        }

        unset($this->files[$key]);
    }

    // -------------------------------------------------------------------------
    // InspectableInterface
    // -------------------------------------------------------------------------

    public function exists(Path $path): bool
    {
        $key = (string) $path;
        return isset($this->files[$key]) || isset($this->directories[$key]);
    }

    public function isFile(Path $path): bool
    {
        return isset($this->files[(string) $path]);
    }

    public function isDir(Path $path): bool
    {
        return isset($this->directories[(string) $path]);
    }

    // -------------------------------------------------------------------------
    // TransferableInterface
    // -------------------------------------------------------------------------

    public function copy(Path $source, Path $destination): void
    {
        $srcKey = (string) $source;

        if (!isset($this->files[$srcKey])) {
            throw new FileNotFoundException($source);
        }

        $this->files[(string) $destination] = [
            'content'    => $this->files[$srcKey]['content'],
            'size'       => $this->files[$srcKey]['size'],
            'modifiedAt' => time(),
        ];
    }

    public function move(Path $source, Path $destination): void
    {
        $srcKey = (string) $source;

        if (!isset($this->files[$srcKey])) {
            throw new FileNotFoundException($source);
        }

        $this->files[(string) $destination] = [
            'content'    => $this->files[$srcKey]['content'],
            'size'       => $this->files[$srcKey]['size'],
            'modifiedAt' => $this->files[$srcKey]['modifiedAt'],
        ];

        unset($this->files[$srcKey]);
    }

    public function rename(Path $source, Path $destination): void
    {
        $this->move($source, $destination);
    }

    // -------------------------------------------------------------------------
    // ListableInterface
    // -------------------------------------------------------------------------

    /**
     * @return \Generator<int, Path>
     */
    public function listContents(Path $path): \Traversable
    {
        $dirKey = (string) $path;

        if (!isset($this->directories[$dirKey])) {
            throw new DirectoryNotFoundException($path);
        }

        foreach (array_keys($this->files) as $fileKey) {
            if ($this->getParent($fileKey) === $dirKey) {
                yield new Path($fileKey);
            }
        }

        foreach (array_keys($this->directories) as $subDirKey) {
            if ($this->getParent($subDirKey) === $dirKey) {
                yield new Path($subDirKey);
            }
        }
    }

    // -------------------------------------------------------------------------
    // DirectoryInterface
    // -------------------------------------------------------------------------

    public function createDirectory(Path $path): void
    {
        // Idempotent — no-op if already exists
        $this->directories[(string) $path] = true;
    }

    public function deleteDirectory(Path $path): void
    {
        $dirKey = (string) $path;

        if (!isset($this->directories[$dirKey])) {
            throw new DirectoryNotFoundException($path);
        }

        $prefix = $dirKey . '/';

        // Remove all files nested under this directory
        foreach (array_keys($this->files) as $fileKey) {
            if (strncmp($fileKey, $prefix, strlen($prefix)) === 0) {
                unset($this->files[$fileKey]);
            }
        }

        // Remove all sub-directories nested under this directory
        foreach (array_keys($this->directories) as $subDirKey) {
            if (strncmp($subDirKey, $prefix, strlen($prefix)) === 0) {
                unset($this->directories[$subDirKey]);
            }
        }

        unset($this->directories[$dirKey]);
    }

    // -------------------------------------------------------------------------
    // StatableInterface
    // -------------------------------------------------------------------------

    public function metadata(Path $path): FileMetadata
    {
        $key = (string) $path;

        if (!isset($this->files[$key])) {
            throw new FileNotFoundException($path);
        }

        return new FileMetadata(
            $this->files[$key]['size'],
            $this->files[$key]['modifiedAt']
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function getParent(string $pathStr): string
    {
        $lastSlash = strrpos($pathStr, '/');

        if ($lastSlash === false || $lastSlash === 0) {
            return '/';
        }

        return substr($pathStr, 0, $lastSlash);
    }
}
