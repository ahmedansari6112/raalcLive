<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingDetail;

    public function __construct($bookingDetail)
    {
        $this->bookingDetail = $bookingDetail;
    }

    public function broadcastOn()
    {
        $channelName = $this->bookingDetail['client_id'] 
        ? 'booking-notification-' . $this->bookingDetail['client_id'] . '-channel' 
        : 'booking-notification-channel';

        return new Channel($channelName);
    }

    public function broadcastAs(): string
    {
        return 'notify-event';
    }
}
