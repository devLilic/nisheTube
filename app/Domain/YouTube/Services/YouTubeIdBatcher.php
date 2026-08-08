<?php

namespace App\Domain\YouTube\Services;

use InvalidArgumentException;

class YouTubeIdBatcher
{
    /**
     * @param  list<string>  $ids
     * @return list<list<string>>
     */
    public function batches(array $ids, int $batchSize = 50): array
    {
        if ($batchSize < 1 || $batchSize > 50) {
            throw new InvalidArgumentException('YouTube batch size must be between 1 and 50.');
        }

        $unique = [];

        foreach ($ids as $id) {
            $id = trim($id);

            if ($id === '') {
                throw new InvalidArgumentException('YouTube identifiers cannot be blank.');
            }

            $unique[$id] = $id;
        }

        return array_chunk(array_values($unique), $batchSize);
    }
}
