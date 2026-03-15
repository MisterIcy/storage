<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage\Adapter;

use MisterIcy\Storage\Adapter\LocalAdapter;
use MisterIcy\Storage\Exception\AdapterException;
use MisterIcy\Storage\Exception\DirectoryNotFoundException;
use MisterIcy\Storage\Exception\FileNotFoundException;
use MisterIcy\Storage\Exception\PermissionDeniedException;
use MisterIcy\Storage\ValueObject\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\Adapter\LocalAdapter
 * @uses \MisterIcy\Storage\ValueObject\AbstractPath
 * @uses \MisterIcy\Storage\ValueObject\Path
 * @uses \MisterIcy\Storage\ValueObject\FileMetadata
 * @uses \MisterIcy\Storage\Exception\AdapterException
 * @uses \MisterIcy\Storage\Exception\FileNotFoundException
 * @uses \MisterIcy\Storage\Exception\DirectoryNotFoundException
 * @uses \MisterIcy\Storage\Exception\PermissionDeniedException
 */
final class LocalAdapterTest extends TestCase
{
    private string $root;
    private LocalAdapter $adapter;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/local-adapter-test-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        $this->adapter = new LocalAdapter($this->root);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function deleteRecursive(string $path): void
    {
        // Check is_link() before file_exists() so dangling symlinks (whose target
        // has been removed) are still unlinked rather than silently skipped.
        if (is_link($path)) {
            unlink($path);
            return;
        }
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
            return;
        }
        $dh = opendir($path);
        if ($dh === false) {
            return;
        }
        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->deleteRecursive($path . '/' . $entry);
        }
        closedir($dh);
        rmdir($path);
    }

    /**
     * @return resource
     */
    private function makeStream(string $content = 'hello')
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Failed to open temp stream.');
        }
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }

    private function diskContent(string $relPath): string
    {
        $abs = $this->root . $relPath;
        $content = file_get_contents($abs);
        if ($content === false) {
            throw new \RuntimeException("Could not read disk file: {$abs}");
        }
        return $content;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorThrowsOnNonExistentRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LocalAdapter('/absolutely-no-such-directory-' . uniqid('', true));
    }

    public function testConstructorThrowsWhenRootIsFile(): void
    {
        $file = $this->root . '/just-a-file.txt';
        file_put_contents($file, 'x');

        $this->expectException(\InvalidArgumentException::class);
        new LocalAdapter($file);
    }

    public function testConstructorSucceedsWithValidDirectory(): void
    {
        $adapter = new LocalAdapter($this->root);
        self::assertInstanceOf(LocalAdapter::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // Security — path traversal
    // -------------------------------------------------------------------------

    public function testResolveThrowsAdapterExceptionForSymlinkEscapingRoot(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink() not available on this platform.');
        }

        // Create a directory outside root
        $outside = sys_get_temp_dir() . '/outside-root-' . uniqid('', true);
        mkdir($outside, 0777, true);
        $outsideFile = $outside . '/secret.txt';
        file_put_contents($outsideFile, 'secret');

        // Create a symlink inside root that points outside
        $link = $this->root . '/escape-link';

        try {
            symlink($outside, $link);
        } catch (\Throwable $e) {
            $this->deleteRecursive($outside);
            self::markTestSkipped('Could not create symlink: ' . $e->getMessage());
        }

        $this->expectException(AdapterException::class);

        try {
            $this->adapter->read(new Path('/escape-link/secret.txt'));
        } finally {
            $this->deleteRecursive($outside);
        }
    }

    // -------------------------------------------------------------------------
    // ReadableInterface
    // -------------------------------------------------------------------------

    public function testReadReturnsStreamWithCorrectContent(): void
    {
        file_put_contents($this->root . '/hello.txt', 'hello world');
        $stream = $this->adapter->read(new Path('/hello.txt'));

        self::assertSame('hello world', stream_get_contents($stream));
        fclose($stream);
    }

    public function testReadThrowsFileNotFoundExceptionForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->read(new Path('/nonexistent.txt'));
    }

    public function testReadThrowsFileNotFoundExceptionForDirectory(): void
    {
        mkdir($this->root . '/somedir');
        $this->expectException(FileNotFoundException::class);
        $this->adapter->read(new Path('/somedir'));
    }

    // -------------------------------------------------------------------------
    // WritableInterface — write
    // -------------------------------------------------------------------------

    public function testWriteCreatesFileWithCorrectContent(): void
    {
        $this->adapter->write(new Path('/output.txt'), $this->makeStream('disk content'));
        self::assertSame('disk content', $this->diskContent('/output.txt'));
    }

    public function testWriteOverwritesExistingContent(): void
    {
        file_put_contents($this->root . '/existing.txt', 'old');
        $this->adapter->write(new Path('/existing.txt'), $this->makeStream('new'));
        self::assertSame('new', $this->diskContent('/existing.txt'));
    }

    public function testWriteCreatesParentDirectoriesAutomatically(): void
    {
        $this->adapter->write(new Path('/a/b/c/deep.txt'), $this->makeStream('deep'));
        self::assertSame('deep', $this->diskContent('/a/b/c/deep.txt'));
    }

    // -------------------------------------------------------------------------
    // WritableInterface — delete
    // -------------------------------------------------------------------------

    public function testDeleteRemovesFile(): void
    {
        file_put_contents($this->root . '/to-delete.txt', 'bye');
        $this->adapter->delete(new Path('/to-delete.txt'));
        self::assertFileDoesNotExist($this->root . '/to-delete.txt');
    }

    public function testDeleteThrowsFileNotFoundExceptionForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->delete(new Path('/ghost.txt'));
    }

    // -------------------------------------------------------------------------
    // InspectableInterface — exists
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueForExistingFile(): void
    {
        file_put_contents($this->root . '/present.txt', 'x');
        self::assertTrue($this->adapter->exists(new Path('/present.txt')));
    }

    public function testExistsReturnsTrueForExistingDirectory(): void
    {
        mkdir($this->root . '/mydir');
        self::assertTrue($this->adapter->exists(new Path('/mydir')));
    }

    public function testExistsReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->exists(new Path('/does-not-exist.txt')));
    }

    // -------------------------------------------------------------------------
    // InspectableInterface — isFile
    // -------------------------------------------------------------------------

    public function testIsFileReturnsTrueForFile(): void
    {
        file_put_contents($this->root . '/file.txt', 'x');
        self::assertTrue($this->adapter->isFile(new Path('/file.txt')));
    }

    public function testIsFileReturnsFalseForDirectory(): void
    {
        mkdir($this->root . '/adir');
        self::assertFalse($this->adapter->isFile(new Path('/adir')));
    }

    public function testIsFileReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->isFile(new Path('/unknown.txt')));
    }

    // -------------------------------------------------------------------------
    // InspectableInterface — isDir
    // -------------------------------------------------------------------------

    public function testIsDirReturnsTrueForDirectory(): void
    {
        mkdir($this->root . '/thedir');
        self::assertTrue($this->adapter->isDir(new Path('/thedir')));
    }

    public function testIsDirReturnsFalseForFile(): void
    {
        file_put_contents($this->root . '/file.txt', 'x');
        self::assertFalse($this->adapter->isDir(new Path('/file.txt')));
    }

    public function testIsDirReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->isDir(new Path('/unknown')));
    }

    // -------------------------------------------------------------------------
    // TransferableInterface — copy
    // -------------------------------------------------------------------------

    public function testCopyMakesContentAvailableAtDestination(): void
    {
        file_put_contents($this->root . '/src.txt', 'copy me');
        $this->adapter->copy(new Path('/src.txt'), new Path('/dst.txt'));
        self::assertSame('copy me', $this->diskContent('/dst.txt'));
    }

    public function testCopyKeepsSourceIntact(): void
    {
        file_put_contents($this->root . '/src.txt', 'original');
        $this->adapter->copy(new Path('/src.txt'), new Path('/dst.txt'));
        self::assertSame('original', $this->diskContent('/src.txt'));
    }

    public function testCopyCreatesParentDirectoriesForDestination(): void
    {
        file_put_contents($this->root . '/src.txt', 'data');
        $this->adapter->copy(new Path('/src.txt'), new Path('/subdir/dst.txt'));
        self::assertSame('data', $this->diskContent('/subdir/dst.txt'));
    }

    public function testCopyThrowsFileNotFoundExceptionForMissingSource(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->copy(new Path('/missing.txt'), new Path('/dst.txt'));
    }

    // -------------------------------------------------------------------------
    // TransferableInterface — move
    // -------------------------------------------------------------------------

    public function testMoveMovesContentToDestination(): void
    {
        file_put_contents($this->root . '/src.txt', 'move me');
        $this->adapter->move(new Path('/src.txt'), new Path('/dst.txt'));
        self::assertSame('move me', $this->diskContent('/dst.txt'));
    }

    public function testMoveRemovesSource(): void
    {
        file_put_contents($this->root . '/src.txt', 'gone');
        $this->adapter->move(new Path('/src.txt'), new Path('/dst.txt'));
        self::assertFileDoesNotExist($this->root . '/src.txt');
    }

    public function testMoveThrowsFileNotFoundExceptionForMissingSource(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->move(new Path('/missing.txt'), new Path('/dst.txt'));
    }

    // -------------------------------------------------------------------------
    // TransferableInterface — rename (alias of move)
    // -------------------------------------------------------------------------

    public function testRenameBehavesLikeMove(): void
    {
        file_put_contents($this->root . '/old.txt', 'content');
        $this->adapter->rename(new Path('/old.txt'), new Path('/new.txt'));
        self::assertTrue(is_file($this->root . '/new.txt'));
        self::assertFalse(is_file($this->root . '/old.txt'));
    }

    // -------------------------------------------------------------------------
    // ListableInterface
    // -------------------------------------------------------------------------

    public function testListContentsReturnsImmediateChildrenOnly(): void
    {
        file_put_contents($this->root . '/a.txt', 'a');
        file_put_contents($this->root . '/b.txt', 'b');
        mkdir($this->root . '/sub');
        file_put_contents($this->root . '/sub/deep.txt', 'deep');

        $entries = iterator_to_array($this->adapter->listContents(new Path('/')), false);
        $strings = array_map('strval', $entries);

        self::assertContains('/a.txt', $strings);
        self::assertContains('/b.txt', $strings);
        self::assertContains('/sub', $strings);
        // deep.txt is NOT a direct child of /
        self::assertNotContains('/sub/deep.txt', $strings);
    }

    public function testListContentsIncludesSubdirectories(): void
    {
        mkdir($this->root . '/subdir');
        file_put_contents($this->root . '/file.txt', 'x');

        $entries = iterator_to_array($this->adapter->listContents(new Path('/')), false);
        $strings = array_map('strval', $entries);

        self::assertContains('/subdir', $strings);
        self::assertContains('/file.txt', $strings);
    }

    public function testListContentsThrowsDirectoryNotFoundExceptionForMissingDir(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        iterator_to_array($this->adapter->listContents(new Path('/no-such-dir')));
    }

    public function testListContentsOnNestedDirectory(): void
    {
        mkdir($this->root . '/parent');
        file_put_contents($this->root . '/parent/child.txt', 'child');

        $entries = iterator_to_array($this->adapter->listContents(new Path('/parent')), false);
        $strings = array_map('strval', $entries);

        self::assertContains('/parent/child.txt', $strings);
        self::assertCount(1, $strings);
    }

    // -------------------------------------------------------------------------
    // DirectoryInterface — createDirectory
    // -------------------------------------------------------------------------

    public function testCreateDirectoryCreatesDirectory(): void
    {
        $this->adapter->createDirectory(new Path('/newdir'));
        self::assertDirectoryExists($this->root . '/newdir');
    }

    public function testCreateDirectoryCreatesIntermediateDirectories(): void
    {
        $this->adapter->createDirectory(new Path('/a/b/c'));
        self::assertDirectoryExists($this->root . '/a/b/c');
    }

    public function testCreateDirectoryIsIdempotent(): void
    {
        $this->adapter->createDirectory(new Path('/idem'));
        $this->adapter->createDirectory(new Path('/idem')); // must not throw
        self::assertDirectoryExists($this->root . '/idem');
    }

    // -------------------------------------------------------------------------
    // DirectoryInterface — deleteDirectory
    // -------------------------------------------------------------------------

    public function testDeleteDirectoryRemovesDirectory(): void
    {
        mkdir($this->root . '/todelete');
        $this->adapter->deleteDirectory(new Path('/todelete'));
        self::assertDirectoryDoesNotExist($this->root . '/todelete');
    }

    public function testDeleteDirectoryRemovesNestedFilesAndSubdirs(): void
    {
        mkdir($this->root . '/data', 0777, true);
        mkdir($this->root . '/data/sub', 0777, true);
        file_put_contents($this->root . '/data/file.txt', 'x');
        file_put_contents($this->root . '/data/sub/nested.txt', 'y');

        $this->adapter->deleteDirectory(new Path('/data'));

        self::assertDirectoryDoesNotExist($this->root . '/data');
        self::assertFileDoesNotExist($this->root . '/data/file.txt');
        self::assertFileDoesNotExist($this->root . '/data/sub/nested.txt');
    }

    public function testDeleteDirectoryThrowsDirectoryNotFoundExceptionForMissingDir(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        $this->adapter->deleteDirectory(new Path('/no-such-dir'));
    }

    // -------------------------------------------------------------------------
    // StatableInterface
    // -------------------------------------------------------------------------

    public function testMetadataReturnsCorrectSize(): void
    {
        $content = 'size-test-content';
        file_put_contents($this->root . '/size.txt', $content);

        $meta = $this->adapter->metadata(new Path('/size.txt'));
        self::assertSame(strlen($content), $meta->getSize());
    }

    public function testMetadataReturnsModifiedAtWithinReasonableRange(): void
    {
        $before = time() - 2;
        file_put_contents($this->root . '/mtime.txt', 'x');
        $after = time() + 2;

        $meta = $this->adapter->metadata(new Path('/mtime.txt'));
        self::assertGreaterThanOrEqual($before, $meta->getModifiedAt());
        self::assertLessThanOrEqual($after, $meta->getModifiedAt());
    }

    public function testMetadataReturnsMimeType(): void
    {
        file_put_contents($this->root . '/text.txt', 'hello');

        $meta = $this->adapter->metadata(new Path('/text.txt'));
        self::assertNotNull($meta->getMimeType());
        self::assertStringContainsString('text/', (string) $meta->getMimeType());
    }

    public function testMetadataThrowsFileNotFoundExceptionForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->metadata(new Path('/missing.txt'));
    }

    // -------------------------------------------------------------------------
    // Permission denied — write to read-only directory
    // -------------------------------------------------------------------------

    public function testWriteThrowsPermissionDeniedExceptionOnReadOnlyParent(): void
    {
        if (posix_getuid() === 0) {
            self::markTestSkipped('Running as root; permission checks do not apply.');
        }

        $readonly = $this->root . '/readonly';
        mkdir($readonly, 0555);

        $this->expectException(PermissionDeniedException::class);

        try {
            $this->adapter->write(new Path('/readonly/file.txt'), $this->makeStream('x'));
        } finally {
            chmod($readonly, 0777);
        }
    }
}
