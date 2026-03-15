<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage\ValueObject;

use MisterIcy\Storage\ValueObject\FileMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\ValueObject\FileMetadata
 */
class FileMetadataTest extends TestCase
{
    public function testGetSizeReturnsConstructedValue(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertSame(1024, $meta->getSize());
    }

    public function testGetModifiedAtReturnsConstructedValue(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertSame(1700000000, $meta->getModifiedAt());
    }

    public function testGetMimeTypeReturnsNullWhenNotProvided(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertNull($meta->getMimeType());
    }

    public function testGetMimeTypeReturnsValueWhenProvided(): void
    {
        $meta = new FileMetadata(1024, 1700000000, 'image/jpeg');
        $this->assertSame('image/jpeg', $meta->getMimeType());
    }

    public function testGetExtrasReturnsEmptyArrayByDefault(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertSame([], $meta->getExtras());
    }

    public function testGetExtrasReturnsConstructedValue(): void
    {
        $extras = ['etag' => 'abc123', 'storage-class' => 'STANDARD'];
        $meta = new FileMetadata(1024, 1700000000, null, $extras);
        $this->assertSame($extras, $meta->getExtras());
    }

    public function testGetExtraReturnsValueForExistingKey(): void
    {
        $meta = new FileMetadata(1024, 1700000000, null, ['etag' => 'abc123']);
        $this->assertSame('abc123', $meta->getExtra('etag'));
    }

    public function testGetExtraReturnsNullForMissingKeyByDefault(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertNull($meta->getExtra('nonexistent'));
    }

    public function testGetExtraReturnsCustomDefaultForMissingKey(): void
    {
        $meta = new FileMetadata(1024, 1700000000);
        $this->assertSame('fallback', $meta->getExtra('nonexistent', 'fallback'));
    }

    public function testIsImmutableHasNoSetters(): void
    {
        $meta = new FileMetadata(512, 1600000000, 'text/plain', ['key' => 'val']);

        // Verify only getters exist (no public set* methods)
        $methods = get_class_methods($meta);
        foreach ($methods as $method) {
            $this->assertStringStartsNotWith('set', $method, "Unexpected setter: {$method}");
        }
    }
}
