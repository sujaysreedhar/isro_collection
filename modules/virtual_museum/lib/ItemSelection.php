<?php

final class VirtualMuseumItemSelection
{
    /**
     * @param array<int,array<string,mixed>> $candidates
     * @param array<int,array{selection_state?:string,sort_order?:int|null}> $overrides keyed by item id
     * @return array<int,array<string,mixed>>
     */
    public static function resolve(array $candidates, array $overrides, int $limit = 100): array
    {
        $deduplicated = [];
        foreach ($candidates as $candidate) {
            $id = (int) ($candidate['id'] ?? 0);
            if ($id <= 0 || (($overrides[$id]['selection_state'] ?? 'include') === 'exclude')) {
                continue;
            }

            $candidate['id'] = $id;
            $candidate['source_order'] = (int) ($candidate['source_order'] ?? PHP_INT_MAX);
            $candidate['native_order'] = (int) ($candidate['native_order'] ?? PHP_INT_MAX);
            $candidate['explicit_order'] = isset($overrides[$id]['sort_order'])
                ? (int) $overrides[$id]['sort_order']
                : null;

            if (!isset($deduplicated[$id]) || self::comesBefore($candidate, $deduplicated[$id])) {
                $deduplicated[$id] = $candidate;
            }
        }

        $rows = array_values($deduplicated);
        usort($rows, static function (array $a, array $b): int {
            $aExplicit = $a['explicit_order'] !== null;
            $bExplicit = $b['explicit_order'] !== null;
            if ($aExplicit !== $bExplicit) {
                return $aExplicit ? -1 : 1;
            }
            if ($aExplicit && $a['explicit_order'] !== $b['explicit_order']) {
                return $a['explicit_order'] <=> $b['explicit_order'];
            }
            return [$a['source_order'], $a['native_order'], $a['id']]
                <=> [$b['source_order'], $b['native_order'], $b['id']];
        });

        return array_slice($rows, 0, max(1, min(100, $limit)));
    }

    private static function comesBefore(array $candidate, array $existing): bool
    {
        return [$candidate['source_order'], $candidate['native_order'], $candidate['id']]
            < [$existing['source_order'], $existing['native_order'], $existing['id']];
    }
}
