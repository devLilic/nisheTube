<?php

namespace App\Domain\Exports\Data;

final readonly class ExportSelectionInput
{
    /**
     * @param  list<string>  $researchRunIds
     * @param  list<string>  $videoIds
     * @param  list<string>|null  $columns
     */
    public function __construct(
        public string $sourceType,
        public array $researchRunIds,
        public ?string $sourceId,
        public array $videoIds,
        public bool $includeTechnicalDetails,
        public bool $confirmed,
        public ?array $columns = null,
    ) {}
}
