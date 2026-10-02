<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'title',
        'short_name',
        'unit',
        'year',
        'source_url',
        'file_name',
        'checksum',
        'retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'retrieved_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * Human-readable reference to one provision, e.g. "PPC s.379" or "Constitution Art.25".
     */
    public function cite(?string $sectionRef): string
    {
        $name = $this->short_name ?? $this->title;

        if ($sectionRef === null) {
            return $name;
        }

        $prefix = $this->unit === 'article' ? 'Art.' : 's.';

        return "{$name} {$prefix}{$sectionRef}";
    }
}
