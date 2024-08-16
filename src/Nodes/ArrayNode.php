<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class ArrayNode implements Node, ValuableNode
{
    /**
     * @param  Node[]  $elements
     */
    public function __construct(private array $elements) {}

    public function addElement(ValuableNode $element): void
    {
        $this->elements[] = $element;
    }

    public function elements(): array
    {
        return $this->elements;
    }
}
