<?php

namespace Core\Base\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $_details;

    public function __construct($detail)
    {
        //
        $this->_details = $detail;
        // $this->details = $this->_details['data'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        // return $this->subject('Mail from ampol.com')->view('emails.test');
        return $this->view($this->_details['views'])
            ->subject($this->_details['subject'])
            ->from($this->_details['from_address'], $this->_details['from_name'])
            //   ->cc($address, $name)
            //    ->bcc($address, $name)
            ->replyTo($this->_details['replyTo'], 'ตอบกลับ นะจ๊ะ')
            ->with([
                'details' => $this->_details['data'],
            ]);
    }

    private function asJSON($data)
    {
        $json = json_encode($data);
        $json = preg_replace('/(["\]}])([,:])(["\[{])/', '$1$2 $3', $json);

        return $json;
    }

    private function asString($data)
    {
        $json = $this->asJSON($data);

        return wordwrap($json, 76, "\n   ");
    }
}
