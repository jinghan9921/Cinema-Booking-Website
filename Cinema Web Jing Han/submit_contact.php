<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $conn->real_escape_string($_POST['contact_name']);
    $email = $conn->real_escape_string($_POST['contact_email']);
    $phone = $conn->real_escape_string($_POST['contact_phone'] ?? '');
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);

    $sql = "INSERT INTO contact_submissions (full_name, email, phone, subject, message)
            VALUES ('$name', '$email', '$phone', '$subject', '$message')";

    if ($conn->query($sql)) {
        
        // Send confirmation email using PHP mail()
        $to = $email;
        $email_subject = "Thank you for contacting IE Theatre";
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: IE Theatre <noreply@ietheatre.com>" . "\r\n";
        
        // Email body
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(135deg, #e96b39 0%, #ffa726 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
                .info-box { background: #f0f0f0; padding: 15px; border-left: 4px solid #e96b39; margin: 20px 0; border-radius: 4px; }
                h1 { margin: 0; font-size: 2em; }
                h3 { color: #e96b39; margin-top: 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎬 IE Theatre</h1>
                    <p style='margin: 5px 0 0 0; font-size: 1.1em;'>Thank You for Contacting Us</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>$name</strong>,</p>
                    <p>Thank you for reaching out to IE Theatre. We have received your message and our team will review it shortly.</p>
                    
                    <div class='info-box'>
                        <h3>Your Message Details:</h3>
                        <p><strong>Subject:</strong> $subject</p>
                        <p><strong>Message:</strong><br>" . nl2br($message) . "</p>
                    </div>
                    
                    <p>We typically respond within 24-48 hours during business days. If your inquiry is urgent, please call us at <strong>+65 1234 5678</strong>.</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br><strong>IE Theatre Team</strong></p>
                </div>
                <div class='footer'>
                    <p>IE Theatre | 123 Cinema Boulevard, Singapore 123456</p>
                    <p>📞 +65 1234 5678 | ✉️ info@ietheatre.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        if (mail($to, $email_subject, $email_body, $headers)) {
            echo "<script>alert('Thank you for contacting us! A confirmation email has been sent to $email'); window.location.href='contact_careers.php?contact_success=1#contact-us';</script>";
        } else {
            echo "<script>alert('Form submitted successfully, but email could not be sent.'); window.location.href='contact_careers.php?contact_success=1#contact-us';</script>";
        }
        
    } else {
        echo "<script>alert('Error: " . $conn->error . "'); window.location.href='contact_careers.php#contact-us';</script>";
    }

    $conn->close();
}
?>
