<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTranslation extends Model
{
    use HasFactory;

    protected $table = 'event_translation';

    protected $fillable = [
        "field_values",
        "language",
        "event_id"
    ];
}
