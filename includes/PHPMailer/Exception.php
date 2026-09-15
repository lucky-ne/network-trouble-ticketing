<?php
namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong><br />\n";
    }
}
