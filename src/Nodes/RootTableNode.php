<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class RootTableNode implements Node
{
    /**
     * @param  KeyValuePairNode[] | TableNode[] | ArrayTableNode[]  $elements
     */
    public function __construct(
        protected array $elements
    ) {}

    public function addElement(KeyValuePairNode|TableNode|ArrayTableNode $element): void
    {
        $this->elements[] = $element;
    }

    public function elements(): array
    {
        return $this->elements;
    }
}
