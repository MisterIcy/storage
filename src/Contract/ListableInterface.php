<?php

declare(strict_types=1);

namespace MisterIcy\Storage\Contract;

use MisterIcy\Storage\ValueObject\Path;

interface ListableInterface extends AdapterInterface
{
    /**
     * List immediate children (files and directories) of the given path.
     *
     * @return iterable<Path>
     */
    public function listContents(Path $path): iterable;
}
