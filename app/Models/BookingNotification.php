<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingNotification extends Model
{
    use HasFactory;
    
    protected $table = 'booking_notifications';

    protected $fillable = [
        "booking_id",
        "description",
        "booking_status"
    ];
    
     public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
    
    public static function countUnreadNotifications($clientId)
    {
        return self::where('client_id', $clientId)
            ->where('notification_status', 0)
            ->join('bookings', 'bookings.id', '=', 'booking_notifications.booking_id')
            ->count();
    }

    public static function getNotificationsForClient($clientId)
    {
        return self::where('client_id', $clientId)
            ->join('bookings', 'bookings.id', '=', 'booking_notifications.booking_id')
            ->select('booking_notifications.booking_id', 'booking_notifications.description as notification_description',
                    'booking_notifications.booking_status as notification_booking_status',
                    'booking_notifications.id as notification_id',
                    'booking_notifications.notification_status as notification_message_status',
                    'booking_notifications.created_at as notification_created_at',
                    'bookings.*')
            ->get();
    }
}
