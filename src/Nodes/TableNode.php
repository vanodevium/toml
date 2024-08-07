<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
class TableNode implements Node
{
    /**
     * @param  KeyValuePairNode[]  $elements
     */
    public function __construct(
        public readonly KeyNode $key,
        private array $elements
    ) {}

    public function addElement(KeyValuePairNode $element): void
    {
        $this->elements[] = $element;
    }

    public function elements(): array
    {
        return $this->elements;
    }
}
