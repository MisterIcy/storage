<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface ReadableInterface extends AdapterInterface
{
    /**
     * Read the file at the given path and return a stream resource.
     *
     * @return resource
     */
    public function read(Path $path);
}
