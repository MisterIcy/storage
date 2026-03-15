<?php

declare(strict_types=1);

namespace MisterIcy\Storage\ValueObject;

abstract class AbstractPath
{
    private string $path;

    public function __construct(string $raw)
    {
        $this->path = $this->normalise($raw);
    }

    private function normalise(string $raw): string
    {
        // Normalise directory separators
        $raw = str_replace('\\', '/', $raw);

        $segments = explode('/', $raw);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                // Skip empty segments (from double slashes or leading /) and current-dir dots
                continue;
            }

            if ($segment === '..') {
                array_pop($result);
            } else {
                $result[] = $segment;
            }
        }

        if (empty($result)) {
            return '/';
        }

        return '/' . implode('/', $result);
    }

    public function __toString(): string
    {
        return $this->path;
    }

    public function equals(self $other): bool
    {
        return $this->path === (string) $other;
    }
}
