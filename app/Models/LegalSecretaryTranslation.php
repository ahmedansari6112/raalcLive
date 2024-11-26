<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSecretaryTranslation extends Model
{
    use HasFactory;
    
    protected $table = 'legal_secretaries_translations';
    
    protected $fillable = ['legal_secretary_id', 'lang', 'fields_value'];
}
