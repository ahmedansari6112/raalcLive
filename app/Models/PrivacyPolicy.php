<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyPolicy extends Model
{
    use HasFactory;

    protected $table = "privacy_policy";

    protected $fillable = [
        "slug",
        "translated_value",
        "heading",
        "description",
        "language",
        "platform",
        "created_by"
    ];
}
