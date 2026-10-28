<?php declare(strict_types=1);

namespace Tests\Fixtures;

use STDW\Contract\View\AliasManagerInterface;


final class FakeAliasManager implements AliasManagerInterface
{
    /** @var array<string, string> */
    private array $aliases = [];


    public function alias(string $name, ?string $path = null): ?string
    {
        if ($path !== null) {
            $this->aliases[$name] = rtrim($path, '/') . '/';

            return $this->aliases[$name];
        }

        return $this->aliases[$name] ?? null;
    }

    public function resolveAlias(string $view): string
    {
        $parts = explode(':', $view, 2);

        if (count($parts) === 1) {
            return $view;
        }

        return ($this->aliases[$parts[0]] ?? '') . $parts[1];
    }
}
