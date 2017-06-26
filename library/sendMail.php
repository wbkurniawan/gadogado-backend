<?php
/**
 * Created by PhpStorm.
 * User: William
 * Date: 10/13/2016
 * Time: 7:35 PM
 */
require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
include_once __DIR__ . "/../config/global.php";

function sendMail($address,$subject,$content){

    $mail = new PHPMailer;

    $mail->isSMTP();
    $mail->Host = 'send.one.com';
    $mail->SMTPAuth = true;
    $mail->Username = ADMIN_EMAIL;
    $mail->Password = 'BOOKsharing2016';
    $mail->SMTPSecure = 'tls';

    $mail->From = ADMIN_EMAIL;
    $mail->FromName = ADMIN_EMAIL_NAME;
    $mail->addAddress($address);

    $mail->isHTML(true);

    $mail->Subject = $subject;
    $mail->Body    = $content;

    if(!$mail->send()) {
        throw new Exception('Mailer Error: ' . $mail->ErrorInfo);
    }
}