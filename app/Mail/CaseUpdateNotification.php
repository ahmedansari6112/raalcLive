<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaseUpdateNotification extends Mailable
{
    use Queueable, SerializesModels;
    public $caseUpdateDetail;
    public $isAdmin;

    /**
     * Create a new message instance.
     */
   public function __construct($caseUpdateDetail, $isAdmin)
    {
        $this->caseUpdateDetail = $caseUpdateDetail;
        $this->isAdmin = $isAdmin;
    }
    
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject("New Case Update Request")
                    ->markdown('emails.caseUpdateTemplate');
    }
}
