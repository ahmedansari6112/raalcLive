<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContainTranslation extends Model
{
    use HasFactory;
    
    protected $table = 'web_content_translations';

    protected $fillable = [
        "translated_value",
        "language",
        "web_content_id",
    ];

    public function webContent()
    {
        return $this->belongsTo(WebContent::class);
    }
}
