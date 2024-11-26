<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewTranslation extends Model
{
    use HasFactory;

    protected $table = 'review_translations';

    protected $fillable = [
        'field_values',
        'lang',
        'review_id'
    ];
}
