<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage\Adapter;

use MisterIcy\Storage\Adapter\InMemoryAdapter;
use MisterIcy\Storage\Exception\DirectoryNotFoundException;
use MisterIcy\Storage\Exception\FileNotFoundException;
use MisterIcy\Storage\ValueObject\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\Adapter\InMemoryAdapter
 * @uses \MisterIcy\Storage\ValueObject\AbstractPath
 * @uses \MisterIcy\Storage\ValueObject\Path
 * @uses \MisterIcy\Storage\ValueObject\FileMetadata
 * @uses \MisterIcy\Storage\Exception\FileNotFoundException
 * @uses \MisterIcy\Storage\Exception\DirectoryNotFoundException
 */
final class InMemoryAdapterTest extends TestCase
{
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStream(string $content = 'hello')
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }

    // -------------------------------------------------------------------------
    // Write + Read
    // -------------------------------------------------------------------------

    public function testWriteThenReadRoundtrip(): void
    {
        $path = new Path('/files/greeting.txt');
        $this->adapter->write($path, $this->makeStream('hello world'));

        $resource = $this->adapter->read($path);
        self::assertIsResource($resource);
        self::assertSame('hello world', stream_get_contents($resource));
    }

    public function testWriteOverwritesExistingContent(): void
    {
        $path = new Path('/files/greeting.txt');
        $this->adapter->write($path, $this->makeStream('first'));
        $this->adapter->write($path, $this->makeStream('second'));

        $resource = $this->adapter->read($path);
        self::assertSame('second', stream_get_contents($resource));
    }

    public function testReadOnMissingFileThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->read(new Path('/nonexistent.txt'));
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteThenReadThrowsFileNotFoundException(): void
    {
        $path = new Path('/files/temp.txt');
        $this->adapter->write($path, $this->makeStream());
        $this->adapter->delete($path);

        $this->expectException(FileNotFoundException::class);
        $this->adapter->read($path);
    }

    public function testDeleteNonExistentFileThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->delete(new Path('/ghost.txt'));
    }

    // -------------------------------------------------------------------------
    // Inspect (exists / isFile / isDir)
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueForWrittenFile(): void
    {
        $path = new Path('/files/exists.txt');
        $this->adapter->write($path, $this->makeStream());
        self::assertTrue($this->adapter->exists($path));
    }

    public function testExistsReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->exists(new Path('/unknown.txt')));
    }

    public function testIsFileReturnsTrueForWrittenFile(): void
    {
        $path = new Path('/files/file.txt');
        $this->adapter->write($path, $this->makeStream());
        self::assertTrue($this->adapter->isFile($path));
    }

    public function testIsFileReturnsFalseForDirectory(): void
    {
        $dir = new Path('/mydir');
        $this->adapter->createDirectory($dir);
        self::assertFalse($this->adapter->isFile($dir));
    }

    public function testIsFileReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->isFile(new Path('/unknown.txt')));
    }

    public function testIsDirReturnsTrueAfterCreateDirectory(): void
    {
        $dir = new Path('/mydir');
        $this->adapter->createDirectory($dir);
        self::assertTrue($this->adapter->isDir($dir));
    }

    public function testIsDirReturnsFalseForFile(): void
    {
        $path = new Path('/files/file.txt');
        $this->adapter->write($path, $this->makeStream());
        self::assertFalse($this->adapter->isDir($path));
    }

    public function testIsDirReturnsFalseForUnknownPath(): void
    {
        self::assertFalse($this->adapter->isDir(new Path('/unknown')));
    }

    // -------------------------------------------------------------------------
    // Copy
    // -------------------------------------------------------------------------

    public function testCopyMakesContentAvailableAtDestination(): void
    {
        $src = new Path('/src.txt');
        $dst = new Path('/dst.txt');
        $this->adapter->write($src, $this->makeStream('copy me'));
        $this->adapter->copy($src, $dst);

        self::assertSame('copy me', stream_get_contents($this->adapter->read($dst)));
    }

    public function testCopyKeepsSourceIntact(): void
    {
        $src = new Path('/src.txt');
        $dst = new Path('/dst.txt');
        $this->adapter->write($src, $this->makeStream('original'));
        $this->adapter->copy($src, $dst);

        self::assertSame('original', stream_get_contents($this->adapter->read($src)));
    }

    public function testCopyFromMissingSourceThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->copy(new Path('/missing.txt'), new Path('/dst.txt'));
    }

    // -------------------------------------------------------------------------
    // Move
    // -------------------------------------------------------------------------

    public function testMoveMakesContentAvailableAtDestination(): void
    {
        $src = new Path('/src.txt');
        $dst = new Path('/dst.txt');
        $this->adapter->write($src, $this->makeStream('move me'));
        $this->adapter->move($src, $dst);

        self::assertSame('move me', stream_get_contents($this->adapter->read($dst)));
    }

    public function testMoveRemovesSource(): void
    {
        $src = new Path('/src.txt');
        $dst = new Path('/dst.txt');
        $this->adapter->write($src, $this->makeStream('gone'));
        $this->adapter->move($src, $dst);

        self::assertFalse($this->adapter->exists($src));
    }

    public function testMoveFromMissingSourceThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->move(new Path('/missing.txt'), new Path('/dst.txt'));
    }

    // -------------------------------------------------------------------------
    // Rename (alias of move)
    // -------------------------------------------------------------------------

    public function testRenameBehavesLikeMove(): void
    {
        $src = new Path('/old-name.txt');
        $dst = new Path('/new-name.txt');
        $this->adapter->write($src, $this->makeStream('content'));
        $this->adapter->rename($src, $dst);

        self::assertTrue($this->adapter->exists($dst));
        self::assertFalse($this->adapter->exists($src));
    }

    // -------------------------------------------------------------------------
    // listContents
    // -------------------------------------------------------------------------

    public function testListContentsReturnsImmediateChildrenOnly(): void
    {
        $dir = new Path('/root');
        $this->adapter->createDirectory($dir);
        $this->adapter->write(new Path('/root/a.txt'), $this->makeStream());
        $this->adapter->write(new Path('/root/b.txt'), $this->makeStream());
        $this->adapter->write(new Path('/root/sub/deep.txt'), $this->makeStream());

        $entries = iterator_to_array($this->adapter->listContents($dir), false);
        $strings = array_map('strval', $entries);

        self::assertContains('/root/a.txt', $strings);
        self::assertContains('/root/b.txt', $strings);
        self::assertCount(2, $strings); // deep.txt is not immediate child
    }

    public function testListContentsIncludesSubdirectories(): void
    {
        $dir = new Path('/root');
        $this->adapter->createDirectory($dir);
        $sub = new Path('/root/sub');
        $this->adapter->createDirectory($sub);
        $this->adapter->write(new Path('/root/file.txt'), $this->makeStream());

        $entries = iterator_to_array($this->adapter->listContents($dir), false);
        $strings = array_map('strval', $entries);

        self::assertContains('/root/sub', $strings);
        self::assertContains('/root/file.txt', $strings);
        self::assertCount(2, $strings);
    }

    public function testListContentsOnMissingDirectoryThrowsDirectoryNotFoundException(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        iterator_to_array($this->adapter->listContents(new Path('/missing')));
    }

    // -------------------------------------------------------------------------
    // createDirectory
    // -------------------------------------------------------------------------

    public function testCreateDirectoryIsIdempotent(): void
    {
        $dir = new Path('/idempotent');
        $this->adapter->createDirectory($dir);
        $this->adapter->createDirectory($dir); // must not throw
        self::assertTrue($this->adapter->isDir($dir));
    }

    // -------------------------------------------------------------------------
    // deleteDirectory
    // -------------------------------------------------------------------------

    public function testDeleteDirectoryRemovesDirectoryAndNestedFiles(): void
    {
        $dir = new Path('/data');
        $this->adapter->createDirectory($dir);
        $this->adapter->write(new Path('/data/file.txt'), $this->makeStream());
        $this->adapter->write(new Path('/data/sub/nested.txt'), $this->makeStream());

        $this->adapter->deleteDirectory($dir);

        self::assertFalse($this->adapter->exists($dir));
        self::assertFalse($this->adapter->exists(new Path('/data/file.txt')));
        self::assertFalse($this->adapter->exists(new Path('/data/sub/nested.txt')));
    }

    public function testDeleteDirectoryOnMissingDirectoryThrowsDirectoryNotFoundException(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        $this->adapter->deleteDirectory(new Path('/nothing'));
    }

    // -------------------------------------------------------------------------
    // metadata
    // -------------------------------------------------------------------------

    public function testMetadataReturnsCorrectSize(): void
    {
        $path = new Path('/sized.txt');
        $content = 'hello world'; // 11 bytes
        $this->adapter->write($path, $this->makeStream($content));

        $meta = $this->adapter->metadata($path);
        self::assertSame(11, $meta->getSize());
    }

    public function testMetadataReturnsModifiedAtTimestamp(): void
    {
        $before = time();
        $path = new Path('/timestamped.txt');
        $this->adapter->write($path, $this->makeStream('data'));
        $after = time();

        $meta = $this->adapter->metadata($path);
        self::assertGreaterThanOrEqual($before, $meta->getModifiedAt());
        self::assertLessThanOrEqual($after, $meta->getModifiedAt());
    }

    public function testMetadataMimeTypeIsNull(): void
    {
        $path = new Path('/mime.txt');
        $this->adapter->write($path, $this->makeStream());

        $meta = $this->adapter->metadata($path);
        self::assertNull($meta->getMimeType());
    }

    public function testMetadataOnMissingFileThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->adapter->metadata(new Path('/nonexistent.txt'));
    }
}
