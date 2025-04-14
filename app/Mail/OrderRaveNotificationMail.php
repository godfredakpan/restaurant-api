<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderRaveNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $messageBody;
    public $actionUrl;
    public $actionText;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $messageBody, $actionUrl, $actionText)
    {
        $this->subject = $subject;
        $this->messageBody = $messageBody;
        $this->actionUrl = $actionUrl;
        $this->actionText = $actionText;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.order-rave')
                    ->subject($this->subject)
                    ->from('no-reply@api.orderrave.ng', 'Order Rave'); 
    }
}
