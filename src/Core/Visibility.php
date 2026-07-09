<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The 3-tier visibility scheme (public < private < admin) shared by
 * every part of the plugin that gates content by viewer level -
 * originally lived only inside FieldDefinition, now shared so
 * Documents (and anything else) doesn't duplicate the same rank
 * comparison.
 */
final class Visibility {

    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_ADMIN   = 'admin';

    private const RANK = [
        self::VISIBILITY_PUBLIC  => 0,
        self::VISIBILITY_PRIVATE => 1,
        self::VISIBILITY_ADMIN   => 2,
    ];

    /**
     * Whether a viewer cleared for $viewerLevel may see content whose
     * own visibility is $level - visible if $level's rank is no more
     * restrictive than the viewer's.
     */
    public static function isAtLeast( string $level, string $viewerLevel ): bool {
        $levelRank  = self::RANK[ $level ] ?? self::RANK[ self::VISIBILITY_ADMIN ];
        $viewerRank = self::RANK[ $viewerLevel ] ?? self::RANK[ self::VISIBILITY_PUBLIC ];

        return $levelRank <= $viewerRank;
    }
}
