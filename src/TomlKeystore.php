<?php

namespace Devium\Toml;

use Devium\Toml\Nodes\ArrayTableNode;
use Devium\Toml\Nodes\KeyNode;
use Devium\Toml\Nodes\KeyValuePairNode;
use Devium\Toml\Nodes\TableNode;

/**
 * Tracks defined keys and tables to reject duplicate definitions.
 *
 * Keys are stored as dotted paths, array table entries as "path.[index]".
 *
 * @internal
 */
final class TomlKeystore
{
    /** @var array<string, true> */
    private array $keys = [];

    /** @var array<string, true> */
    private array $tables = [];

    private ?string $lastTable = null;

    /** @var array<string, true> */
    private array $arrayTables = [];

    /** @var array<string, true> */
    private array $implicitTables = [];

    /** @var array<string, array{0: int, 1: string}> derived header => [seq, arrayTableKey] of the latest array-table entry with that header */
    private array $latestByHeader = [];

    /** @var array<string, int> resolved array key (without index) => number of entries */
    private array $arrayCounts = [];

    private int $seq = 0;

    /**
     * @throws TomlError
     */
    public function addNode(KeyValuePairNode|TableNode|ArrayTableNode $node): void
    {
        match ($node::class) {
            KeyValuePairNode::class => $this->addKeyValuePairNode($node),
            TableNode::class => $this->addTableNode($node),
            ArrayTableNode::class => $this->addArrayTableNode($node),
        };
    }

    /**
     * @throws TomlError
     */
    protected function addKeyValuePairNode(KeyValuePairNode $node): void
    {
        $key = $this->lastTable !== null ? "$this->lastTable." : '';

        $components = $this->makeKeyComponents($node->key);
        $last = count($components) - 1;

        foreach ($components as $i => $component) {
            $key .= ($i !== 0 ? '.' : '').$component;

            if (isset($this->keys[$key]) || isset($this->tables[$key]) || isset($this->tables["$key.[0]"])) {
                throw new TomlError('key duplication');
            }

            // dotted keys define the tables on the way implicitly
            if ($i < $last) {
                $this->implicitTables[$key] = true;

                continue;
            }

            if (isset($this->implicitTables[$key])) {
                throw new TomlError('key duplication');
            }
        }

        $this->keys[$key] = true;
    }

    /**
     * @return string[]
     */
    protected function makeKeyComponents(KeyNode $keyNode): array
    {
        $components = [];
        foreach ($keyNode->keys() as $key) {
            $components[] = $key->value;
        }

        return $components;
    }

    /**
     * @throws TomlError
     */
    protected function addTableNode(TableNode $tableNode): void
    {
        $components = $this->makeKeyComponents($tableNode->key);
        $header = implode('.', $components);
        $foundArrayTable = $this->nearestEntry($header, true)[1] ?? null;

        $key = '';

        // a table under an array table belongs to its latest entry
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
            $key .= ($i !== 0 ? '.' : '').str_replace('.', '\.', $component);
            $i++;

            if (isset($this->keys[$key])) {
                throw new TomlError('key duplication');
            }
        }

        if (isset($this->arrayTables[$key]) || isset($this->tables[$key]) || isset($this->implicitTables[$key])) {
            throw new TomlError('key duplication');
        }

        $this->addTable($key);
    }

    /**
     * @throws TomlError
     */
    protected function addArrayTableNode(ArrayTableNode $arrayTableNode): void
    {
        $header = implode('.', $this->makeKeyComponents($arrayTableNode->key));

        if (isset($this->keys[$header]) || isset($this->tables[$header]) || isset($this->implicitTables[$header])) {
            throw new TomlError('key duplication');
        }

        $key = $header;
        $parent = $this->nearestEntry($header, false);
        if ($parent !== null) {
            $key = $parent[1].substr($header, strlen($this->makeHeaderFromArrayTable($parent[1])));
        }
        $index = $this->arrayCounts[$key] ?? 0;

        // the first entry must not come after a sub-table of it
        if ($index === 0) {
            $prefix = "$header.";
            foreach ($this->tables as $table => $_) {
                if (str_starts_with((string) $table, $prefix)) {
                    throw new TomlError('key duplication');
                }
            }
        }

        if (isset($this->keys[$key]) || isset($this->tables[$key])) {
            throw new TomlError('key duplication');
        }

        $this->arrayCounts[$key] = $index + 1;
        $key .= ".[$index]";
        if (! isset($this->arrayTables[$key])) {
            $this->arrayTables[$key] = true;
            $this->latestByHeader[$this->makeHeaderFromArrayTable($key)] = [++$this->seq, $key];
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

    /**
     * Finds the most recent array-table entry whose header is a prefix of $header:
     * any prefix when $anyPrefix, otherwise only prefixes ending right before a dot.
     *
     * @return array{0: int, 1: string}|null
     */
    private function nearestEntry(string $header, bool $anyPrefix): ?array
    {
        $best = null;

        if ($anyPrefix) {
            $length = strlen($header);
            for ($k = 0; $k <= $length; $k++) {
                $entry = $this->latestByHeader[substr($header, 0, $k)] ?? null;
                if ($entry !== null && ($best === null || $entry[0] > $best[0])) {
                    $best = $entry;
                }
            }

            return $best;
        }

        $offset = 0;
        while (($k = strpos($header, '.', $offset)) !== false) {
            $entry = $this->latestByHeader[substr($header, 0, $k)] ?? null;
            if ($entry !== null && ($best === null || $entry[0] > $best[0])) {
                $best = $entry;
            }
            $offset = $k + 1;
        }

        return $best;
    }

    /**
     * "a.[0].b.[1]" => "a.b"
     */
    protected function makeHeaderFromArrayTable(string $arrayTable): string
    {
        return implode(
            '.',
            array_filter(
                $this->unescapedExplode('.', $arrayTable),
                static fn (string $item) => ! str_starts_with($item, '[')
            )
        );
    }

    /**
     * Splits $value by $character, ignoring occurrences escaped with a backslash.
     *
     * @return string[]
     */
    protected function unescapedExplode(string $character, string $value): array
    {
        return array_map(
            static fn (string $item) => str_replace(__METHOD__, $character, $item),
            explode($character, str_replace('\\'.$character, __METHOD__, $value))
        );
    }
}
