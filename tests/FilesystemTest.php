<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage;

use MisterIcy\Storage\Adapter\InMemoryAdapter;
use MisterIcy\Storage\Contract\AdapterInterface;
use MisterIcy\Storage\Exception\OperationNotSupportedException;
use MisterIcy\Storage\Filesystem;
use MisterIcy\Storage\ValueObject\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\Filesystem
 * @uses \MisterIcy\Storage\Adapter\InMemoryAdapter
 * @uses \MisterIcy\Storage\ValueObject\AbstractPath
 * @uses \MisterIcy\Storage\ValueObject\Path
 * @uses \MisterIcy\Storage\ValueObject\FileMetadata
 * @uses \MisterIcy\Storage\Exception\OperationNotSupportedException
 */
final class FilesystemTest extends TestCase
{
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem(new InMemoryAdapter());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStream(string $content = 'data')
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, $content);
        rewind($h);
        return $h;
    }

    // -------------------------------------------------------------------------
    // Smoke tests via the facade
    // -------------------------------------------------------------------------

    public function testWriteAndReadRoundtrip(): void
    {
        $path = new Path('/test.txt');
        $this->fs->write($path, $this->makeStream('facade content'));

        $resource = $this->fs->read($path);
        self::assertSame('facade content', stream_get_contents($resource));
    }

    public function testExistsAfterWrite(): void
    {
        $path = new Path('/exists.txt');
        $this->fs->write($path, $this->makeStream());
        self::assertTrue($this->fs->exists($path));
    }

    public function testIsFileAfterWrite(): void
    {
        $path = new Path('/file.txt');
        $this->fs->write($path, $this->makeStream());
        self::assertTrue($this->fs->isFile($path));
    }

    public function testIsDirAfterCreateDirectory(): void
    {
        $dir = new Path('/mydir');
        $this->fs->createDirectory($dir);
        self::assertTrue($this->fs->isDir($dir));
    }

    public function testDeleteRemovesFile(): void
    {
        $path = new Path('/todelete.txt');
        $this->fs->write($path, $this->makeStream());
        $this->fs->delete($path);
        self::assertFalse($this->fs->exists($path));
    }

    public function testCopyVisFacade(): void
    {
        $src = new Path('/src.txt');
        $dst = new Path('/dst.txt');
        $this->fs->write($src, $this->makeStream('copied'));
        $this->fs->copy($src, $dst);

        self::assertSame('copied', stream_get_contents($this->fs->read($dst)));
        self::assertTrue($this->fs->exists($src));
    }

    public function testMoveViaFacade(): void
    {
        $src = new Path('/movesrc.txt');
        $dst = new Path('/movedst.txt');
        $this->fs->write($src, $this->makeStream('moved'));
        $this->fs->move($src, $dst);

        self::assertTrue($this->fs->exists($dst));
        self::assertFalse($this->fs->exists($src));
    }

    public function testRenameViaFacade(): void
    {
        $src = new Path('/old.txt');
        $dst = new Path('/new.txt');
        $this->fs->write($src, $this->makeStream());
        $this->fs->rename($src, $dst);

        self::assertTrue($this->fs->exists($dst));
        self::assertFalse($this->fs->exists($src));
    }

    public function testListContentsViaFacade(): void
    {
        $dir = new Path('/listed');
        $this->fs->createDirectory($dir);
        $this->fs->write(new Path('/listed/a.txt'), $this->makeStream());

        $entries = iterator_to_array($this->fs->listContents($dir), false);
        self::assertCount(1, $entries);
        self::assertSame('/listed/a.txt', (string) $entries[0]);
    }

    public function testDeleteDirectoryViaFacade(): void
    {
        $dir = new Path('/ddir');
        $this->fs->createDirectory($dir);
        $this->fs->write(new Path('/ddir/file.txt'), $this->makeStream());
        $this->fs->deleteDirectory($dir);

        self::assertFalse($this->fs->exists($dir));
    }

    public function testMetadataViaFacade(): void
    {
        $path = new Path('/meta.txt');
        $this->fs->write($path, $this->makeStream('hello'));

        $meta = $this->fs->metadata($path);
        self::assertSame(5, $meta->getSize());
        self::assertNull($meta->getMimeType());
    }

    // -------------------------------------------------------------------------
    // OperationNotSupportedException for stub adapter
    // -------------------------------------------------------------------------

    private function stubAdapter(): AdapterInterface
    {
        // Anonymous class that only satisfies the AdapterInterface marker —
        // it supports none of the capability sub-interfaces.
        return new class implements AdapterInterface {};
    }

    public function testReadThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->read(new Path('/a.txt'));
    }

    public function testWriteThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->write(new Path('/a.txt'), $this->makeStream());
    }

    public function testDeleteThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->delete(new Path('/a.txt'));
    }

    public function testExistsThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->exists(new Path('/a.txt'));
    }

    public function testIsFileThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->isFile(new Path('/a.txt'));
    }

    public function testIsDirThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->isDir(new Path('/a.txt'));
    }

    public function testCopyThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->copy(new Path('/a.txt'), new Path('/b.txt'));
    }

    public function testMoveThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->move(new Path('/a.txt'), new Path('/b.txt'));
    }

    public function testRenameThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->rename(new Path('/a.txt'), new Path('/b.txt'));
    }

    public function testListContentsThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        iterator_to_array((new Filesystem($this->stubAdapter()))->listContents(new Path('/')));
    }

    public function testCreateDirectoryThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->createDirectory(new Path('/dir'));
    }

    public function testDeleteDirectoryThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->deleteDirectory(new Path('/dir'));
    }

    public function testMetadataThrowsOnUnsupportedAdapter(): void
    {
        $this->expectException(OperationNotSupportedException::class);
        (new Filesystem($this->stubAdapter()))->metadata(new Path('/a.txt'));
    }
}
