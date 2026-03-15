<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Exception;

class OperationNotSupportedException extends StorageException
{
    public function __construct(string $operation = '', ?\Throwable $previous = null)
    {
        parent::__construct("Operation not supported: {$operation}", 0, $previous);
    }
}
