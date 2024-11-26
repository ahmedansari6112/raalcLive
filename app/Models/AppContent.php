<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppContent extends Model
{
    use HasFactory;

    protected $table = 'app_contents';

    protected $fillable = [
        "slug",
        "field_values"
    ];
}
