<?php

namespace app\modules\email\controllers\Utils;

use app\modules\utils\controllers\PheanstalkLib;

/**
 * Class SendEmail
 * @package app\modules\email\controllers\Utils
 * This class is used as a general facade to send emails.
 * To use in you need to call it's static function named as
 * email class (no matter if it was or wasn't started with a capital letter)
 * and provide email and array of arguments to it.
 */
class SendEmail
{
    public static function __callStatic($name, $arguments)
    {
        $name = self::_formName($name);
        // asynchronously-executed function will not have own
        // $_SERVER['HTTP_HOST'], but it is still necessary to send email
        $arguments[] = $_SERVER['HTTP_HOST'];
        PheanstalkLib::putJob(
            self::class . '::_process',
            [$name, $arguments]
        );
    }

    protected static function _formName($name)
    {
        // We get a namespace of email classes and
        // form a class name for a function name
        $toCut = strLen('Utils');
        return substr(__NAMESPACE__, 0, ($toCut * -1)) .
            ucwords($name);
    }

    public static function _process($name, $arguments)
    {
        // global variable $_SERVER['HTTP_HOST'] is necessary to send email
        $_SERVER['HTTP_HOST'] = $arguments[2];
        $name = '\\' . $name;
        $mail = new $name();
        $mail->compose($arguments[0], $arguments[1]);
        return $mail->send();
    }
}
