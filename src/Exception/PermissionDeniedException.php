<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Exception;

use MisterIcy\Storage\ValueObject\Path;

class PermissionDeniedException extends StorageException
{
    public function __construct(Path $path, ?\Throwable $previous = null)
    {
        parent::__construct("Permission denied: {$path}", 0, $previous);
    }
}
