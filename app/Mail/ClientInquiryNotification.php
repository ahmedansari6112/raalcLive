<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInquiryNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $inquiryDetail;
    public $isAdmin;

    /**
     * Create a new message instance.
     */
   public function __construct($inquiryDetail, $isAdmin)
    {
        $this->inquiryDetail = $inquiryDetail;
        $this->isAdmin = $isAdmin;
    }
    
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject("New Client Inquiry Request")
                    ->markdown('emails.clientInquiryTemplate');
    }
}
