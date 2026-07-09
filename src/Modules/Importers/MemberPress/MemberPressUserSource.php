<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\MemberPress;

use AssociationManager\Modules\Importers\Domain\ImportRow;
use AssociationManager\Modules\Importers\ImportSourceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Reads directly from wp_users/wp_usermeta via get_users()/get_user_meta()
 * - no MemberPress plugin classes, hooks, or Composer package involved,
 * so Association Manager never depends on MemberPress being installed or
 * active, only on its data already existing in those two WordPress-core
 * tables (which is true whether MemberPress is currently active,
 * deactivated, or was only ever used to seed data on a previous site).
 *
 * A WP user only becomes an ImportRow if they have at least one usermeta
 * key starting with $metaKeyPrefix - otherwise every ordinary WP user
 * (subscribers, admins with no MemberPress relationship) would import as
 * an Association Manager member, which is not what "MemberPress
 * Importer" means. The prefix defaults to "mepr" (matches both MemberPress'
 * own internal "mepr_..." keys and the "mepr-..." slugs its Custom Fields
 * UI generates) and is constructor-configurable in case a real
 * installation's naming differs.
 */
final class MemberPressUserSource implements ImportSourceInterface {

    public function __construct(
        private readonly string $metaKeyPrefix = 'mepr'
    ) {
    }

    public function key(): string {
        return 'memberpress';
    }

    public function label(): string {
        return 'MemberPress';
    }

    /**
     * @return ImportRow[]
     */
    public function fetchRows(): array {
        $users = get_users( [ 'fields' => [ 'ID', 'user_email' ] ] );

        $rows = [];

        foreach ( $users as $user ) {
            $rawFields = $this->memberPressFieldsFor( (int) $user->ID );

            if ( $rawFields === [] ) {
                continue;
            }

            $rows[] = new ImportRow(
                sourceUserId: (int) $user->ID,
                wpUserId: (int) $user->ID,
                email: $user->user_email !== '' ? $user->user_email : null,
                rawFields: $rawFields,
            );
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPressFieldsFor( int $wpUserId ): array {
        $allMeta = get_user_meta( $wpUserId );

        if ( ! is_array( $allMeta ) ) {
            return [];
        }

        $fields = [];

        foreach ( $allMeta as $metaKey => $values ) {
            if ( ! str_starts_with( $metaKey, $this->metaKeyPrefix ) ) {
                continue;
            }

            // get_user_meta() without a $key always returns each value as
            // an array (WordPress allows multiple rows per meta_key);
            // MemberPress custom fields are single-value, so the first
            // entry is the one that matters.
            $fields[ $metaKey ] = $values[0] ?? null;
        }

        return $fields;
    }
}
