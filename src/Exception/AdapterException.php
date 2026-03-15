<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Exception;

class AdapterException extends StorageException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
