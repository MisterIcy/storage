<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface TransferableInterface extends AdapterInterface
{
    public function copy(Path $source, Path $destination): void;

    public function move(Path $source, Path $destination): void;

    public function rename(Path $source, Path $destination): void;
}
