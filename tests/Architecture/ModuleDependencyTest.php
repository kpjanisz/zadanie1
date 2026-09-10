<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the module graph acyclic.
 *
 * This is not a style preference. A generic module that imports a domain one
 * cannot be reused by the next domain, and the cycle it creates is invisible
 * until somebody tries. It has already regressed once — a response listener in
 * Shared reached for a product-specific class — so it is asserted rather than
 * remembered.
 */
#[CoversNothing]
final class ModuleDependencyTest extends TestCase
{
    /**
     * What each module is allowed to import. Shared is deliberately empty: it is
     * the bottom of the graph and must stay there.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'Product' => ['Category', 'Notification', 'Shared'],
        'Category' => ['Shared'],
        'Notification' => ['Shared'],
        'Security' => ['Shared'],
        'Shared' => [],
    ];

    #[DataProvider('modules')]
    public function testAModuleImportsOnlyWhatItIsAllowedTo(string $module): void
    {
        $offenders = [];

        foreach ($this->importsOf($module) as $file => $imported) {
            foreach ($imported as $dependency) {
                if (!\in_array($dependency, self::ALLOWED[$module], true)) {
                    $offenders[] = \sprintf('%s imports App\\%s', $file, $dependency);
                }
            }
        }

        self::assertSame([], $offenders, \sprintf(
            "Module %s may only import [%s].\n%s",
            $module,
            implode(', ', self::ALLOWED[$module]) ?: 'nothing',
            implode("\n", $offenders),
        ));
    }

    /**
     * Walks the graph rather than comparing pairs: checking only for a mutual
     * A→B / B→A edge would let a transitive A→B→C→A through, which is exactly
     * the shape a cycle takes once more than two modules are involved.
     */
    public function testTheGraphHasNoCycles(): void
    {
        $settled = [];

        foreach (array_keys(self::ALLOWED) as $module) {
            $cycle = $this->findCycleFrom($module, [], $settled);

            self::assertNull($cycle, \sprintf('Dependency cycle: %s.', implode(' → ', $cycle ?? [])));
        }
    }

    /**
     * Depth-first search returning the path that closes a cycle, or null.
     *
     * @param list<string> $path
     * @param array<string, true> $settled modules already proven acyclic
     *
     * @return list<string>|null
     */
    private function findCycleFrom(string $module, array $path, array &$settled): ?array
    {
        $openedAt = array_search($module, $path, true);

        if (false !== $openedAt) {
            $path[] = $module;

            return \array_slice($path, $openedAt);
        }

        if (isset($settled[$module])) {
            return null;
        }

        $path[] = $module;

        foreach (self::ALLOWED[$module] ?? [] as $dependency) {
            $cycle = $this->findCycleFrom($dependency, $path, $settled);

            if (null !== $cycle) {
                return $cycle;
            }
        }

        $settled[$module] = true;

        return null;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function modules(): iterable
    {
        foreach (array_keys(self::ALLOWED) as $module) {
            yield $module => [$module];
        }
    }

    /**
     * @return array<string, list<string>> file path => imported module names
     */
    private function importsOf(string $module): array
    {
        $root = \dirname(__DIR__, 2).'/src/'.$module;
        self::assertDirectoryExists($root);

        $found = [];

        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);

            preg_match_all('/^use App\\\\(\w+)\\\\/m', $source, $matches);

            $imported = array_values(array_unique(array_diff($matches[1], [$module])));

            if ([] !== $imported) {
                $found[str_replace(\dirname(__DIR__, 2).'/', '', $file->getPathname())] = $imported;
            }
        }

        return $found;
    }
}
