<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Support;

/**
 * Minimal stand-in for $wpdb, supporting exactly the query shapes this
 * plugin's repositories actually issue: WHERE with =, >=, <=, LIKE,
 * combined with AND; ORDER BY + LIMIT/OFFSET; and the INSERT ... ON
 * DUPLICATE KEY UPDATE upsert used by the field-values EAV table. Not a
 * SQL engine - a pattern-matched simulation, same approach used in
 * every smoke script since Sprint 9.
 */
final class FakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    /** @var array<string, int> */
    private array $autos = [];

    public function get_charset_collate(): string
    {
        return '';
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    public function prepare(string $query, mixed ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_string($arg) ? "'" . addslashes($arg) . "'" : (string) $arg;
            $query = preg_replace('/%[ds]/', $replacement, $query, 1);
        }

        return $query;
    }

    public function get_var(string $query): mixed
    {
        $table = $this->tableFromQuery($query);
        $rows = $this->applyConditions($this->rowsFor($table), $this->parseConditions($query));

        if (preg_match('/^\s*SELECT\s+COUNT\(\*\)/i', $query)) {
            return (string) count($rows);
        }

        if (preg_match('/^\s*SELECT\s+(\w+)\s+FROM/i', $query, $m)) {
            return $rows === [] ? null : ($rows[0][$m[1]] ?? null);
        }

        return (string) count($rows);
    }

    public function get_row(string $query, string $output = 'ARRAY_A'): ?array
    {
        $table = $this->tableFromQuery($query);
        $rows = $this->applyConditions($this->rowsFor($table), $this->parseConditions($query));

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_results(string $query, string $output = 'ARRAY_A'): array
    {
        $table = $this->tableFromQuery($query);
        $rows = $this->applyConditions($this->rowsFor($table), $this->parseConditions($query));

        $sortKey = 'id';
        $desc = true;

        if (preg_match('/ORDER BY (\w+)(?:, \w+)? (ASC|DESC)/', $query, $m)) {
            $sortKey = $m[1];
            $desc = strtoupper($m[2]) === 'DESC';
        }

        usort($rows, static fn (array $a, array $b): int => $desc
            ? strcmp((string) ($b[$sortKey] ?? ''), (string) ($a[$sortKey] ?? ''))
            : strcmp((string) ($a[$sortKey] ?? ''), (string) ($b[$sortKey] ?? '')));

        if (preg_match('/LIMIT (\d+) OFFSET (\d+)/', $query, $m)) {
            $rows = array_slice($rows, (int) $m[2], (int) $m[1]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data, mixed $format = null): bool
    {
        $this->autos[$table] = ($this->autos[$table] ?? 0) + 1;
        $id = $this->autos[$table];
        $data['id'] = $id;
        $this->tables[$table][$id] = $data;
        $this->insert_id = $id;

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where, mixed $format = null, mixed $whereFormat = null): bool
    {
        foreach ($this->tables[$table] ?? [] as $rowId => $row) {
            $matches = true;

            foreach ($where as $column => $value) {
                if ((string) ($row[$column] ?? null) !== (string) $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $this->tables[$table][$rowId] = array_merge($row, $data);
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where, mixed $format = null): bool
    {
        foreach ($this->tables[$table] ?? [] as $rowId => $row) {
            $matches = true;

            foreach ($where as $column => $value) {
                if ((string) ($row[$column] ?? null) !== (string) $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                unset($this->tables[$table][$rowId]);
            }
        }

        return true;
    }

    public function query(string $sql): bool
    {
        if (stripos($sql, 'INSERT INTO') === false || stripos($sql, 'ON DUPLICATE KEY UPDATE') === false) {
            return false;
        }

        preg_match('/INSERT INTO (\S+)/i', $sql, $tableMatch);
        $table = $tableMatch[1];

        preg_match('/VALUES\s*\((.*?)\)\s*ON DUPLICATE/is', $sql, $valuesMatch);
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'|(-?[0-9]+)/", $valuesMatch[1], $tokens, PREG_SET_ORDER);

        $values = [];
        foreach ($tokens as $token) {
            $values[] = ($token[1] ?? '') !== '' ? $token[1] : $token[2];
        }

        [$entityType, $entityId, $fieldKey, $value, $createdAt, $updatedAt] = $values;

        $existingId = null;
        foreach ($this->tables[$table] ?? [] as $rowId => $row) {
            if (
                $row['entity_type'] === $entityType
                && (string) $row['entity_id'] === (string) $entityId
                && $row['field_key'] === $fieldKey
            ) {
                $existingId = $rowId;
                break;
            }
        }

        if ($existingId !== null) {
            $this->tables[$table][$existingId]['value'] = $value;
            $this->tables[$table][$existingId]['updated_at'] = $updatedAt;
        } else {
            $this->autos[$table] = ($this->autos[$table] ?? 0) + 1;
            $id = $this->autos[$table];
            $this->tables[$table][$id] = [
                'id' => $id,
                'entity_type' => $entityType,
                'entity_id' => (int) $entityId,
                'field_key' => $fieldKey,
                'value' => $value,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        return true;
    }

    private function tableFromQuery(string $sql): string
    {
        if (preg_match('/FROM\s+(\S+)/', $sql, $m)) {
            return $m[1];
        }

        return 'unknown';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(string $table): array
    {
        return array_values($this->tables[$table] ?? []);
    }

    /**
     * @return array<int, array{col: string, op: string, val: string}>
     */
    private function parseConditions(string $sql): array
    {
        if (!preg_match('/WHERE\s+(.*?)(\s+ORDER BY|\s+LIMIT|$)/is', $sql, $m)) {
            return [];
        }

        $whereClause = trim($m[1]);

        if ($whereClause === '') {
            return [];
        }

        $conditions = [];

        foreach (preg_split('/\s+AND\s+/i', $whereClause) as $part) {
            if (preg_match("/(\w+)\s+LIKE\s+'([^']*)'/i", $part, $cm)) {
                $conditions[] = ['col' => $cm[1], 'op' => 'LIKE', 'val' => trim($cm[2], '%')];
            } elseif (preg_match("/(\w+)\s*(=|>=|<=|<|>)\s*'([^']*)'/", $part, $cm)) {
                $conditions[] = ['col' => $cm[1], 'op' => $cm[2], 'val' => $cm[3]];
            } elseif (preg_match("/(\w+)\s*(=|>=|<=|<|>)\s*(\d+)/", $part, $cm)) {
                $conditions[] = ['col' => $cm[1], 'op' => $cm[2], 'val' => $cm[3]];
            }
        }

        return $conditions;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{col: string, op: string, val: string}> $conditions
     * @return array<int, array<string, mixed>>
     */
    private function applyConditions(array $rows, array $conditions): array
    {
        foreach ($conditions as $condition) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($condition): bool {
                $value = $row[$condition['col']] ?? null;

                return match ($condition['op']) {
                    '=' => (string) $value === (string) $condition['val'],
                    '>=' => strcmp((string) $value, (string) $condition['val']) >= 0,
                    '<=' => strcmp((string) $value, (string) $condition['val']) <= 0,
                    '<' => strcmp((string) $value, (string) $condition['val']) < 0,
                    '>' => strcmp((string) $value, (string) $condition['val']) > 0,
                    'LIKE' => str_contains((string) $value, (string) $condition['val']),
                    default => true,
                };
            }));
        }

        return $rows;
    }
}
