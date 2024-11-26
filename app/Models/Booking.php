<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;
    
    protected $table = 'bookings';

    protected $fillable = [
        "client_id",
        "client_name",
        "client_email",
        "client_phone",
        "meeting_date",
        "time_slot",
        "beverage",
        "number_of_attendees",
        "meeting_shift",
        "meeting_place",
        "booking_status",
        "consultant_id",
        "meeting_purpose",
        "language",
        "description"
    ];
    
    public function notifications()
    {
        return $this->hasMany(BookingNotification::class, 'booking_id');
    }
}
