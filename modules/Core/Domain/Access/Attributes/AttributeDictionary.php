<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Attributes;

use InvalidArgumentException;

/**
 * Standard ABAC Attribute Dictionary & Schema Registry (W8-D53, W8-D54).
 *
 * Defines canonical attribute keys, data types, value constraints, and descriptions
 * for Subjects, Resources, Actions, and Environmental Contexts across the platform.
 */
final class AttributeDictionary
{
    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_ARRAY = 'array';

    public const TYPE_FLOAT = 'float';

    /**
     * Canonical registry of known attributes.
     *
     * @var array<string, array{type: string, category: string, description: string, allowed_values?: list<string>}>
     */
    private static array $definitions = [
        // Subject attributes
        'subject.tenant_id' => [
            'type' => self::TYPE_STRING,
            'category' => 'subject',
            'description' => 'UUID of the active tenant belonging to the subject',
        ],
        'subject.roles' => [
            'type' => self::TYPE_ARRAY,
            'category' => 'subject',
            'description' => 'List of role identifiers assigned to the subject',
        ],
        'subject.clearance_level' => [
            'type' => self::TYPE_INTEGER,
            'category' => 'subject',
            'description' => 'Security clearance level ranging from 1 (Public) to 5 (Top Secret)',
        ],
        'subject.org_unit_id' => [
            'type' => self::TYPE_STRING,
            'category' => 'subject',
            'description' => 'Department or business unit code',
        ],
        'subject.is_mfa_verified' => [
            'type' => self::TYPE_BOOLEAN,
            'category' => 'subject',
            'description' => 'True if multi-factor authentication was satisfied during session creation',
        ],
        'subject.is_break_glass' => [
            'type' => self::TYPE_BOOLEAN,
            'category' => 'subject',
            'description' => 'True if active session is elevated via emergency break-glass procedure',
        ],
        'subject.device_trust_score' => [
            'type' => self::TYPE_INTEGER,
            'category' => 'subject',
            'description' => 'Managed device trust score from 0 (untrusted) to 100 (fully compliant corporate endpoint)',
        ],

        // Resource attributes
        'resource.type' => [
            'type' => self::TYPE_STRING,
            'category' => 'resource',
            'description' => 'Machine type of the target resource',
        ],
        'resource.owner_tenant_id' => [
            'type' => self::TYPE_STRING,
            'category' => 'resource',
            'description' => 'UUID of the tenant that owns the resource',
        ],
        'resource.classification' => [
            'type' => self::TYPE_STRING,
            'category' => 'resource',
            'description' => 'Data classification sensitivity tier',
            'allowed_values' => ['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED', 'HIGHLY_SENSITIVE'],
        ],
        'resource.required_clearance' => [
            'type' => self::TYPE_INTEGER,
            'category' => 'resource',
            'description' => 'Minimum security clearance required to operate on resource',
        ],
        'resource.contains_pii' => [
            'type' => self::TYPE_BOOLEAN,
            'category' => 'resource',
            'description' => 'Indicates whether resource contains personally identifiable data fields',
        ],
        'resource.is_financial_tranche' => [
            'type' => self::TYPE_BOOLEAN,
            'category' => 'resource',
            'description' => 'Indicates whether resource represents a financial tranche > Rp 500M',
        ],

        // Action attributes
        'action.code' => [
            'type' => self::TYPE_STRING,
            'category' => 'action',
            'description' => 'Distinct operational verb or permission key',
        ],
        'action.category' => [
            'type' => self::TYPE_STRING,
            'category' => 'action',
            'description' => 'Broad functional category of the action',
            'allowed_values' => ['READ', 'WRITE', 'DELETE', 'ADMIN', 'EXPORT', 'IMPERSONATE', 'BREAK_GLASS'],
        ],
        'action.risk_tier' => [
            'type' => self::TYPE_STRING,
            'category' => 'action',
            'description' => 'Operational risk tier of the action',
            'allowed_values' => ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'],
        ],

        // Environment attributes
        'environment.security_zone' => [
            'type' => self::TYPE_STRING,
            'category' => 'environment',
            'description' => 'Originating network perimeter segment',
            'allowed_values' => ['INTERNAL_LAN', 'DMZ', 'PUBLIC_INTERNET', 'SECURE_BASTION'],
        ],
        'environment.is_business_hours' => [
            'type' => self::TYPE_BOOLEAN,
            'category' => 'environment',
            'description' => 'True if request occurs within 08:00 - 18:00 WIB Monday through Friday',
        ],
        'environment.threat_level' => [
            'type' => self::TYPE_STRING,
            'category' => 'environment',
            'description' => 'Current platform-wide cybersecurity threat posture',
            'allowed_values' => ['GREEN', 'YELLOW', 'ORANGE', 'RED'],
        ],
    ];

    /**
     * Retrieve all registered attribute definitions.
     *
     * @return array<string, array{type: string, category: string, description: string, allowed_values?: list<string>}>
     */
    public static function all(): array
    {
        return self::$definitions;
    }

    /**
     * Check if an attribute key exists in dictionary.
     */
    public static function has(string $key): bool
    {
        return isset(self::$definitions[$key]);
    }

    /**
     * Validate a value against its attribute schema definition.
     *
     * @throws InvalidArgumentException If value fails type or constraint check
     */
    public static function validate(string $key, mixed $value): void
    {
        if (! self::has($key)) {
            return; // Permissive for custom extension attributes
        }

        $def = self::$definitions[$key];
        $type = $def['type'];

        $validType = match ($type) {
            self::TYPE_STRING => is_string($value),
            self::TYPE_INTEGER => is_int($value),
            self::TYPE_BOOLEAN => is_bool($value),
            self::TYPE_ARRAY => is_array($value),
            self::TYPE_FLOAT => is_float($value) || is_int($value),
            default => true,
        };

        if (! $validType) {
            throw new InvalidArgumentException(
                "Attribute [{$key}] expected type {$type}, got ".gettype($value)
            );
        }

        if (isset($def['allowed_values']) && is_string($value)) {
            if (! in_array($value, $def['allowed_values'], true)) {
                throw new InvalidArgumentException(
                    "Attribute [{$key}] value '{$value}' is not in allowed values: ".implode(', ', $def['allowed_values'])
                );
            }
        }
    }
}
