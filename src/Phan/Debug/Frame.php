<?php declare(strict_types=1);

namespace Phan\Debug;

use Phan\IssueInstance;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;

/**
 * Debug utilities for working with frames of debug_backtrace()
 *
 * Mostly utilities for better crash reports.
 */
class Frame
{
    /**
     * Utilities to encode values might be seen in Phan or its plugins in a crash.
     * @param mixed $value
     * @suppress PhanTypeSuspiciousStringExpression
     */
    public static function encodeValue($value, int $max_depth = 2) : string
    {
        if (is_object($value)) {
            if ($value instanceof IssueInstance) {
                return "IssueInstance($value)";
            }
            if ($value instanceof FQSEN) {
                return get_class($value) . '(' . $value . ')';
            }
            if ($value instanceof \Closure) {
                return 'Closure';
            }
            if ($value instanceof AddressableElement
                || $value instanceof UnaddressableTypedElement
                || $value instanceof UnionType
                || $value instanceof Context
                || $value instanceof Type) {
                return get_class($value) . '(' . $value . ')';
            }
            return get_class($value) . '(' . StringUtil::jsonEncode($value) . ')';
        }
        if (!is_array($value)) {
            if (is_resource($value)) {
                ob_start();
                var_dump($value);
                return trim(ob_get_clean() ?: 'resource');
            }
            return StringUtil::jsonEncode($value);
        }
        if ($max_depth <= 0) {
            return count($value) > 0 ? '[...]' : '[]';
        }
        $is_consecutive = true;
        $i = 0;
        foreach ($value as $key => $_) {
            if ($key !== $i) {
                $is_consecutive = false;
                break;
            }
            $i++;
        }

        if ($is_consecutive) {
            $result = [];
            foreach ($value as $i => $inner_value) {
                if ($i >= 10) {
                    $result[] = '... ' . (count($value) - 10) . ' more element(s)';
                    break;
                }
                $result[] = self::encodeValue($inner_value);
            }
            return '[' . implode(', ', $result) . ']';
        }
        $result = [];
        $i = 0;
        foreach ($value as $key => $inner_value) {
            $i++;
            if ($i > 10) {
                $result[] = '... ' . (count($value) - 10) . ' more field(s)';
                break;
            }
            $result[] = StringUtil::jsonEncode($key) . ':' . self::encodeValue($inner_value);
        }
        return '{' . implode(', ', $result) . '}';
    }

    /**
     * Utility to show more information about an unexpected error
     */
    public static function frameToString(array $frame) : string
    {
        return with_disabled_phan_error_handler(function () use ($frame) : string {
            $invocation = $frame['function'] ?? '(unknown)';
            if (isset($frame['class'])) {
                $invocation = $frame['class'] . ($frame['type'] ?? '::') . $invocation;
            }
            $args = $frame['args'];
            return $invocation . '(). Args: ' . self::encodeValue($args);
        });
    }

    /**
     * Returns details about a call to asExpandedTypes that hit a RecursionDepthException
     */
    public static function getExpandedTypesDetails() : string
    {
        $result = [];
        foreach (debug_backtrace() as $frame) {
            if (($frame['function'] ?? null) === 'asExpandedTypes' && isset($frame['object'])) {
                $object = $frame['object'];
                if ($object instanceof Type) {
                    $result[] = 'when expanding type (' . (string)$object . ')';
                } elseif ($object instanceof UnionType) {
                    $result[] = 'when expanding union type (' . (string)$object . ')';
                }
            }
        }
        return implode("\n", $result);
    }
}
