<?php

namespace Devium\Toml;

use Devium\Toml\Nodes\ArrayNode;
use Devium\Toml\Nodes\ArrayTableNode;
use Devium\Toml\Nodes\BareNode;
use Devium\Toml\Nodes\BooleanNode;
use Devium\Toml\Nodes\FloatNode;
use Devium\Toml\Nodes\InlineTableNode;
use Devium\Toml\Nodes\IntegerNode;
use Devium\Toml\Nodes\KeyNode;
use Devium\Toml\Nodes\KeyValuePairNode;
use Devium\Toml\Nodes\LocalDateNode;
use Devium\Toml\Nodes\LocalDateTimeNode;
use Devium\Toml\Nodes\LocalTimeNode;
use Devium\Toml\Nodes\OffsetDateTimeNode;
use Devium\Toml\Nodes\RootTableNode;
use Devium\Toml\Nodes\StringNode;
use Devium\Toml\Nodes\TableNode;
use Throwable;

/**
 * @internal
 */
final class TomlParser
{
    protected TomlTokenizer $tokenizer;

    protected TomlKeystore $keystore;

    protected RootTableNode $rootTableNode;

    protected TableNode|RootTableNode $tableNode;

    /**
     * @throws TomlError
     */
    public function __construct(string $input)
    {
        $this->tokenizer = new TomlTokenizer($input);
        $this->keystore = new TomlKeystore;
        $this->rootTableNode = new RootTableNode([]);
        $this->tableNode = $this->rootTableNode;
    }

    /**
     * @throws TomlError
     */
    public function parse(): RootTableNode
    {
        try {
            for (; ;) {
                $node = $this->expression();
                if (! $node) {
                    break;
                }

                $this->tokenizer->take('WHITESPACE');
                $this->tokenizer->take('COMMENT');
                $this->tokenizer->assert('NEWLINE', 'EOF');
                $this->keystore->addNode($node);
                if (in_array($node::class, [TableNode::class, ArrayTableNode::class])) {
                    $this->tableNode = $node;
                    $this->rootTableNode->addElement($node);
                } else {
                    $this->tableNode->addElement($node);
                }
            }
        } catch (TomlError $error) {
            throw new TomlError(
                $error->getMessage(),
                $this->tokenizer->getInput(),
                $this->tokenizer->getPosition(),
            );
        }

        return $this->rootTableNode;
    }

    /**
     * @throws TomlError
     */
    public function expression(): KeyValuePairNode|TableNode|ArrayTableNode|null
    {
        $this->takeCommentsAndNewlines();
        $token = $this->tokenizer->peek();

        return match ($token->type) {
            'LEFT_SQUARE_BRACKET' => $this->table(),
            'EOF' => null,
            default => $this->keyValuePair(),
        };
    }

    /**
     * @throws TomlError
     */
    public function takeCommentsAndNewlines(): void
    {
        for (; ;) {
            $this->tokenizer->take('WHITESPACE');
            if ($this->tokenizer->take('COMMENT')) {
                if ($this->tokenizer->isEOF()) {
                    break;
                }
                $this->tokenizer->assert('NEWLINE');

                continue;
            }
            if (! $this->tokenizer->take('NEWLINE')) {
                break;
            }
        }
    }

    /**
     * @throws TomlError
     */
    public function table(): TableNode
    {
        $this->tokenizer->next();

        $isArrayTable = $this->tokenizer->take('LEFT_SQUARE_BRACKET');
        $key = $this->key();

        $this->tokenizer->assert('RIGHT_SQUARE_BRACKET');

        if ($isArrayTable) {
            $this->tokenizer->assert('RIGHT_SQUARE_BRACKET');
        }

        return $isArrayTable ? new ArrayTableNode($key, []) : new TableNode($key, []);
    }

    /**
     * @throws TomlError
     */
    public function key(): KeyNode
    {
        $keyNode = new KeyNode([]);

        do {
            $this->tokenizer->take('WHITESPACE');
            $token = $this->tokenizer->next();

            switch ($token->type) {
                case 'BARE':
                    $keyNode->addKey(new BareNode($token->value));

                    break;

                case 'STRING':
                    if ($token->isMultiline) {
                        throw new TomlError('unexpected string value');
                    }

                    $keyNode->addKey(new StringNode($token->value));

                    break;

                default:
                    throw new TomlError('unexpected token type: '.$token->type);
            }

            $this->tokenizer->take('WHITESPACE');
        } while ($this->tokenizer->take('PERIOD'));

        return $keyNode;
    }

    /**
     * @throws TomlError
     */
    public function keyValuePair(): KeyValuePairNode
    {
        $key = $this->key();

        $this->tokenizer->assert('EQUALS');
        $this->tokenizer->take('WHITESPACE');

        return new KeyValuePairNode($key, $this->value());
    }

    /**
     * @throws TomlError
     */
    public function value(): StringNode|IntegerNode|FloatNode|BooleanNode|OffsetDateTimeNode|LocalDateTimeNode|LocalDateNode|LocalTimeNode|ArrayNode|InlineTableNode
    {
        $token = $this->tokenizer->next();

        return match ($token->type) {
            'STRING' => new StringNode($token->value),
            'BARE' => $this->booleanOrNumberOrDateOrDateTimeOrTime($token->value),
            'PLUS' => $this->plus(),
            'LEFT_SQUARE_BRACKET' => $this->array(),
            'LEFT_CURLY_BRACKET' => $this->inlineTable(),
            default => throw new TomlError('unexpected token type: '.$token->type),
        };
    }

    /**
     * @throws TomlError
     */
    public function booleanOrNumberOrDateOrDateTimeOrTime(string $value): BooleanNode|OffsetDateTimeNode|LocalDateTimeNode|LocalDateNode|LocalTimeNode|IntegerNode|FloatNode
    {
        if ($value === 'true' || $value === 'false') {
            return new BooleanNode($value === 'true');
        }

        if (str_contains(substr($value, 1), '-') && ! str_contains($value, 'e-') && ! str_contains($value, 'E-')) {
            return $this->dateOrDateTime($value);
        }
        if ($this->tokenizer->peek()->type === 'COLON') {
            return $this->time($value);
        }

        return $this->number($value);
    }

    /**
     * @throws TomlError
     */
    public function dateOrDateTime(string $value): OffsetDateTimeNode|LocalDateTimeNode|LocalDateNode
    {
        $token = $this->tokenizer->peek();

        if ($token->type === 'WHITESPACE' && $token->value === ' ') {
            $this->tokenizer->next();
            $token = $this->tokenizer->peek();

            if ($token->type !== 'BARE') {
                return new LocalDateNode(TomlLocalDate::fromString($value));
            }

            $this->tokenizer->next();
            $value .= 'T';
            $value .= $token->value;
        }

        if (! str_contains($value, 'T') && ! str_contains($value, 't')) {
            return new LocalDateNode(TomlLocalDate::fromString($value));
        }

        $tokens = $this->tokenizer->sequence('COLON', 'BARE', 'COLON', 'BARE');
        $value .= implode('', array_map(static fn (TomlToken $token) => $token->value, $tokens));

        if (
            str_ends_with($tokens[count($tokens) - 1]->value, 'Z') ||
            str_ends_with($tokens[count($tokens) - 1]->value, 'z')
        ) {
            return new OffsetDateTimeNode($this->parseDate($value));
        }

        if (str_contains($tokens[count($tokens) - 1]->value, '-')) {
            $this->tokenizer->assert('COLON');
            $token = $this->tokenizer->expect('BARE');
            $value .= ':';
            $value .= $token->value;

            return new OffsetDateTimeNode($this->parseDate($value));
        }

        $tokenType = $this->tokenizer->peek()->type;

        if ($tokenType === 'PLUS') {
            $this->tokenizer->next();
            $tokens = $this->tokenizer->sequence('BARE', 'COLON', 'BARE');
            $value .= '+';
            $value .= implode('', array_map(static fn (TomlToken $token) => $token->value, $tokens));

            return new OffsetDateTimeNode($this->parseDate($value));
        }

        if ($tokenType === 'PERIOD') {
            $this->tokenizer->next();
            $token = $this->tokenizer->expect('BARE');
            $value .= '.';
            $value .= $token->value;

            if (str_ends_with((string) $token->value, 'Z')) {
                return new OffsetDateTimeNode($this->parseDate($value));
            }

            if (str_contains((string) $token->value, '-')) {
                $this->tokenizer->assert('COLON');
                $token = $this->tokenizer->expect('BARE');
                $value .= ':';
                $value .= $token->value;

                return new OffsetDateTimeNode($this->parseDate($value));
            }

            if ($this->tokenizer->take('PLUS')) {
                $tokens = $this->tokenizer->sequence('BARE', 'COLON', 'BARE');
                $value .= '+';
                $value .= implode('', array_map(static fn (TomlToken $token) => $token->value, $tokens));

                return new OffsetDateTimeNode($this->parseDate($value));
            }
        }

        return new LocalDateTimeNode(TomlLocalDateTime::fromString($value));
    }

    /**
     * @throws TomlError
     */
    public function parseDate($value): TomlDateTime
    {
        try {
            return new TomlDateTime($value);
        } catch (Throwable) {
            throw new TomlError('error during datetime parsing');
        }
    }

    /**
     * @throws TomlError
     */
    public function time($value): LocalTimeNode
    {
        $tokens = $this->tokenizer->sequence('COLON', 'BARE', 'COLON', 'BARE');
        $value .= implode('', array_map(static fn (TomlToken $token) => $token->value, $tokens));
        if ($this->tokenizer->take('PERIOD')) {
            $token = $this->tokenizer->expect('BARE');
            $value .= '.';
            $value .= $token->value;
        }

        return new LocalTimeNode(TomlLocalTime::fromString($value));
    }

    /**
     * @throws TomlError
     */
    public function number($value): IntegerNode|FloatNode
    {
        $result = match ($value) {
            'inf', '+inf' => new FloatNode(INF),
            '-inf' => new FloatNode(-INF),
            'nan', '+nan', '-nan' => new FloatNode(NAN),
            default => null,
        };

        if ($result !== null) {
            return $result;
        }

        return match (true) {
            str_starts_with((string) $value, '0x') => $this->integer($value, 16),
            str_starts_with((string) $value, '0o') => $this->integer($value, 8),
            str_starts_with((string) $value, '0b') => $this->integer($value, 2),
            str_contains((string) $value, 'e') => $this->float($value),
            str_contains((string) $value, 'E') => $this->float($value),
            $this->tokenizer->peek()->type === 'PERIOD' => $this->float($value),
            default => $this->integer($value, 10),
        };
    }

    /**
     * @throws TomlError
     */
    public function integer($value, $radix): IntegerNode
    {
        $isSignAllowed = $radix === 10;
        $areLeadingZerosAllowed = $radix !== 10;
        $int = $this->parseInteger($value, $isSignAllowed, $areLeadingZerosAllowed, false, $radix)['int'];

        return new IntegerNode($int);
    }

    /**
     * @throws TomlError
     */
    public function parseInteger(
        $value, $isSignAllowed, $areLeadingZerosAllowed, $isUnparsedAllowed, $radix, bool $asString = false
    ): array {

        if (preg_match('/[^0-9-+._oxabcdef]/i', (string) $value)) {
            throw new TomlError('unexpected non-numeric value');
        }

        $sign = '';
        $i = 0;
        if ($value[$i] === '+' || $value[$i] === '-') {
            if (! $isSignAllowed) {
                throw new TomlError('unexpected sign (+/-)');
            }
            $sign = $value[$i];
            $i++;
        }

        if (! $areLeadingZerosAllowed && $value[$i] === '0' && ($i + 1) !== strlen((string) $value)) {
            throw new TomlError('unexpected leading zero');
        }

        if (preg_match('/[+-]?0[obx](_|$)/im', (string) $value)) {
            throw new TomlError('unexpected number formatting');
        }

        if (str_starts_with((string) $value, '0x') && ! preg_match('/^0[xX][0-9a-fA-F_]+$/', (string) $value)) {
            throw new TomlError('unexpected binary number formatting');
        }

        $isUnderscoreAllowed = false;
        for (; $i < strlen((string) $value); $i++) {
            $char = $value[$i];
            if ($char === '_') {
                if (! $isUnderscoreAllowed) {
                    throw new TomlError('unexpected underscore symbol');
                }
                $isUnderscoreAllowed = false;

                continue;
            }

            if (! ($i === 1 && (($radix === 8 && $char === 'o') || ($radix === 2 && $char === 'b'))) && ! $this->digitalChecks($radix, $char)) {
                break;
            }

            $isUnderscoreAllowed = true;
        }

        if (! $isUnderscoreAllowed) {
            throw new TomlError('unexpected underscore symbol');
        }

        $int = str_replace('_', '', TomlUtils::stringSlice($value, 0, $i));
        $unparsed = TomlUtils::stringSlice($value, $i);

        if (! $isUnparsedAllowed && $unparsed !== '') {
            throw new TomlError('unexpected unparsed part of numeric value');
        }

        $int = str_replace('0o', '0', $int);
        if (! $asString) {
            $int = intval($int, 0);
        }

        return [
            'int' => $int,
            'unparsed' => $unparsed,
            'sign' => $sign,
        ];
    }

    /**
     * @throws TomlError
     */
    public function digitalChecks($radix, $value): bool
    {
        return match ($radix) {
            16 => TomlUtils::isHexadecimal($value),
            10 => TomlUtils::isDecimal($value),
            8 => TomlUtils::isOctal($value),
            2 => TomlUtils::isBinary($value),
            default => throw new TomlError('unexpected radix value'),
        };
    }

    /**
     * @throws TomlError
     */
    public function float($value): FloatNode
    {
        $parsed = $this->parseInteger($value, true, true, true, 10);
        $float = $parsed['int'];
        $unparsed = $parsed['unparsed'];
        $sign = $parsed['sign'];

        if ($this->tokenizer->take('PERIOD')) {
            if (preg_match('/^[+-]?0\d+/im', (string) $value)) {
                throw new TomlError('unexpected float formatting');
            }

            if ($unparsed !== '') {
                throw new TomlError('unexpected unparsed part of float value');
            }

            $token = $this->tokenizer->expect('BARE');
            $result = $this->parseInteger($token->value, false, true, true, 10, true);
            if (! str_starts_with((string) $float, '+') && ! str_starts_with((string) $float, '-')) {
                $float = "$sign$float";
            }
            $float .= ".{$result['int']}";
            $unparsed = $result['unparsed'];
        }

        if ($unparsed === '') {
            return new FloatNode($float);
        }

        if (! str_starts_with((string) $unparsed, 'e') && ! str_starts_with((string) $unparsed, 'E')) {
            throw new TomlError('unexpected unparsed part of float value');
        }

        $float .= 'e';

        if (strlen((string) $unparsed) !== 1) {
            $float .= $this->parseInteger(substr((string) $unparsed, 1), true, true, false, 10)['int'];

            return new FloatNode((float) $float);
        }

        $this->tokenizer->assert('PLUS');
        $token = $this->tokenizer->expect('BARE');
        $float .= '+';
        $float .= $this->parseInteger($token->value, false, true, false, 10)['int'];

        return new FloatNode((float) $float);
    }

    /**
     * @throws TomlError
     */
    public function plus(): FloatNode|IntegerNode
    {
        $token = $this->tokenizer->expect('BARE');

        return $this->number("+$token->value");
    }

    /**
     * @throws TomlError
     */
    public function array(): ArrayNode
    {
        $arrayNode = new ArrayNode([]);

        for (; ;) {
            $this->takeCommentsAndNewlines();

            if ($this->tokenizer->peek()->type === 'RIGHT_SQUARE_BRACKET') {
                break;
            }

            $value = $this->value();
            $arrayNode->addElement($value);
            $this->takeCommentsAndNewlines();

            if (! $this->tokenizer->take('COMMA')) {
                $this->takeCommentsAndNewlines();

                break;
            }
        }

        $this->tokenizer->assert('RIGHT_SQUARE_BRACKET');

        return $arrayNode;
    }

    /**
     * @throws TomlError
     */
    public function inlineTable(): InlineTableNode
    {
        $this->tokenizer->take('WHITESPACE');
        $inlineTableNode = new InlineTableNode([]);

        if ($this->tokenizer->take('RIGHT_CURLY_BRACKET')) {
            return $inlineTableNode;
        }

        $keystore = new TomlKeystore;
        for (; ;) {
            $keyValue = $this->keyValuePair();
            $keystore->addNode($keyValue);
            $inlineTableNode->addElement($keyValue);
            $this->tokenizer->take('WHITESPACE');
            if ($this->tokenizer->take('RIGHT_CURLY_BRACKET')) {
                break;
            }
            $this->tokenizer->assert('COMMA');
        }

        return $inlineTableNode;
    }
}
