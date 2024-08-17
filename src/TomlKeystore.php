<?php

namespace Devium\Toml;

use Devium\Toml\Nodes\ArrayTableNode;
use Devium\Toml\Nodes\BareNode;
use Devium\Toml\Nodes\KeyNode;
use Devium\Toml\Nodes\KeyValuePairNode;
use Devium\Toml\Nodes\StringNode;
use Devium\Toml\Nodes\TableNode;
use Ds\Set;

/**
 * @internal
 */
final class TomlKeystore
{
    private readonly Set $keys;

    private readonly Set $tables;

    private readonly Set $arrayTables;

    private readonly Set $implicitTables;

    public function __construct()
    {
        $this->keys = new Set;
        $this->tables = new Set;
        $this->arrayTables = new Set;
        $this->implicitTables = new Set;
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

        if (! $this->tables->isEmpty()) {
            $table = $this->tables->last();
            $key = "$table.";
        }

        $components = $this->makeKeyComponents($node->key);
        $counter = count($components);

        for ($i = 0; $i < $counter; $i++) {
            $component = $components[$i];

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
        return $this->keys->contains($key);
    }

    protected function tablesContains(string $key): bool
    {
        return $this->tables->contains($key);
    }

    protected function tablesContainsZeroIndex(string $key): bool
    {
        return $this->tables->contains("$key.[0]");
    }

    protected function implicitTablesAdd(string $key): void
    {
        $this->implicitTables->add($key);
    }

    protected function implicitTablesContains(string $key): bool
    {
        return $this->implicitTables->contains($key);
    }

    protected function keysAdd(string $key): void
    {
        $this->keys->add($key);
    }

    /**
     * @throws TomlError
     */
    protected function addTableNode(TableNode $tableNode): void
    {
        $components = $this->makeKeyComponents($tableNode->key);
        $header = $this->makeKey($tableNode->key);
        $arrayTable = $this->arrayTables->reversed();
        $foundArrayTable = null;

        foreach ($arrayTable as $arrayTableItem) {
            if (str_starts_with($header, $this->makeHeaderFromArrayTable($arrayTableItem))) {
                $foundArrayTable = $arrayTableItem;

                break;
            }
        }

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

        $this->tables->add($key);
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
        return $this->arrayTables->contains($key);
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
        $index = 0;

        for ($i = $this->arrayTables->count() - 1; $i >= 0; $i--) {
            $arrayTable = $this->arrayTables[$i];
            $arrayTableHeader = $this->makeHeaderFromArrayTable($arrayTable);

            if ($arrayTableHeader === $header) {
                $index++;

                continue;
            }

            if (str_starts_with($header, $arrayTableHeader)) {
                $key = $arrayTable.substr($header, strlen($arrayTableHeader));

                break;
            }
        }

        if ($index === 0 && ! $this->tables->filter(
            static fn ($table) => str_starts_with((string) $table, $header))->isEmpty()
        ) {
            throw new TomlError('key duplication');
        }

        if ($this->keysContains($key) || $this->tablesContains($key)) {
            throw new TomlError('key duplication');
        }

        $key .= ".[$index]";
        $this->arrayTables->add($key);
        $this->tables->add($key);
    }
}
