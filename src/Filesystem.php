<?php

declare(strict_types=1);

namespace MisterIcy\Storage;

use MisterIcy\Storage\Contract\AdapterInterface;
use MisterIcy\Storage\Contract\DirectoryInterface;
use MisterIcy\Storage\Contract\InspectableInterface;
use MisterIcy\Storage\Contract\ListableInterface;
use MisterIcy\Storage\Contract\ReadableInterface;
use MisterIcy\Storage\Contract\StatableInterface;
use MisterIcy\Storage\Contract\TransferableInterface;
use MisterIcy\Storage\Contract\WritableInterface;
use MisterIcy\Storage\Exception\OperationNotSupportedException;
use MisterIcy\Storage\ValueObject\FileMetadata;
use MisterIcy\Storage\ValueObject\Path;

class Filesystem implements
    ReadableInterface,
    WritableInterface,
    InspectableInterface,
    TransferableInterface,
    ListableInterface,
    DirectoryInterface,
    StatableInterface
{
    private AdapterInterface $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    // -------------------------------------------------------------------------
    // ReadableInterface
    // -------------------------------------------------------------------------

    /**
     * @return resource
     */
    public function read(Path $path)
    {
        if (!$this->adapter instanceof ReadableInterface) {
            throw new OperationNotSupportedException('read');
        }

        return $this->adapter->read($path);
    }

    // -------------------------------------------------------------------------
    // WritableInterface
    // -------------------------------------------------------------------------

    /**
     * @param resource $stream
     */
    public function write(Path $path, $stream): void
    {
        if (!$this->adapter instanceof WritableInterface) {
            throw new OperationNotSupportedException('write');
        }

        $this->adapter->write($path, $stream);
    }

    public function delete(Path $path): void
    {
        if (!$this->adapter instanceof WritableInterface) {
            throw new OperationNotSupportedException('delete');
        }

        $this->adapter->delete($path);
    }

    // -------------------------------------------------------------------------
    // InspectableInterface
    // -------------------------------------------------------------------------

    public function exists(Path $path): bool
    {
        if (!$this->adapter instanceof InspectableInterface) {
            throw new OperationNotSupportedException('exists');
        }

        return $this->adapter->exists($path);
    }

    public function isFile(Path $path): bool
    {
        if (!$this->adapter instanceof InspectableInterface) {
            throw new OperationNotSupportedException('isFile');
        }

        return $this->adapter->isFile($path);
    }

    public function isDir(Path $path): bool
    {
        if (!$this->adapter instanceof InspectableInterface) {
            throw new OperationNotSupportedException('isDir');
        }

        return $this->adapter->isDir($path);
    }

    // -------------------------------------------------------------------------
    // TransferableInterface
    // -------------------------------------------------------------------------

    public function copy(Path $source, Path $destination): void
    {
        if (!$this->adapter instanceof TransferableInterface) {
            throw new OperationNotSupportedException('copy');
        }

        $this->adapter->copy($source, $destination);
    }

    public function move(Path $source, Path $destination): void
    {
        if (!$this->adapter instanceof TransferableInterface) {
            throw new OperationNotSupportedException('move');
        }

        $this->adapter->move($source, $destination);
    }

    public function rename(Path $source, Path $destination): void
    {
        if (!$this->adapter instanceof TransferableInterface) {
            throw new OperationNotSupportedException('rename');
        }

        $this->adapter->rename($source, $destination);
    }

    // -------------------------------------------------------------------------
    // ListableInterface
    // -------------------------------------------------------------------------

    /**
     * @return iterable<Path>
     */
    public function listContents(Path $path): iterable
    {
        if (!$this->adapter instanceof ListableInterface) {
            throw new OperationNotSupportedException('listContents');
        }

        return $this->adapter->listContents($path);
    }

    // -------------------------------------------------------------------------
    // DirectoryInterface
    // -------------------------------------------------------------------------

    public function createDirectory(Path $path): void
    {
        if (!$this->adapter instanceof DirectoryInterface) {
            throw new OperationNotSupportedException('createDirectory');
        }

        $this->adapter->createDirectory($path);
    }

    public function deleteDirectory(Path $path): void
    {
        if (!$this->adapter instanceof DirectoryInterface) {
            throw new OperationNotSupportedException('deleteDirectory');
        }

        $this->adapter->deleteDirectory($path);
    }

    // -------------------------------------------------------------------------
    // StatableInterface
    // -------------------------------------------------------------------------

    public function metadata(Path $path): FileMetadata
    {
        if (!$this->adapter instanceof StatableInterface) {
            throw new OperationNotSupportedException('metadata');
        }

        return $this->adapter->metadata($path);
    }
}
