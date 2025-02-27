<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookMeetingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $bookingDetail;
    public $subject;
    public $template;
    public $language;
    public $isAdmin;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($bookingDetail, $template, $language, $isAdmin)
    {
        $this->bookingDetail = $bookingDetail;
        $this->template = $template;
        $this->language = $language;
        $this->isAdmin = $isAdmin;
        $this->subject = $this->getSubjectByLanguage($language, $bookingDetail);
    }
    
    
    protected function getSubjectByLanguage($language, $bookingDetail)
    {
        if($bookingDetail['change_status']){
            $subjects = [
                'en' => 'Legal Consultation Request Status Updated',
                'ar' => 'تم تحديث حالة طلب الاستشارة القانونية',
                'ru' => 'Статус бронирования встречи обновлен',
                'ch' => '会议预订状态已更新',
            ];    
        }else{
            $subjects = [
                'en' => 'Legal Consultation Request Confirmation',
                'ar' => 'تأكيد طلب استشارة قانونية',
                'ru' => 'Подтверждение бронирования',
                'ch' => '预订确认',
            ];
        }
        
        return $subjects[$language] ?? $subjects['en']; // Default to English if language not found
    }
    
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)
                    ->markdown($this->template)
                    ->with([
                        'bookingDetail' => $this->bookingDetail,
                        'isAdmin' => $this->isAdmin
                    ]);
    }
}
