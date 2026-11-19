<?php

namespace Devium\Toml;

use Devium\Toml\Nodes\ArrayTableNode;
use Devium\Toml\Nodes\BareNode;
use Devium\Toml\Nodes\KeyNode;
use Devium\Toml\Nodes\KeyValuePairNode;
use Devium\Toml\Nodes\StringNode;
use Devium\Toml\Nodes\TableNode;

/**
 * @internal
 */
final class TomlKeystore
{
    private array $keys = [];

    private array $tables = [];

    private ?string $lastTable = null;

    /** @var array<string,true> */
    private array $arrayTablesSet = [];

    /** @var list<string> */
    private array $arrayTables = [];

    private array $implicitTables = [];

    /** derived header => [seq, arrayTableKey] of the most recent array-table entry with that header */
    private array $latestByHeader = [];

    /** resolved array key (without index) => number of entries */
    private array $arrayCounts = [];

    private int $seq = 0;

    /** @return array{0:int,1:string}|null most recent entry whose header is a prefix of $header (at given cut points) */
    private function nearestEntry(string $header, bool $anyPrefix, bool $allowEqual): ?array
    {
        $best = null;
        $len = strlen($header);
        if ($anyPrefix) {
            for ($k = 0; $k <= $len; $k++) {
                $e = $this->latestByHeader[substr($header, 0, $k)] ?? null;
                if ($e !== null && ($best === null || $e[0] > $best[0])) {
                    $best = $e;
                }
            }

            return $best;
        }
        $off = 0;
        while (($k = strpos($header, '.', $off)) !== false) {
            $e = $this->latestByHeader[substr($header, 0, $k)] ?? null;
            if ($e !== null && ($best === null || $e[0] > $best[0])) {
                $best = $e;
            }
            $off = $k + 1;
        }

        return $best;
    }

    /**
     * @throws TomlError
     */
    public function addNode(KeyValuePairNode|TableNode|ArrayTableNode $node): void
    {
        switch ($node::class) {
            case KeyValuePairNode::class:
                $this->addKeyValuePairNode($node);
                break;
            case TableNode::class:
                $this->addTableNode($node);
                break;
            case ArrayTableNode::class:
                $this->addArrayTableNode($node);
                break;
            default:
                throw new TomlError('unsupported Node');
        }
    }

    /**
     * @throws TomlError
     */
    protected function addKeyValuePairNode(KeyValuePairNode $node): void
    {
        $key = '';

        if ($this->lastTable !== null) {
            $key = "$this->lastTable.";
        }

        $components = $this->makeKeyComponents($node->key);

        foreach ($components as $i => $iValue) {
            $component = $iValue;

            $key .= ($i !== 0 ? '.' : '').$component;

            if ($this->keysContains($key) || $this->tablesContains($key) || $this->tablesContainsZeroIndex($key)) {
                throw new TomlError('key duplication');
            }

            if (count($components) > 1 && $i < count($components) - 1) {
                $this->implicitTablesAdd($key);

                continue;
            }

            if ($this->implicitTablesContains($key)) {
                throw new TomlError('key duplication');
            }
        }

        $this->keysAdd($key);
    }

    protected function makeKeyComponents(KeyNode $keyNode): array
    {
        return array_map(static fn (BareNode|StringNode $key) => $key->value, $keyNode->keys());
    }

    protected function keysContains(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    protected function tablesContains(string $key): bool
    {
        return isset($this->tables[$key]);
    }

    protected function tablesContainsZeroIndex(string $key): bool
    {
        return isset($this->tables["$key.[0]"]);
    }

    protected function implicitTablesAdd(string $key): void
    {
        $this->implicitTables[$key] = true;
    }

    protected function implicitTablesContains(string $key): bool
    {
        return isset($this->implicitTables[$key]);
    }

    protected function keysAdd(string $key): void
    {
        $this->keys[$key] = true;
    }

    /**
     * @throws TomlError
     */
    protected function addTableNode(TableNode $tableNode): void
    {
        $components = $this->makeKeyComponents($tableNode->key);
        $header = $this->makeKey($tableNode->key);
        $found = $this->nearestEntry($header, true, true);
        $foundArrayTable = $found[1] ?? null;

        $key = '';

        if ($foundArrayTable !== null) {
            $foundArrayTableHeader = $this->makeHeaderFromArrayTable($foundArrayTable);

            $components = array_filter(
                $this->unescapedExplode('.', substr($header, strlen($foundArrayTableHeader))),
                static fn (string $component) => $component !== ''
            );

            if ($components === []) {
                throw new TomlError('broken key');
            }

            $key = "$foundArrayTable.";
        }

        $i = 0;
        foreach ($components as $component) {

            $component = str_replace('.', '\.', $component);

            $key .= ($i !== 0 ? '.' : '').$component;

            $i++;

            if ($this->keysContains($key)) {
                throw new TomlError('key duplication');
            }
        }

        if ($this->arrayTablesContains($key) || $this->tablesContains($key) || $this->implicitTablesContains($key)) {
            throw new TomlError('key duplication');
        }

        $this->addTable($key);
    }

    private function addTable(string $key): void
    {
        if (! isset($this->tables[$key])) {
            $this->tables[$key] = true;
            $this->lastTable = $key;
        }
    }

    protected function makeKey(KeyNode $keyNode): string
    {
        return implode('.', $this->makeKeyComponents($keyNode));
    }

    protected function makeHeaderFromArrayTable(string $arrayTable): string
    {
        return implode(
            '.',
            array_filter(
                $this->unescapedExplode('.', $arrayTable),
                static fn ($item) => ! str_starts_with((string) $item, '[')
            )
        );
    }

    protected function unescapedExplode(string $character, string $value): array
    {
        return array_map(
            static fn ($item) => str_replace(__METHOD__, $character, $item),
            explode($character, str_replace('\\'.$character, __METHOD__, $value))
        );
    }

    protected function arrayTablesContains(string $key): bool
    {
        return isset($this->arrayTablesSet[$key]);
    }

    /**
     * @throws TomlError
     */
    protected function addArrayTableNode(ArrayTableNode $arrayTableNode): void
    {
        $header = $this->makeKey($arrayTableNode->key);

        if (
            $this->keysContains($header) || $this->tablesContains($header) || $this->implicitTablesContains($header)
        ) {
            throw new TomlError('key duplication');
        }

        $key = $header;
        $parent = $this->nearestEntry($header, false, false);
        if ($parent !== null) {
            $key = $parent[1].substr($header, strlen($this->makeHeaderFromArrayTable($parent[1])));
        }
        $index = $this->arrayCounts[$key] ?? 0;

        if ($index === 0) {
            foreach ($this->tables as $table => $_) {
                if (str_starts_with((string) $table, $header.'.')) {
                    throw new TomlError('key duplication');
                }
            }
        }

        if ($this->keysContains($key) || $this->tablesContains($key)) {
            throw new TomlError('key duplication');
        }

        $this->arrayCounts[$key] = $index + 1;
        $key .= ".[$index]";
        if (! isset($this->arrayTablesSet[$key])) {
            $this->arrayTablesSet[$key] = true;
            $this->arrayTables[] = $key;
            $this->latestByHeader[$this->makeHeaderFromArrayTable($key)] = [++$this->seq, $key];
        }
        $this->addTable($key);
    }
}
