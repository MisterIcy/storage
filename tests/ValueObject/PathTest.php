<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage\ValueObject;

use MisterIcy\Storage\ValueObject\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\ValueObject\Path
 * @covers \MisterIcy\Storage\ValueObject\AbstractPath
 */
class PathTest extends TestCase
{
    public function testAlreadyNormalisedPathIsUnchanged(): void
    {
        $path = new Path('/uploads/avatar.jpg');
        $this->assertSame('/uploads/avatar.jpg', (string) $path);
    }

    public function testDotDotSegmentsAreResolved(): void
    {
        $path = new Path('/uploads/../uploads/avatar.jpg');
        $this->assertSame('/uploads/avatar.jpg', (string) $path);
    }

    public function testDoubleSlashesAreCollapsed(): void
    {
        $path = new Path('/a//b');
        $this->assertSame('/a/b', (string) $path);
    }

    public function testDotSegmentsAreRemoved(): void
    {
        $path = new Path('/a/./b');
        $this->assertSame('/a/b', (string) $path);
    }

    public function testTrailingSlashIsStripped(): void
    {
        $path = new Path('/uploads/');
        $this->assertSame('/uploads', (string) $path);
    }

    public function testRootIsPreserved(): void
    {
        $path = new Path('/');
        $this->assertSame('/', (string) $path);
    }

    public function testBackslashesAreConvertedToForwardSlashes(): void
    {
        $path = new Path('\\uploads\\avatar.jpg');
        $this->assertSame('/uploads/avatar.jpg', (string) $path);
    }

    public function testToStringReturnsNormalisedPath(): void
    {
        $path = new Path('/some/./path/../file.txt');
        $this->assertSame('/some/file.txt', (string) $path);
    }

    public function testEqualsReturnsTrueForSameLogicalPath(): void
    {
        $a = new Path('/uploads/avatar.jpg');
        $b = new Path('/uploads/../uploads/avatar.jpg');
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentPath(): void
    {
        $a = new Path('/uploads/avatar.jpg');
        $b = new Path('/uploads/other.jpg');
        $this->assertFalse($a->equals($b));
    }

    public function testMultipleDotDotSegments(): void
    {
        $path = new Path('/a/b/c/../../d');
        $this->assertSame('/a/d', (string) $path);
    }
}
