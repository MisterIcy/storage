<?php

declare(strict_types=1);

namespace Tests\MisterIcy\Storage\Exception;

use MisterIcy\Storage\Exception\AdapterException;
use MisterIcy\Storage\Exception\DirectoryNotFoundException;
use MisterIcy\Storage\Exception\FileAlreadyExistsException;
use MisterIcy\Storage\Exception\FileNotFoundException;
use MisterIcy\Storage\Exception\OperationNotSupportedException;
use MisterIcy\Storage\Exception\PermissionDeniedException;
use MisterIcy\Storage\Exception\StorageException;
use MisterIcy\Storage\ValueObject\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MisterIcy\Storage\Exception\StorageException
 * @covers \MisterIcy\Storage\Exception\FileNotFoundException
 * @covers \MisterIcy\Storage\Exception\DirectoryNotFoundException
 * @covers \MisterIcy\Storage\Exception\FileAlreadyExistsException
 * @covers \MisterIcy\Storage\Exception\PermissionDeniedException
 * @covers \MisterIcy\Storage\Exception\OperationNotSupportedException
 * @covers \MisterIcy\Storage\Exception\AdapterException
 * @uses \MisterIcy\Storage\ValueObject\AbstractPath
 * @uses \MisterIcy\Storage\ValueObject\Path
 */
final class ExceptionHierarchyTest extends TestCase
{
    // ── StorageException ─────────────────────────────────────────────────────

    public function testStorageExceptionExtendsRuntimeException(): void
    {
        $e = new StorageException('error');
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testStorageExceptionMessageIsPreserved(): void
    {
        $e = new StorageException('storage error');
        self::assertSame('storage error', $e->getMessage());
    }

    public function testStorageExceptionCanBeThrown(): void
    {
        $this->expectException(StorageException::class);
        throw new StorageException('thrown');
    }

    public function testStorageExceptionCanBeCaughtAsRuntimeException(): void
    {
        $caught = false;

        try {
            throw new StorageException('thrown');
        } catch (\RuntimeException $e) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    // ── FileNotFoundException ────────────────────────────────────────────────

    public function testFileNotFoundExceptionExtendsStorageException(): void
    {
        $e = new FileNotFoundException(new Path('/a/b.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testFileNotFoundExceptionFullMessage(): void
    {
        $e = new FileNotFoundException(new Path('/a/b.txt'));
        self::assertSame('File not found: /a/b.txt', $e->getMessage());
    }

    public function testFileNotFoundExceptionMessageContainsPath(): void
    {
        $e = new FileNotFoundException(new Path('/uploads/file.txt'));
        self::assertStringContainsString('/uploads/file.txt', $e->getMessage());
    }

    public function testFileNotFoundExceptionMessageHasCorrectPrefix(): void
    {
        $e = new FileNotFoundException(new Path('/a/b.txt'));
        self::assertStringStartsWith('File not found: ', $e->getMessage());
    }

    public function testFileNotFoundExceptionCodeIsZero(): void
    {
        $e = new FileNotFoundException(new Path('/a.txt'));
        self::assertSame(0, $e->getCode());
    }

    public function testFileNotFoundExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('low-level');
        $e = new FileNotFoundException(new Path('/a.txt'), $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testFileNotFoundExceptionDefaultPreviousIsNull(): void
    {
        $e = new FileNotFoundException(new Path('/a.txt'));
        self::assertNull($e->getPrevious());
    }

    // ── DirectoryNotFoundException ───────────────────────────────────────────

    public function testDirectoryNotFoundExceptionExtendsStorageException(): void
    {
        $e = new DirectoryNotFoundException(new Path('/dir'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testDirectoryNotFoundExceptionFullMessage(): void
    {
        $e = new DirectoryNotFoundException(new Path('/some/dir'));
        self::assertSame('Directory not found: /some/dir', $e->getMessage());
    }

    public function testDirectoryNotFoundExceptionMessageContainsPath(): void
    {
        $e = new DirectoryNotFoundException(new Path('/uploads/dir'));
        self::assertStringContainsString('/uploads/dir', $e->getMessage());
    }

    public function testDirectoryNotFoundExceptionMessageHasCorrectPrefix(): void
    {
        $e = new DirectoryNotFoundException(new Path('/dir'));
        self::assertStringStartsWith('Directory not found: ', $e->getMessage());
    }

    public function testDirectoryNotFoundExceptionCodeIsZero(): void
    {
        $e = new DirectoryNotFoundException(new Path('/dir'));
        self::assertSame(0, $e->getCode());
    }

    public function testDirectoryNotFoundExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new DirectoryNotFoundException(new Path('/dir'), $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testDirectoryNotFoundExceptionDefaultPreviousIsNull(): void
    {
        $e = new DirectoryNotFoundException(new Path('/dir'));
        self::assertNull($e->getPrevious());
    }

    // ── FileAlreadyExistsException ───────────────────────────────────────────

    public function testFileAlreadyExistsExceptionExtendsStorageException(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testFileAlreadyExistsExceptionFullMessage(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a/b.txt'));
        self::assertSame('File already exists: /a/b.txt', $e->getMessage());
    }

    public function testFileAlreadyExistsExceptionMessageContainsPath(): void
    {
        $e = new FileAlreadyExistsException(new Path('/uploads/file.txt'));
        self::assertStringContainsString('/uploads/file.txt', $e->getMessage());
    }

    public function testFileAlreadyExistsExceptionMessageHasCorrectPrefix(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a.txt'));
        self::assertStringStartsWith('File already exists: ', $e->getMessage());
    }

    public function testFileAlreadyExistsExceptionCodeIsZero(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a.txt'));
        self::assertSame(0, $e->getCode());
    }

    public function testFileAlreadyExistsExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('disk error');
        $e = new FileAlreadyExistsException(new Path('/a.txt'), $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testFileAlreadyExistsExceptionDefaultPreviousIsNull(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a.txt'));
        self::assertNull($e->getPrevious());
    }

    // ── PermissionDeniedException ────────────────────────────────────────────

    public function testPermissionDeniedExceptionExtendsStorageException(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testPermissionDeniedExceptionFullMessage(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertSame('Permission denied: /protected/file.txt', $e->getMessage());
    }

    public function testPermissionDeniedExceptionMessageContainsPath(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertStringContainsString('/protected/file.txt', $e->getMessage());
    }

    public function testPermissionDeniedExceptionMessageHasCorrectPrefix(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertStringStartsWith('Permission denied: ', $e->getMessage());
    }

    public function testPermissionDeniedExceptionCodeIsZero(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertSame(0, $e->getCode());
    }

    public function testPermissionDeniedExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('OS error');
        $e = new PermissionDeniedException(new Path('/protected/file.txt'), $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testPermissionDeniedExceptionDefaultPreviousIsNull(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertNull($e->getPrevious());
    }

    // ── OperationNotSupportedException ──────────────────────────────────────

    public function testOperationNotSupportedExceptionExtendsStorageException(): void
    {
        $e = new OperationNotSupportedException('read');
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testOperationNotSupportedExceptionFullMessage(): void
    {
        $e = new OperationNotSupportedException('copy');
        self::assertSame('Operation not supported: copy', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionMessageContainsOperation(): void
    {
        $e = new OperationNotSupportedException('listContents');
        self::assertStringContainsString('listContents', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionMessageHasCorrectPrefix(): void
    {
        $e = new OperationNotSupportedException('write');
        self::assertStringStartsWith('Operation not supported: ', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionCanBeCreatedWithoutOperation(): void
    {
        $e = new OperationNotSupportedException();
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testOperationNotSupportedExceptionEmptyOperationMessage(): void
    {
        $e = new OperationNotSupportedException();
        self::assertSame('Operation not supported: ', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionCodeIsZero(): void
    {
        $e = new OperationNotSupportedException('read');
        self::assertSame(0, $e->getCode());
    }

    public function testOperationNotSupportedExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('not implemented');
        $e = new OperationNotSupportedException('delete', $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testOperationNotSupportedExceptionDefaultPreviousIsNull(): void
    {
        $e = new OperationNotSupportedException('read');
        self::assertNull($e->getPrevious());
    }

    // ── AdapterException ────────────────────────────────────────────────────

    public function testAdapterExceptionExtendsStorageException(): void
    {
        $e = new AdapterException('something went wrong');
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testAdapterExceptionMessageIsPreserved(): void
    {
        $e = new AdapterException('disk full');
        self::assertSame('disk full', $e->getMessage());
    }

    public function testAdapterExceptionCodeIsZero(): void
    {
        $e = new AdapterException('error');
        self::assertSame(0, $e->getCode());
    }

    public function testAdapterExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new AdapterException('wrapped', $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testAdapterExceptionDefaultPreviousIsNull(): void
    {
        $e = new AdapterException('error');
        self::assertNull($e->getPrevious());
    }
}
