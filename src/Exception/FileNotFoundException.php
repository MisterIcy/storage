<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Exception;

use MisterIcy\Storage\ValueObject\Path;

class FileNotFoundException extends StorageException
{
    public function __construct(Path $path, ?\Throwable $previous = null)
    {
        parent::__construct("File not found: {$path}", 0, $previous);
    }
}
