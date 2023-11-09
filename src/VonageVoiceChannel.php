<?php

namespace Roomies\VonageVoiceChannel;

use Illuminate\Notifications\Notification;
use Vonage\Client;
use Vonage\Voice\Endpoint\Phone;
use Vonage\Voice\NCCO\Action\Talk;
use Vonage\Voice\NCCO\NCCO;
use Vonage\Voice\OutboundCall;
use Vonage\Voice\VoiceObjects\AdvancedMachineDetection;

class VonageVoiceChannel
{
    /**
     * The Vonage client instance.
     *
     * @var \Vonage\Client
     */
    protected $client;

    /**
     * The phone number notifications should come from.
     *
     * @var string
     */
    protected $from;

    /**
     * The language to use for calls.
     *
     * @var string
     */
    protected $language;

    /**
     * The language style to use for calls.
     *
     * @var string
     */
    protected $style;

    /**
     * Create a new channel instance.
     *
     * @param  \Vonage\Client  $client
     * @param  string  $from
     * @param  string  $language
     * @param  string  $style
     * @return void
     */
    public function __construct(Client $client, string $from, string $language, string $style)
    {
        $this->client = $client;
        $this->from = $from;
        $this->language = $language;
        $this->style = $style;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void|\Vonage\Voice\Webhook\Event
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $to = $notifiable->routeNotificationFor('voice', $notification)) {
            return;
        }

        $message = $notification->toVoice($notifiable);

        return $this->call($to, (string) $message);
    }

    /**
     * Make the call to the given number with the given message.
     *
     * @param  string  $phoneNumber
     * @param  string  $message
     * @param  bool    $goToVoicemail
     * @return \Vonage\Voice\Webhook\Event
     */
    protected function call($phoneNumber, $message, $goToVoicemail = false)
    {
        $outboundCall = new OutboundCall(new Phone($phoneNumber), new Phone($this->from));

        if ($goToVoicemail) {
            $behavior = AdvancedMachineDetection::MACHINE_BEHAVIOUR_CONTINUE;
        } else {
            $behavior = AdvancedMachineDetection::MACHINE_BEHAVIOUR_HANGUP;
        }
        $outboundCall->setAdvancedMachineDetection(new AdvancedMachineDetection(
            $behavior,
            AdvancedMachineDetection::BEEP_TIMEOUT_MAX,
            AdvancedMachineDetection::MACHINE_MODE_DETECT_BEEP,
        ));

        $ncco = (new NCCO)->addAction(Talk::factory($message, [
            'level' => 1,
            'language' => $this->language,
            'style' => $this->style,
        ]));

        $outboundCall->setNCCO($ncco);

        return $this->client->voice()->createOutboundCall($outboundCall);
    }
}
