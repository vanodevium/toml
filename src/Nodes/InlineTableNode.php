<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class InlineTableNode implements Node
{
    /**
     * @param  KeyValuePairNode[]  $elements
     */
    public function __construct(private array $elements) {}

    public function addElement(KeyValuePairNode $element): void
    {
        $this->elements[] = $element;
    }

    public function elements(): array
    {
        return $this->elements;
    }
}
