<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\FirebaseChannel;

class SendPushNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $body;
    protected $token;

    public function __construct($title, $body, $token)
    {
        $this->title = $title;
        $this->body = $body;
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['firebase']; // Use the custom driver name 'firebase'
    }

    public function toFirebase($notifiable)
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'token' => $this->token,
        ];
    }
}
