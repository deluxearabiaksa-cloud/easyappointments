<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
     * Easy!Appointments - Online Appointment Scheduler
     *
     * @package EasyAppointments
     * @author A.Tselegidis <alextselegidis@gmail.com>
     * @copyright Copyright (c) Alex Tselegidis
     * @license https://opensource.org/licenses/GPL-3.0 - GPLv3
     * @link https://easyappointments.org
 * @since v1.4.0
     * ---------------------------------------------------------------------------- */

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Email messages library.
     *
     * Handles the email messaging related functionality.
     *
     * @package Libraries
     */
class Email_messages
    {
            /**
     * @var EA_Controller|CI_Controller
             */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Email_messages constructor.
             */
    public function __construct()
        {
                    $this->CI = &get_instance();

                $this->CI->load->model('admins_model');
                    $this->CI->load->model('appointments_model');
                    $this->CI->load->model('providers_model');
                    $this->CI->load->model('secretaries_model');
                    $this->CI->load->model('secretaries_model');
                    $this->CI->load->model('settings_model');

                $this->CI->load->library('email');
                    $this->CI->load->library('ics_file');
                    $this->CI->load->library('timezones');
        }

    public function send_appointment_saved(
                array $appointment,
                array $provider,
                array $service,
                array $customer,
                array $settings,
                string $subject,
                string $message,
                string $appointment_link,
                string $recipient_email,
                string $ics_stream,
                ?string $timezone = null,
            ): void {
                $appointment_timezone = new DateTimeZone($provider['timezone']);
                $appointment_start = new DateTime($appointment['start_datetime'], $appointment_timezone);
                $appointment_end = new DateTime($appointment['end_datetime'], $appointment_timezone);

                if ($timezone && $timezone !== $provider['timezone']) {
                                $custom_timezone = new DateTimeZone($timezone);
                                $appointment_start->setTimezone($custom_timezone);
                                $appointment['start_datetime'] = $appointment_start->format('Y-m-d H:i:s');
                                $appointment_end->setTimezone($custom_timezone);
                                $appointment['end_datetime'] = $appointment_end->format('Y-m-d H:i:s');
                }

                $html = $this->CI->load->view(
                                'emails/appointment_saved_email',
                                [
                                    'subject' => $subject,
                                    'message' => $message,
                                    'appointment' => $appointment,
                                    'service' => $service,
                                    'provider' => $provider,
                                    'customer' => $customer,
                                    'settings' => $settings,
                                    'timezone' => $timezone,
                                    'appointment_link' => $appointment_link,
                                ],
                                true,
                            );

                $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
                $php_mailer->addStringAttachment($ics_stream, 'invitation.ics', PHPMailer::ENCODING_BASE64, 'text/calendar');
                $this->dispatch($php_mailer);
    }

    public function send_appointment_deleted(
                array $appointment,
                array $provider,
                array $service,
                array $customer,
                array $settings,
                string $recipient_email,
                ?string $reason = null,
                ?string $timezone = null,
            ): void {
                $appointment_timezone = new DateTimeZone($provider['timezone']);
                $appointment_start = new DateTime($appointment['start_datetime'], $appointment_timezone);
                $appointment_end = new DateTime($appointment['end_datetime'], $appointment_timezone);

                if ($timezone && $timezone !== $provider['timezone']) {
                                $custom_timezone = new DateTimeZone($timezone);
                                $appointment_start->setTimezone($custom_timezone);
                                $appointment['start_datetime'] = $appointment_start->format('Y-m-d H:i:s');
                                $appointment_end->setTimezone($custom_timezone);
                                $appointment['end_datetime'] = $appointment_end->format('Y-m-d H:i:s');
                }

                $html = $this->CI->load->view(
                                'emails/appointment_deleted_email',
                                [
                                    'appointment' => $appointment,
                                    'service' => $service,
                                    'provider' => $provider,
                                    'customer' => $customer,
                                    'settings' => $settings,
                                    'timezone' => $timezone,
                                    'reason' => $reason,
                                ],
                                true,
                            );

                $subject = lang('appointment_cancelled_title');
                $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
                $this->dispatch($php_mailer);
    }

    public function send_password(string $password, string $recipient_email, array $settings): void
        {
                    $html = $this->CI->load->view(
                                    'emails/account_recovery_email',
                                    [
                                        'subject' => lang('new_account_password'),
                                        'message' => str_replace('$password', '<strong>' . $password . '</strong>', lang('new_password_is')),
                                        'settings' => $settings,
                                    ],
                                    true,
                                );

                $subject = lang('new_account_password');
                    $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
                    $this->dispatch($php_mailer);
        }

    public function send_password_reset_link(string $reset_link, string $recipient_email, array $settings): void
        {
                    $html = $this->CI->load->view(
                                    'emails/password_reset_email',
                                    [
                                        'subject' => lang('password_reset_request'),
                                        'message' => lang('password_reset_email_message'),
                                        'reset_link' => $reset_link,
                                        'settings' => $settings,
                                    ],
                                    true,
                                );

                $subject = lang('password_reset_request');
                    $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
                    $this->dispatch($php_mailer);
        }

    private function dispatch(PHPMailer $mailer): void
    {
        $api_key = getenv('BREVO_API_KEY') ?: ($_ENV['BREVO_API_KEY'] ?? ($_SERVER['BREVO_API_KEY'] ?? ''));

        log_message('error', 'BREVO dispatch called. api_key=' . ($api_key ? 'SET(len=' . strlen($api_key) . ')' : 'EMPTY'));

        if (!$api_key) {
            log_message('error', 'BREVO: no API key, falling back to SMTP');
            $mailer->send();
            return;
        }

        $to = [];
        foreach ($mailer->getToAddresses() as $address) {
            $entry = ['email' => $address[0]];
            if (!empty($address[1])) {
                $entry['name'] = $address[1];
            }
            $to[] = $entry;
        }

        $payload = [
            'sender' => [
                'email' => $mailer->From,
                'name'  => $mailer->FromName,
            ],
            'to'          => $to,
            'subject'     => $mailer->Subject,
            'htmlContent' => $mailer->Body,
            'textContent' => $mailer->AltBody,
        ];

        $attachments = $mailer->getAttachments();
        if (!empty($attachments)) {
            $payload['attachment'] = [];
            foreach ($attachments as $att) {
                if (!empty($att[5])) {
                    $payload['attachment'][] = [
                        'name'    => $att[2],
                        'content' => base64_encode($att[0]),
                    ];
                }
            }
        }

        log_message('error', 'BREVO: sending to ' . json_encode($to) . ' subject=' . $mailer->Subject);
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $api_key,
            'content-type: application/json',
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', 'BREVO response: HTTP ' . $http_code . ' body=' . $response);

        if ($http_code !== 201) {
            log_message('error', 'Brevo API error: HTTP ' . $http_code . ' - ' . $response);
            throw new Exception('Brevo API failed: HTTP ' . $http_code . ' - ' . $response);
        }
    }

    private function get_php_mailer(
                ?string $recipient_email = null,
                ?string $subject = null,
                ?string $html = null,
            ): PHPMailer {
                $php_mailer = new PHPMailer(true);

                $php_mailer->CharSet   = 'UTF-8';
                $php_mailer->SMTPDebug = config('smtp_debug') ? SMTP::DEBUG_SERVER : null;

                if (config('protocol') === 'smtp') {
                                $php_mailer->isSMTP();
                                $php_mailer->Host       = config('smtp_host');
                                $php_mailer->SMTPAuth   = config('smtp_auth');
                                $php_mailer->Username   = config('smtp_user');
                                $php_mailer->Password   = config('smtp_pass');
                                $php_mailer->SMTPSecure = config('smtp_crypto');
                                $php_mailer->Port       = config('smtp_port');
                }

                $from_name        = config('from_name') ?: setting('company_name');
                $from_address     = config('from_address') ?: setting('company_email');
                $reply_to_address = config('reply_to') ?: setting('company_email');

                $php_mailer->setFrom($from_address, $from_name);
                $php_mailer->addReplyTo($reply_to_address);

                if ($recipient_email) {
                                $php_mailer->addAddress($recipient_email);
                }

                if ($subject) {
                                $php_mailer->Subject = $subject;
                }

                if ($html) {
                                $plain_text = str_replace(["\n\n", "\n\n\n"], '', striptags($html));

                    if (config('mailtype') === 'html') {
                                        $php_mailer->isHTML();
                    } else {
                                        $html = $plain_text;
                    }

                    $php_mailer->Body    = $html;
                                $php_mailer->AltBody = $plain_text;
                }

                try { $php_mailer->addEmbeddedImage(FCPATH . 'assets/img/logo.png', 'logo.png', 'logo.png', 'base64', 'image/png'); } catch (\Exception $e) { log_message('error', 'BREVO: logo embed failed: ' . $e->getMessage()); }

                return $php_mailer;
    }
    }
