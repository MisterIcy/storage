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
    public function testStorageExceptionExtendsRuntimeException(): void
    {
        $e = new StorageException('error');
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testFileNotFoundExceptionExtendsStorageException(): void
    {
        $e = new FileNotFoundException(new Path('/a/b.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testFileNotFoundExceptionMessageContainsPath(): void
    {
        $path = new Path('/uploads/file.txt');
        $e = new FileNotFoundException($path);
        self::assertStringContainsString('/uploads/file.txt', $e->getMessage());
    }

    public function testFileNotFoundExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('low-level');
        $e = new FileNotFoundException(new Path('/a.txt'), $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testDirectoryNotFoundExceptionExtendsStorageException(): void
    {
        $e = new DirectoryNotFoundException(new Path('/dir'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testDirectoryNotFoundExceptionMessageContainsPath(): void
    {
        $path = new Path('/uploads/dir');
        $e = new DirectoryNotFoundException($path);
        self::assertStringContainsString('/uploads/dir', $e->getMessage());
    }

    public function testFileAlreadyExistsExceptionExtendsStorageException(): void
    {
        $e = new FileAlreadyExistsException(new Path('/a.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testFileAlreadyExistsExceptionMessageContainsPath(): void
    {
        $path = new Path('/uploads/file.txt');
        $e = new FileAlreadyExistsException($path);
        self::assertStringContainsString('/uploads/file.txt', $e->getMessage());
    }

    public function testPermissionDeniedExceptionExtendsStorageException(): void
    {
        $e = new PermissionDeniedException(new Path('/protected/file.txt'));
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testPermissionDeniedExceptionMessageContainsPath(): void
    {
        $path = new Path('/protected/file.txt');
        $e = new PermissionDeniedException($path);
        self::assertStringContainsString('/protected/file.txt', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionExtendsStorageException(): void
    {
        $e = new OperationNotSupportedException('read');
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testOperationNotSupportedExceptionMessageContainsOperation(): void
    {
        $e = new OperationNotSupportedException('listContents');
        self::assertStringContainsString('listContents', $e->getMessage());
    }

    public function testOperationNotSupportedExceptionCanBeCreatedWithoutOperation(): void
    {
        $e = new OperationNotSupportedException();
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testAdapterExceptionExtendsStorageException(): void
    {
        $e = new AdapterException('something went wrong');
        self::assertInstanceOf(StorageException::class, $e);
    }

    public function testAdapterExceptionForwardsPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new AdapterException('wrapped', $previous);
        self::assertSame($previous, $e->getPrevious());
    }

    public function testAdapterExceptionMessageIsPreserved(): void
    {
        $e = new AdapterException('disk full');
        self::assertSame('disk full', $e->getMessage());
    }
}
