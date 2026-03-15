<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface DirectoryInterface extends AdapterInterface
{
    public function createDirectory(Path $path): void;

    public function deleteDirectory(Path $path): void;
}
