<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A message submitted through the public contact form. */
class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Mirrors the column defaults so a fresh instance already knows its state,
     * which keeps status checks correct before the first reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => self::STATUS_NEW];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'locale',
        'status',
        'read_at',
        'replied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_READ => 'Read',
            self::STATUS_REPLIED => 'Replied',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    /** Move the message forward only; never overwrite a later state. */
    public function markRead(): void
    {
        if ($this->status !== self::STATUS_NEW) {
            return;
        }

        $this->forceFill(['status' => self::STATUS_READ, 'read_at' => now()])->save();
    }

    public function markReplied(): void
    {
        $this->forceFill(['status' => self::STATUS_REPLIED, 'replied_at' => now()])->save();
    }
}
