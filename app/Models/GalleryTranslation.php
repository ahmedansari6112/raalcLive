<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryTranslation extends Model
{
    use HasFactory;

    protected $table = 'gallery_translation';

    protected $fillable = [
        "field_values",
        "language",
        "gallery_id"
    ];
}
