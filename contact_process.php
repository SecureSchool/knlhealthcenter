<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/SMTP.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars(trim($_POST['name']));
    $email   = htmlspecialchars(trim($_POST['email']));
    $message = htmlspecialchars(trim($_POST['message']));

    if (empty($name) || empty($email) || empty($message)) {
        echo "All fields are required.";
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aujscdelaconcepcionmacy@gmail.com'; // Your Gmail
        $mail->Password   = 'izvk emfc onna xizb';   // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($email, $name);          // Sender is user
        $mail->addAddress('youremail@gmail.com'); // Recipient is your email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Contact Message from ' . $name;
        $mail->Body    = "<h3>Contact Form Submission</h3>
                          <p><strong>Name:</strong> {$name}</p>
                          <p><strong>Email:</strong> {$email}</p>
                          <p><strong>Message:</strong><br>{$message}</p>";
        $mail->AltBody = "Name: {$name}\nEmail: {$email}\nMessage: {$message}";

        $mail->send();
        echo "Message sent successfully!";
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
} else {
    echo "Invalid request.";
}
