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
use MisterIcy\Storage\Exception\AdapterException;
use MisterIcy\Storage\Exception\DirectoryNotFoundException;
use MisterIcy\Storage\Exception\FileNotFoundException;
use MisterIcy\Storage\Exception\PermissionDeniedException;
use MisterIcy\Storage\ValueObject\FileMetadata;
use MisterIcy\Storage\ValueObject\Path;

class LocalAdapter implements
    ReadableInterface,
    WritableInterface,
    InspectableInterface,
    TransferableInterface,
    ListableInterface,
    DirectoryInterface,
    StatableInterface
{
    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);

        if ($real === false) {
            throw new \InvalidArgumentException(
                "Storage root does not exist or is not accessible: {$root}"
            );
        }

        if (!is_dir($real)) {
            throw new \InvalidArgumentException(
                "Storage root is not a directory: {$root}"
            );
        }

        $this->root = rtrim($real, '/');
    }

    // -------------------------------------------------------------------------
    // ReadableInterface
    // -------------------------------------------------------------------------

    /**
     * @return resource
     */
    public function read(Path $path)
    {
        $abs = $this->resolve($path);

        if (!is_file($abs)) {
            throw new FileNotFoundException($path);
        }

        $stream = @fopen($abs, 'r');

        if ($stream === false) {
            throw new AdapterException(
                "Failed to open file for reading: {$abs}",
                $this->lastErrorException()
            );
        }

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
        $abs = $this->resolve($path);
        $parent = dirname($abs);

        if (!is_dir($parent)) {
            if (!@mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new AdapterException(
                    "Failed to create parent directory: {$parent}",
                    $this->lastErrorException()
                );
            }
        }

        if (is_dir($parent) && !is_writable($parent) && !is_writable($abs)) {
            throw new PermissionDeniedException($path);
        }

        $dest = @fopen($abs, 'w');

        if ($dest === false) {
            $error = error_get_last();
            if ($error !== null && stripos($error['message'], 'permission') !== false) {
                throw new PermissionDeniedException($path, $this->lastErrorException());
            }
            throw new AdapterException(
                "Failed to open file for writing: {$abs}",
                $this->lastErrorException()
            );
        }

        @stream_copy_to_stream($stream, $dest);
        fclose($dest);
    }

    public function delete(Path $path): void
    {
        $abs = $this->resolve($path);

        if (!is_file($abs)) {
            throw new FileNotFoundException($path);
        }

        if (!@unlink($abs)) {
            throw new AdapterException(
                "Failed to delete file: {$abs}",
                $this->lastErrorException()
            );
        }
    }

    // -------------------------------------------------------------------------
    // InspectableInterface
    // -------------------------------------------------------------------------

    public function exists(Path $path): bool
    {
        return file_exists($this->resolve($path));
    }

    public function isFile(Path $path): bool
    {
        return is_file($this->resolve($path));
    }

    public function isDir(Path $path): bool
    {
        return is_dir($this->resolve($path));
    }

    // -------------------------------------------------------------------------
    // TransferableInterface
    // -------------------------------------------------------------------------

    public function copy(Path $source, Path $destination): void
    {
        $srcAbs = $this->resolve($source);

        if (!is_file($srcAbs)) {
            throw new FileNotFoundException($source);
        }

        $destAbs = $this->resolve($destination);
        $destParent = dirname($destAbs);

        if (!is_dir($destParent)) {
            if (!@mkdir($destParent, 0777, true) && !is_dir($destParent)) {
                throw new AdapterException(
                    "Failed to create parent directory for copy destination: {$destParent}",
                    $this->lastErrorException()
                );
            }
        }

        if (!@copy($srcAbs, $destAbs)) {
            throw new AdapterException(
                "Failed to copy {$srcAbs} to {$destAbs}",
                $this->lastErrorException()
            );
        }
    }

    public function move(Path $source, Path $destination): void
    {
        $srcAbs = $this->resolve($source);

        if (!is_file($srcAbs)) {
            throw new FileNotFoundException($source);
        }

        $destAbs = $this->resolve($destination);

        if (!@rename($srcAbs, $destAbs)) {
            throw new AdapterException(
                "Failed to move {$srcAbs} to {$destAbs}",
                $this->lastErrorException()
            );
        }
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
        $abs = $this->resolve($path);

        if (!is_dir($abs)) {
            throw new DirectoryNotFoundException($path);
        }

        $dh = @opendir($abs);

        if ($dh === false) {
            throw new AdapterException(
                "Failed to open directory: {$abs}",
                $this->lastErrorException()
            );
        }

        $dirStr = (string) $path;
        // Normalise: root path is '/', others have no trailing slash
        $prefix = $dirStr === '/' ? '/' : $dirStr . '/';

        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            yield new Path($prefix . $entry);
        }

        closedir($dh);
    }

    // -------------------------------------------------------------------------
    // DirectoryInterface
    // -------------------------------------------------------------------------

    public function createDirectory(Path $path): void
    {
        $abs = $this->resolve($path);

        if (is_dir($abs)) {
            return; // idempotent
        }

        if (!@mkdir($abs, 0777, true) && !is_dir($abs)) {
            throw new AdapterException(
                "Failed to create directory: {$abs}",
                $this->lastErrorException()
            );
        }
    }

    public function deleteDirectory(Path $path): void
    {
        $abs = $this->resolve($path);

        if (!is_dir($abs)) {
            throw new DirectoryNotFoundException($path);
        }

        $this->deleteRecursive($abs);
    }

    // -------------------------------------------------------------------------
    // StatableInterface
    // -------------------------------------------------------------------------

    public function metadata(Path $path): FileMetadata
    {
        $abs = $this->resolve($path);

        if (!is_file($abs)) {
            throw new FileNotFoundException($path);
        }

        $stat = @stat($abs);

        if ($stat === false) {
            throw new AdapterException(
                "Failed to stat file: {$abs}",
                $this->lastErrorException()
            );
        }

        $size = (int) $stat['size'];
        $modifiedAt = (int) $stat['mtime'];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeResult = $finfo->file($abs);
        $mimeType = $mimeResult !== false ? $mimeResult : null;

        return new FileMetadata($size, $modifiedAt, $mimeType);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a virtual Path to an absolute OS path within $this->root.
     *
     * For existing paths  : uses realpath() to follow symlinks, then verifies
     *                        the resolved path is still within $this->root.
     * For non-existent paths: uses the candidate string (Path VO pre-normalises
     *                          ".." segments) and verifies the candidate starts
     *                          with $this->root.
     *
     * @throws AdapterException When the resolved path escapes the storage root.
     */
    private function resolve(Path $path): string
    {
        // Path VO always produces a leading '/', e.g. '/subdir/file.txt'
        $candidate = $this->root . (string) $path;

        $real = realpath($candidate);

        if ($real !== false) {
            // Path exists on disk — use OS-resolved path (follows symlinks).
            if (strncmp($real, $this->root, strlen($this->root)) !== 0) {
                throw new AdapterException('Path escapes storage root.');
            }

            // Ensure the resolved path is either the root itself or a child of it.
            // Guard against partial directory name prefix matches (e.g. /stor vs /storage).
            $afterRoot = substr($real, strlen($this->root));
            if ($afterRoot !== '' && $afterRoot[0] !== '/') {
                throw new AdapterException('Path escapes storage root.');
            }

            return $real;
        }

        // Path does not yet exist (write / create operations).
        // Path VO has already normalised ".." away, so check the string prefix.
        if (strncmp($candidate, $this->root, strlen($this->root)) !== 0) {
            throw new AdapterException('Path escapes storage root.');
        }

        $afterRoot = substr($candidate, strlen($this->root));
        if ($afterRoot !== '' && $afterRoot[0] !== '/') {
            throw new AdapterException('Path escapes storage root.');
        }

        return $candidate;
    }

    /**
     * Recursively delete all files and sub-directories under $abs, then remove $abs itself.
     * Uses only native PHP filesystem functions — no shell_exec.
     */
    private function deleteRecursive(string $abs): void
    {
        $dh = @opendir($abs);

        if ($dh === false) {
            throw new AdapterException(
                "Failed to open directory for deletion: {$abs}",
                $this->lastErrorException()
            );
        }

        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $abs . '/' . $entry;

            if (is_dir($child)) {
                $this->deleteRecursive($child);
            } else {
                if (!@unlink($child)) {
                    closedir($dh);
                    throw new AdapterException(
                        "Failed to delete file during directory removal: {$child}",
                        $this->lastErrorException()
                    );
                }
            }
        }

        closedir($dh);

        if (!@rmdir($abs)) {
            throw new AdapterException(
                "Failed to remove directory: {$abs}",
                $this->lastErrorException()
            );
        }
    }

    /**
     * Capture the last PHP error as a \RuntimeException for use as $previous.
     */
    private function lastErrorException(): ?\RuntimeException
    {
        $error = error_get_last();

        if ($error === null) {
            return null;
        }

        return new \RuntimeException($error['message']);
    }
}
