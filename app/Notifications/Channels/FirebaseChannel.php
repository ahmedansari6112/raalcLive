<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Messaging;

class FirebaseChannel
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function send($notifiable, Notification $notification)
    {
        // Call the toFirebase method to get the message details
        $messageData = $notification->toFirebase($notifiable);

        // Create a CloudMessage instance with the target token and notification details
        $message = CloudMessage::withTarget('token', $messageData['token'])
            ->withNotification([
                'title' => $messageData['title'],
                'body' => $messageData['body'],
            ]);

        try {
            // Send the message using Firebase Messaging
            $this->messaging->send($message);
        } catch (\Exception $e) {
            // Log any errors that occur while sending the notification
            \Log::error('Failed to send push notification: ' . $e->getMessage());
        }
    }
}
