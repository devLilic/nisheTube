<?php

namespace App\Models;

use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property ExportFormat $format
 * @property ExportStatus $status
 * @property array<string, mixed> $selection
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $size_bytes
 * @property string|null $checksum_sha256
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $failed_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'format',
    'status',
    'selection',
    'disk',
    'path',
    'size_bytes',
    'checksum_sha256',
    'started_at',
    'completed_at',
    'expires_at',
    'failed_at',
    'error_code',
    'error_message',
])]
class ResearchExport extends Model
{
    use HasPublicId;

    protected $table = 'exports';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'selection' => 'array',
            'size_bytes' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
