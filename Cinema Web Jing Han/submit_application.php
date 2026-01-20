<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $conn->real_escape_string($_POST['applicant_name']);
    $email = $conn->real_escape_string($_POST['applicant_email']);
    $phone = $conn->real_escape_string($_POST['applicant_phone']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $position = $conn->real_escape_string($_POST['position']);
    $experience = $conn->real_escape_string($_POST['experience']);
    $why_join = $conn->real_escape_string($_POST['why_join']);

    // Handle resume upload
    $resume_path = '';
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/resumes/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('resume_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            $resume_path = $target_file;
        }
    }

    $resume_path = $conn->real_escape_string($resume_path);

    $sql = "INSERT INTO job_applications
            (full_name, email, phone, start_date, position, experience, why_join, resume_path)
            VALUES ('$name', '$email', '$phone', '$start_date', '$position', '$experience', '$why_join', '$resume_path')";

    if ($conn->query($sql)) {
        
        // Send confirmation email using PHP mail()
        $to = $email;
        $email_subject = "Application Received - IE Theatre";
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: IE Theatre HR <hr@ietheatre.com>" . "\r\n";
        
        // Format start date nicely
        $formatted_date = date('F j, Y', strtotime($start_date));
        
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
                .highlight { color: #e96b39; font-weight: bold; }
                h1 { margin: 0; font-size: 2em; }
                h3 { color: #e96b39; margin-top: 0; }
                ul { padding-left: 20px; }
                li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>💼 IE Theatre Careers</h1>
                    <p style='margin: 5px 0 0 0; font-size: 1.1em;'>Application Confirmation</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>$name</strong>,</p>
                    <p>Thank you for your interest in joining the IE Theatre team! We have successfully received your application for the position of <span class='highlight'>$position</span>.</p>
                    
                    <div class='info-box'>
                        <h3>Application Summary:</h3>
                        <p><strong>Position:</strong> $position</p>
                        <p><strong>Available Start Date:</strong> $formatted_date</p>
                        <p><strong>Phone:</strong> $phone</p>
                        <p><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>Pending Review</span></p>
                    </div>
                    
                    <h3>What's Next?</h3>
                    <ul>
                        <li>Our HR team will review your application within 5-7 business days</li>
                        <li>If your qualifications match our requirements, we'll contact you for an interview</li>
                        <li>Please keep your phone and email accessible</li>
                    </ul>
                    
                    <p>If you have any questions, feel free to contact our HR department at <strong>hr@ietheatre.com</strong> or call <strong>+65 1234 5678</strong>.</p>
                    
                    <p>We appreciate your interest in IE Theatre and look forward to potentially having you on our team!</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br><strong>IE Theatre HR Team</strong></p>
                </div>
                <div class='footer'>
                    <p>IE Theatre | 123 Cinema Boulevard, Singapore 123456</p>
                    <p>📞 +65 1234 5678 | ✉️ hr@ietheatre.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        if (mail($to, $email_subject, $email_body, $headers)) {
            echo "<script>alert('Thank you for your application! A confirmation email has been sent to $email'); window.location.href='contact_careers.php?application_success=1#career-opportunities';</script>";
        } else {
            echo "<script>alert('Application submitted successfully, but email could not be sent.'); window.location.href='contact_careers.php?application_success=1#career-opportunities';</script>";
        }
        
    } else {
        echo "<script>alert('Error: " . $conn->error . "'); window.location.href='contact_careers.php#career-opportunities';</script>";
    }

    $conn->close();
}
?>
