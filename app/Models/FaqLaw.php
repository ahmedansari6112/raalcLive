<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqLaw extends Model
{
    use HasFactory;

    protected $table = 'faq_laws';

    protected $fillable = [
        'field_values',
        'language',
        'type'
    ];
}
