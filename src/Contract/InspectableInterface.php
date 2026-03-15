<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface InspectableInterface extends AdapterInterface
{
    public function exists(Path $path): bool;

    public function isFile(Path $path): bool;

    public function isDir(Path $path): bool;
}
