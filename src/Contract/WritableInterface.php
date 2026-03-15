<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface WritableInterface extends AdapterInterface
{
    /**
     * Write the given stream resource to the given path.
     *
     * @param resource $stream
     */
    public function write(Path $path, $stream): void;

    /**
     * Delete the file at the given path.
     */
    public function delete(Path $path): void;
}
