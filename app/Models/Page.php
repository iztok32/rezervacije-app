<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Vsebinska stran (O nas, cenik, splošni pogoji …); besedilo v Markdownu. */
class Page extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** Varen HTML: surov HTML in nevarne povezave (javascript:) se odstranijo. */
    public function html(): string
    {
        return Str::markdown((string) $this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function url(): string
    {
        return route('pages.show', $this->slug);
    }
}
