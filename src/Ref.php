<?php

namespace Qstom\Minimax;

/**
 * A reference to another Minimax record (mMApiFkField). Only ID is written;
 * Name and ResourceUrl are read-only.
 */
final class Ref
{
    /**
     * @return array{ID: int}
     */
    public static function to(int $id): array
    {
        return ['ID' => $id];
    }

    /**
     * The ID of a reference read from Minimax, null when it is empty.
     */
    public static function id(mixed $reference): ?int
    {
        $id = is_array($reference) ? ($reference['ID'] ?? null) : null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
