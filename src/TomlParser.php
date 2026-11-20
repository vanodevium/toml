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
use Devium\Toml\Nodes\NumericNode;
use Devium\Toml\Nodes\OffsetDateTimeNode;
use Devium\Toml\Nodes\RootTableNode;
use Devium\Toml\Nodes\StringNode;
use Devium\Toml\Nodes\TableNode;
use Devium\Toml\Nodes\TomlDateTimeNode;
use Devium\Toml\Nodes\ValuableNode;
use Throwable;

/**
 * @internal
 */
final class TomlParser
{
    protected const MAX_DEPTH = 512;

    /** characters a numeric value may consist of */
    private const NUMERIC_CHARS = '+-.0123456789ABCDEFOX_abcdefox';

    /** allowed digits per radix; base 16 accepts any letter here, the hexadecimal format is checked separately */
    private const DIGITS = [
        2 => '01',
        8 => '01234567',
        10 => '0123456789',
        16 => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    ];

    protected int $depth = 0;

    protected TomlTokenizer $tokenizer;

    protected TomlKeystore $keystore;

    protected RootTableNode $rootTableNode;

    protected TableNode|RootTableNode $tableNode;

    /**
     * @throws TomlError
     */
    public function __construct(string $input, private readonly bool $asFloat = false)
    {
        if (! mb_check_encoding($input, 'UTF-8')) {
            throw new TomlError('invalid UTF-8 sequence');
        }

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
            while ($node = $this->expression()) {
                $this->endOfLine();
                $this->keystore->addNode($node);
                if ($node instanceof TableNode) {
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
    protected function expression(): KeyValuePairNode|TableNode|ArrayTableNode|null
    {
        $this->takeCommentsAndNewlines();

        return match ($this->tokenizer->peek()->type) {
            TomlToken::LEFT_SQUARE_BRACKET => $this->table(),
            TomlToken::EOF => null,
            default => $this->keyValuePair(),
        };
    }

    /**
     * Takes optional whitespace and comment, then a newline or EOF.
     *
     * @throws TomlError
     */
    protected function endOfLine(): void
    {
        $tokenizer = $this->tokenizer;
        $type = $tokenizer->peek()->type;
        if ($type === TomlToken::WHITESPACE) {
            $tokenizer->skip();
            $type = $tokenizer->peek()->type;
        }
        if ($type === TomlToken::COMMENT) {
            $tokenizer->skip();
            $type = $tokenizer->peek()->type;
        }
        if ($type !== TomlToken::NEWLINE && $type !== TomlToken::EOF) {
            throw new TomlError('unexpected assertion');
        }
        $tokenizer->skip();
    }

    /**
     * Skips empty lines, whitespace and comments; a comment is followed by a newline or EOF.
     *
     * @throws TomlError
     */
    protected function takeCommentsAndNewlines(): void
    {
        $tokenizer = $this->tokenizer;
        for (; ;) {
            $type = $tokenizer->peek()->type;
            if ($type === TomlToken::WHITESPACE) {
                $tokenizer->skip();
                $type = $tokenizer->peek()->type;
            }

            if ($type === TomlToken::COMMENT) {
                $tokenizer->skip();
                if ($tokenizer->isEOF()) {
                    break;
                }
                if ($tokenizer->peek()->type !== TomlToken::NEWLINE) {
                    throw new TomlError('unexpected assertion');
                }
                $tokenizer->skip();

                continue;
            }

            if ($type !== TomlToken::NEWLINE) {
                break;
            }
            $tokenizer->skip();
        }
    }

    /**
     * @throws TomlError
     */
    protected function table(): TableNode
    {
        $this->tokenizer->next();

        $isArrayTable = $this->tokenizer->take(TomlToken::LEFT_SQUARE_BRACKET);
        $key = $this->key();

        $this->tokenizer->assert(TomlToken::RIGHT_SQUARE_BRACKET);

        if ($isArrayTable) {
            $this->tokenizer->assert(TomlToken::RIGHT_SQUARE_BRACKET);
        }

        return $isArrayTable ? new ArrayTableNode($key, []) : new TableNode($key, []);
    }

    /**
     * @throws TomlError
     */
    protected function key(): KeyNode
    {
        $tokenizer = $this->tokenizer;
        $keyNode = new KeyNode([]);

        do {
            if ($tokenizer->peek()->type === TomlToken::WHITESPACE) {
                $tokenizer->skip();
            }
            $token = $tokenizer->next();

            switch ($token->type) {
                case TomlToken::BARE:
                    $keyNode->addKey(new BareNode($token->value));

                    break;

                case TomlToken::STRING:
                    if ($token->isMultiline) {
                        throw new TomlError('unexpected string value');
                    }

                    $keyNode->addKey(new StringNode($token->value));

                    break;

                default:
                    throw new TomlError('unexpected token type: '.$token->type);
            }

            $type = $tokenizer->peek()->type;
            if ($type === TomlToken::WHITESPACE) {
                $tokenizer->skip();
                $type = $tokenizer->peek()->type;
            }
            if ($type === TomlToken::PERIOD) {
                $tokenizer->skip();
            }
        } while ($type === TomlToken::PERIOD);

        return $keyNode;
    }

    /**
     * @throws TomlError
     */
    protected function keyValuePair(): KeyValuePairNode
    {
        $key = $this->key();

        $this->tokenizer->assert(TomlToken::EQUALS);
        $this->tokenizer->take(TomlToken::WHITESPACE);

        return new KeyValuePairNode($key, $this->value());
    }

    /**
     * @throws TomlError
     */
    protected function value(): ValuableNode
    {
        $token = $this->tokenizer->next();

        if ($token->type === TomlToken::LEFT_SQUARE_BRACKET || $token->type === TomlToken::LEFT_CURLY_BRACKET) {
            if (++$this->depth > self::MAX_DEPTH) {
                throw new TomlError('maximum nesting depth exceeded');
            }

            try {
                return $token->type === TomlToken::LEFT_SQUARE_BRACKET ? $this->array() : $this->inlineTable();
            } finally {
                $this->depth--;
            }
        }

        return match ($token->type) {
            TomlToken::STRING => new StringNode($token->value),
            TomlToken::BARE => $this->booleanOrNumberOrDateOrDateTimeOrTime($token->value),
            TomlToken::PLUS => $this->plus(),
            default => throw new TomlError('unexpected token type: '.$token->type),
        };
    }

    /**
     * @throws TomlError
     */
    protected function booleanOrNumberOrDateOrDateTimeOrTime(string $value): BooleanNode|NumericNode|TomlDateTimeNode
    {
        if ($value === 'true' || $value === 'false') {
            return new BooleanNode($value === 'true');
        }

        // a dash after the first character means a date, unless it is a negative exponent
        if (strpos($value, '-', 1) !== false && stripos($value, 'e-') === false) {
            return $this->dateOrDateTime($value);
        }

        if ($this->tokenizer->peek()->type === TomlToken::COLON) {
            return $this->time($value);
        }

        return $this->number($value);
    }

    /**
     * @throws TomlError
     */
    protected function dateOrDateTime(string $value): OffsetDateTimeNode|LocalDateTimeNode|LocalDateNode
    {
        $token = $this->tokenizer->peek();

        if ($token->type === TomlToken::WHITESPACE && $token->value === ' ') {
            $this->tokenizer->next();
            $token = $this->tokenizer->peek();

            if ($token->type !== TomlToken::BARE) {
                return new LocalDateNode(TomlLocalDate::fromString($value));
            }

            $this->tokenizer->next();
            $value .= 'T';
            $value .= $token->value;
        }

        if (stripos($value, 't') === false) {
            return new LocalDateNode(TomlLocalDate::fromString($value));
        }

        // hour is in $value already, read ":mm:ss..."
        $value .= $this->tokenizer->concat(TomlToken::COLON, TomlToken::BARE, TomlToken::COLON);
        $seconds = $this->tokenizer->expect(TomlToken::BARE)->value;
        $value .= $seconds;

        if (str_ends_with($seconds, 'z') || str_ends_with($seconds, 'Z')) {
            return new OffsetDateTimeNode($this->parseDate($value));
        }

        if (str_contains($seconds, '-')) {
            $this->tokenizer->assert(TomlToken::COLON);
            $token = $this->tokenizer->expect(TomlToken::BARE);
            $value .= ':';
            $value .= $token->value;

            return new OffsetDateTimeNode($this->parseDate($value));
        }

        $tokenType = $this->tokenizer->peek()->type;

        if ($tokenType === TomlToken::PLUS) {
            $this->tokenizer->next();
            $value .= '+';
            $value .= $this->tokenizer->concat(TomlToken::BARE, TomlToken::COLON, TomlToken::BARE);

            return new OffsetDateTimeNode($this->parseDate($value));
        }

        if ($tokenType === TomlToken::PERIOD) {
            $this->tokenizer->next();
            $token = $this->tokenizer->expect(TomlToken::BARE);
            $value .= '.';
            $value .= $token->value;

            if (str_ends_with($token->value, 'Z')) {
                return new OffsetDateTimeNode($this->parseDate($value));
            }

            if (str_contains($token->value, '-')) {
                $this->tokenizer->assert(TomlToken::COLON);
                $token = $this->tokenizer->expect(TomlToken::BARE);
                $value .= ':';
                $value .= $token->value;

                return new OffsetDateTimeNode($this->parseDate($value));
            }

            if ($this->tokenizer->take(TomlToken::PLUS)) {
                $value .= '+';
                $value .= $this->tokenizer->concat(TomlToken::BARE, TomlToken::COLON, TomlToken::BARE);

                return new OffsetDateTimeNode($this->parseDate($value));
            }
        }

        return new LocalDateTimeNode(TomlLocalDateTime::fromString($value));
    }

    /**
     * @throws TomlError
     */
    protected function parseDate(string $value): TomlDateTime
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
    protected function time(string $value): LocalTimeNode
    {
        $value .= $this->tokenizer->concat(TomlToken::COLON, TomlToken::BARE, TomlToken::COLON, TomlToken::BARE);
        if ($this->tokenizer->take(TomlToken::PERIOD)) {
            $token = $this->tokenizer->expect(TomlToken::BARE);
            $value .= '.';
            $value .= $token->value;
        }

        return new LocalTimeNode(TomlLocalTime::fromString($value));
    }

    /**
     * @throws TomlError
     */
    protected function number(string $value): IntegerNode|FloatNode
    {
        return match (true) {
            $value === 'inf', $value === '+inf' => new FloatNode(INF),
            $value === '-inf' => new FloatNode(-INF),
            $value === 'nan', $value === '+nan', $value === '-nan' => new FloatNode(NAN),
            str_starts_with($value, '0x') => $this->integer($value, 16),
            str_starts_with($value, '0o') => $this->integer($value, 8),
            str_starts_with($value, '0b') => $this->integer($value, 2),
            stripos($value, 'e') !== false => $this->float($value),
            $this->tokenizer->peek()->type === TomlToken::PERIOD => $this->float($value),
            default => $this->integer($value, 10),
        };
    }

    /**
     * @throws TomlError
     */
    protected function integer(string $value, int $radix): IntegerNode
    {
        $isDecimal = $radix === 10;

        return new IntegerNode($this->parseInteger($value, $isDecimal, ! $isDecimal, false, $radix)['int']);
    }

    /**
     * Reads the leading integer part of $value; whatever follows it is returned as "unparsed".
     *
     * @return array{int: int|string, unparsed: string, sign: string}
     *
     * @throws TomlError
     */
    protected function parseInteger(
        string $value,
        bool $isSignAllowed,
        bool $areLeadingZerosAllowed,
        bool $isUnparsedAllowed,
        int $radix,
        bool $asString = false,
    ): array {
        $length = strlen($value);

        if (strspn($value, self::NUMERIC_CHARS) !== $length) {
            throw new TomlError('unexpected non-numeric value');
        }

        $sign = '';
        $i = 0;
        if ($value[0] === '+' || $value[0] === '-') {
            if (! $isSignAllowed) {
                throw new TomlError('unexpected sign (+/-)');
            }
            if ($length === 1) {
                throw new TomlError('unexpected sign without digits');
            }
            $sign = $value[0];
            $i++;
        }

        if (! $areLeadingZerosAllowed && $value[$i] === '0' && $i + 1 !== $length) {
            throw new TomlError('unexpected leading zero');
        }

        // a radix prefix not followed by a digit
        if (strpbrk($value, 'obxOBX') !== false && preg_match('/0[obx](_|$)/i', $value)) {
            throw new TomlError('unexpected number formatting');
        }

        if (str_starts_with($value, '0x') && ! preg_match('/^0[xX][0-9a-fA-F_]+$/', $value)) {
            throw new TomlError('unexpected binary number formatting');
        }

        // the run of digits and underscores, including the "o"/"b" of a 0o/0b prefix
        $allowed = self::DIGITS[$radix].'_';
        $end = $i + strspn($value, $allowed, $i);
        $prefix = match ($radix) {
            8 => 'o',
            2 => 'b',
            default => '',
        };
        if ($end === 1 && $prefix !== '' && $length > 1 && $value[1] === $prefix) {
            $end = 2 + strspn($value, $allowed, 2);
        }

        // an underscore must sit between two digits
        $digits = substr($value, $i, $end - $i);
        if ($digits === '' || $digits[0] === '_' || $digits[-1] === '_' || str_contains($digits, '__')) {
            throw new TomlError('unexpected underscore symbol');
        }
        $i = $end;

        $unparsed = substr($value, $i);
        if (! $isUnparsedAllowed && $unparsed !== '') {
            throw new TomlError('unexpected unparsed part of numeric value');
        }

        $int = str_replace(['_', '0o'], ['', '0'], substr($value, 0, $i));

        return [
            'int' => $asString ? $int : intval($int, 0),
            'unparsed' => $unparsed,
            'sign' => $sign,
        ];
    }

    /**
     * @throws TomlError
     */
    protected function float(string $value): FloatNode
    {
        $parsed = $this->parseInteger($value, true, true, true, 10);
        $float = $parsed['int'];
        $unparsed = $parsed['unparsed'];
        $sign = $parsed['sign'];

        if ($this->tokenizer->take(TomlToken::PERIOD)) {
            if (preg_match('/^[+-]?0\d/', $value)) {
                throw new TomlError('unexpected float formatting');
            }

            if ($unparsed !== '') {
                throw new TomlError('unexpected unparsed part of float value');
            }

            $token = $this->tokenizer->expect(TomlToken::BARE);
            $result = $this->parseInteger($token->value, false, true, true, 10, true);
            if (! str_starts_with((string) $float, '+') && ! str_starts_with((string) $float, '-')) {
                $float = "$sign$float";
            }
            $float .= ".{$result['int']}";
            $unparsed = $result['unparsed'];
        }

        if ($unparsed === '') {
            return new FloatNode($this->asFloat ? (float) $float : $float);
        }

        if ($unparsed[0] !== 'e' && $unparsed[0] !== 'E') {
            throw new TomlError('unexpected unparsed part of float value');
        }

        $float .= 'e';

        if ($unparsed !== 'e' && $unparsed !== 'E') {
            $float .= $this->parseInteger(substr($unparsed, 1), true, true, false, 10)['int'];

            return new FloatNode((float) $float);
        }

        $this->tokenizer->assert(TomlToken::PLUS);
        $token = $this->tokenizer->expect(TomlToken::BARE);
        $float .= '+';
        $float .= $this->parseInteger($token->value, false, true, false, 10)['int'];

        return new FloatNode((float) $float);
    }

    /**
     * @throws TomlError
     */
    protected function plus(): FloatNode|IntegerNode
    {
        $token = $this->tokenizer->expect(TomlToken::BARE);

        return $this->number("+$token->value");
    }

    /**
     * @throws TomlError
     */
    protected function array(): ArrayNode
    {
        $arrayNode = new ArrayNode([]);

        for (; ;) {
            $this->takeCommentsAndNewlines();

            if ($this->tokenizer->peek()->type === TomlToken::RIGHT_SQUARE_BRACKET) {
                break;
            }

            $arrayNode->addElement($this->value());
            $this->takeCommentsAndNewlines();

            if (! $this->tokenizer->take(TomlToken::COMMA)) {
                $this->takeCommentsAndNewlines();

                break;
            }
        }

        $this->tokenizer->assert(TomlToken::RIGHT_SQUARE_BRACKET);

        return $arrayNode;
    }

    /**
     * @throws TomlError
     */
    protected function inlineTable(): InlineTableNode
    {
        $this->tokenizer->take(TomlToken::WHITESPACE);
        $inlineTableNode = new InlineTableNode([]);

        if ($this->tokenizer->take(TomlToken::RIGHT_CURLY_BRACKET)) {
            return $inlineTableNode;
        }

        $keystore = new TomlKeystore;
        for (; ;) {
            $keyValue = $this->keyValuePair();
            $keystore->addNode($keyValue);
            $inlineTableNode->addElement($keyValue);
            $this->tokenizer->take(TomlToken::WHITESPACE);
            if ($this->tokenizer->take(TomlToken::RIGHT_CURLY_BRACKET)) {
                break;
            }
            $this->tokenizer->assert(TomlToken::COMMA);
        }

        return $inlineTableNode;
    }
}
