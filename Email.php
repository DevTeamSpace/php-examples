<?php

namespace app\modules\email\controllers\Utils;

use Mailgun\Mailgun;

/**
 * Class Email
 * @package app\modules\email\controllers\Utils
 * This is an abstract class to work with mails. It is a wrapper to
 * Mailgun class's methods.
 * To use any of this class's childes you need to:
 *      $variables = [];
 *      $email = "";
 *      $mail = new MailClass;
 *      $mail->compose($email, $variables);
 *      $mail->send();
 * Every child class has it's special attributes. 'Compose' method
 * always receives variables as indexed array and turns it into
 * key=>value array.
 */
abstract class Email
{
    protected $template;
    protected $subject;

    protected $email;
    protected $variables = [];
    protected $from = 'Some Domain some@domain.com';

    protected $mailGun;
    protected $templatePrefix;

    public function __construct()
    {
        $this->setStaticUrl();
        $this->templatePrefix = __DIR__ . "/../../views/default/";
        $this->mailGun = new Mailgun($_ENV['MAIL_API_KEY']);
    }

    public function compose($email, $variables)
    {
        if ($email) {
            $this->email = $email;
        }
        if ($variables) {
            $this->addVariables($variables);
        }
        if ((!isset($this->template))||(empty($this->template))) {
            $this->template = $this->getDefaultTemplate();
        }
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function setFrom($from)
    {
        $this->from = $from;
    }

    public function send()
    {
        return $this->mailGun->sendMessage($_ENV['MAIL_DOMAIN'], [
            'from' => $this->from,
            'to' => $this->email,
            'subject' => $this->subject,
            'html' => $this->templateToFile(),
        ]);
    }

    protected function formTemplatePath()
    {
        return $this->templatePrefix . $this->template;
    }

    protected function templateToFile()
    {
        ob_start();
        extract($this->variables);
        include($this->formTemplatePath());
        return ob_get_clean();
    }

    protected function returnStaticUrl()
    {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") .
            "://$_SERVER[HTTP_HOST]";
    }

    protected function addVariables($variables)
    {
        $this->variables = array_merge($this->variables, $variables);
    }

    protected function setStaticUrl()
    {
        $this->addVariables(['staticUrl'=>$this->returnStaticUrl()]);
    }

    protected function getDefaultTemplate()
    {
        $className = static::class;
        $result = [];
        preg_match_all("/[A-Z][a-z]*/", $className, $result);
        $result = $result[0];
        foreach ($result as &$item) {
            $item = strtolower($item);
        }
        return implode('_', $result) . '.phtml';
    }
}
