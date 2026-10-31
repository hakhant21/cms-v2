<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One file in the media library. Pages point here (featured image, gallery,
 * blocks) so a file is stored once and its alt text edited in one place.
 */
class Image extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'alt',
        'mime',
        'width',
        'height',
        'size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * A name that is not valid UTF-8 cannot be JSON encoded, and every admin
     * screen serialises the record into a Livewire snapshot.
     */
    public function setOriginalNameAttribute(?string $value): void
    {
        $this->attributes['original_name'] = Text::utf8($value);
    }

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if ($image->isDirty(['path', 'disk']) || $image->original_name === null) {
                $image->readFileMetadata();
            }
        });
    }

    /**
     * Fill size and mime from the uploaded file so the media library can show
     * real information without the editor typing any of it.
     */
    public function readFileMetadata(): void
    {
        if (! $this->path) {
            return;
        }

        // Imported media keeps its original absolute URL (the legacy site
        // hosted its images on a CDN), so there is no local file to inspect.
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            $this->original_name ??= Text::fileName($this->path);

            return;
        }

        $storage = Storage::disk($this->disk ?: 'public');

        if (! $storage->exists($this->path)) {
            return;
        }

        $this->original_name ??= Text::fileName($this->path);
        $this->size = $storage->size($this->path);
        $this->mime = $storage->mimeType($this->path);

        if ($this->mime === null || ! str_starts_with($this->mime, 'image/')) {
            return;
        }

        $dimensions = @getimagesizefromstring((string) $storage->get($this->path));

        if ($dimensions === false) {
            return;
        }

        [$this->width, $this->height] = $dimensions;
    }

    public function url(): string
    {
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://') || str_starts_with($this->path, '/')) {
            return $this->path;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }

    /** Alt text, falling back to the uploaded file name so it is never empty. */
    public function altText(): string
    {
        return $this->alt ?: (string) ($this->original_name ?? '');
    }

    public function isPortrait(): bool
    {
        return $this->height !== null && $this->width !== null && $this->height > $this->width;
    }
}
