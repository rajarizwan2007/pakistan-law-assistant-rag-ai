<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Chunk extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'source_id',
        'chunk_index',
        'chapter',
        'section_ref',
        'heading',
        'content',
        'token_count',
        'embedding',
        'embedding_model',
    ];

    protected $hidden = ['embedding', 'content_tsv'];

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'token_count' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Text sent to the embedding model. Including the act, section and heading
     * gives the vector context that the body text alone may lack.
     */
    public function embeddingText(): string
    {
        $label = $this->source->cite($this->section_ref);
        $heading = $this->heading ? " — {$this->heading}" : '';

        return "{$label}{$heading}\n{$this->content}";
    }
}
