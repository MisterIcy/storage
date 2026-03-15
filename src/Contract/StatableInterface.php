<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\FileMetadata;
use MisterIcy\Storage\ValueObject\Path;

interface StatableInterface extends AdapterInterface
{
    public function metadata(Path $path): FileMetadata;
}
