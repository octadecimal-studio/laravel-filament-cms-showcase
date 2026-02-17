<?php

declare(strict_types=1);

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

/**
 * JSON scalar type for GraphQL.
 *
 * Handles JSON data serialization and parsing.
 */
final class JSON extends ScalarType
{
    /**
     * Serializes an internal value to include in a response.
     */
    public function serialize(mixed $value): mixed
    {
        // JSON values are already serialized in PHP (arrays/objects)
        return $value;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     */
    public function parseValue(mixed $value): mixed
    {
        // If it's already an array/object, return as is
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        // If it's a string, try to decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Error('Invalid JSON: '.json_last_error_msg());
            }

            return $decoded;
        }

        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     *
     * @param  \GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node  $valueNode
     * @param  array<string, mixed>|null  $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        if (! ($valueNode instanceof StringValueNode)) {
            throw new Error('JSON cannot represent non-string value: '.Utils::printSafe($valueNode), $valueNode);
        }

        $decoded = json_decode($valueNode->value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Error('Invalid JSON: '.json_last_error_msg(), $valueNode);
        }

        return $decoded;
    }
}
