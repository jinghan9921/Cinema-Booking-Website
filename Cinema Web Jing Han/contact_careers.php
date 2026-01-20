<?php
session_start();
include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Contact & Careers - IE Theatre</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="sidebarandfooter.css">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { 
        background: #191a1c; 
        font-family: 'Segoe UI', Arial, sans-serif; 
        color: #eee; 
        padding: 20px 20px 150px 220px; 
    }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { color: #e96b39; text-align: center; font-size: 2.5em; margin-bottom: 40px; }
    h2 { color: #ffa726; margin-bottom: 15px; font-size: 1.8em; }
    .section { background: #2a2d35; padding: 40px; border-radius: 12px; margin-bottom: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .section-intro { color: #ccc; margin-bottom: 30px; line-height: 1.6; }
    
    /* Info Box */
    .info-box {
        background: linear-gradient(135deg, #e96b39 0%, #ffa726 100%);
        border-left: 5px solid #e96b39;
        padding: 25px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    .info-box h3 { color: #fff; margin-bottom: 15px; font-size: 1.3em; }
    .info-item { 
        display: flex; 
        align-items: center; 
        margin-bottom: 10px; 
        color: #fff;
    }
    .info-item:last-child { margin-bottom: 0; }
    .info-item strong { min-width: 150px; }
    
    /* Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }
    
    .form-group label { 
        display: block; 
        color: #ffa726; 
        font-weight: bold; 
        margin-bottom: 8px; 
    }
    
    /* Text inputs and textarea */
    .form-group input, .form-group textarea { 
        width: 100%; 
        padding: 12px; 
        background: rgba(0,0,0,0.3); 
        border: 2px solid #555; 
        border-radius: 6px; 
        color: #eee; 
        font-size: 1em; 
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    /* Dropdown/Select - BLACK text for readability */
    .form-group select { 
        width: 100%; 
        padding: 12px; 
        background: rgba(255,255,255,0.9); 
        border: 2px solid #555; 
        border-radius: 6px; 
        color: #000; 
        font-size: 1em; 
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    /* Dropdown options - black text on white background */
    .form-group select option {
        background: #fff;
        color: #000;
    }
    
    /* Date input with BRIGHT background for calendar icon visibility */
    .form-group input[type="date"] {
        width: 100%; 
        padding: 12px; 
        background: rgba(255,255,255,0.85); /* Bright white background */
        border: 2px solid #555; 
        border-radius: 6px; 
        color: #000; /* Black text to match the bright background */
        font-size: 1em; 
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    .form-group input[type="date"]:focus {
        outline: none; 
        border-color: #ffa726;
        background: rgba(255,255,255,0.95); /* Even brighter on focus */
    }

    /* Calendar icon will now be clearly visible */
    .form-group input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
    }

    .form-group textarea { min-height: 120px; resize: vertical; }
    .error-message { color: #ff4444; font-size: 0.9em; margin-top: 5px; display: block; }
    .btn-submit { 
        background: linear-gradient(135deg, #e96b39 0%, #ffa726 100%); 
        color: white; 
        padding: 14px 30px; 
        border: none; 
        border-radius: 8px; 
        font-size: 1.1em; 
        font-weight: bold; 
        cursor: pointer; 
        transition: all 0.3s; 
        margin-top: 20px;
    }
    .btn-submit:hover { 
        background: linear-gradient(135deg, #d45a28 0%, #ff9100 100%); 
        transform: translateY(-2px); 
    }
    .required { color: #ff4444; }
    .success-message { 
        background: #d4edda; 
        color: #155724; 
        border: 2px solid #c3e6cb; 
        padding: 15px; 
        border-radius: 8px; 
        margin-bottom: 20px; 
        text-align: center; 
    }
    
    /* Smooth scrolling for anchor links */
    html { scroll-behavior: smooth; }
    
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

</head>
<body>
    <div class="container">
        <h1>📧 Contact & Careers</h1>
        
        <!-- CONTACT SECTION -->
        <div class="section" id="contact-us">
            <h2>Contact Us</h2>
            <p class="section-intro">Have questions, feedback, or need assistance? We'd love to hear from you! Fill out the form below or reach out directly using our contact information.</p>
            
            <div class="info-box">
                <h3>IE Theatre Information</h3>
                <div class="info-item">
                    <strong>📍 Address:</strong>
                    <span>123 Cinema Boulevard, Singapore 123456</span>
                </div>
                <div class="info-item">
                    <strong>📞 Phone:</strong>
                    <span>+65 1234 5678</span>
                </div>
                <div class="info-item">
                    <strong>✉️ Email:</strong>
                    <span>info@ietheatre.com</span>
                </div>
                <div class="info-item">
                    <strong>🕐 Operating Hours:</strong>
                    <span>Daily 10:00 AM - 11:00 PM</span>
                </div>
            </div>


            <?php if (isset($_GET['contact_success'])): ?>
                <div class="success-message">✅ Thank you for contacting us! We'll get back to you soon.</div>
            <?php endif; ?>


            <form action="submit_contact.php" method="POST" id="contactForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="contact_name" id="contact_name" placeholder="Enter your full name" required>
                        <span class="error-message" id="contactNameError"></span>
                    </div>


                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="contact_email" id="contact_email" placeholder="your.email@example.com" required>
                        <span class="error-message" id="contactEmailError"></span>
                    </div>


                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="contact_phone" placeholder="+65 1234 5678">
                    </div>


                    <div class="form-group">
                        <label>Subject <span class="required">*</span></label>
                        <select name="subject" required>
                            <option value="">-- Select Subject --</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Booking Issue">Booking Issue</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Feedback">Feedback</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>


                    <div class="form-group full-width">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" placeholder="Write your message here..." required></textarea>
                    </div>
                </div>


                <button type="submit" class="btn-submit">Send Message</button>
            </form>
        </div>


        <!-- CAREERS SECTION -->
        <div class="section" id="career-opportunities">
            <h2>💼 Career Opportunities</h2>
            <p class="section-intro">Interested in joining the IE Theatre team? We're always looking for passionate individuals to help create memorable cinema experiences. Fill out the application form below to get started. Required fields are marked with an asterisk <span class="required">*</span></p>


            <?php if (isset($_GET['application_success'])): ?>
                <div class="success-message">✅ Thank you for your application! We'll review it and get back to you soon.</div>
            <?php endif; ?>


            <form action="submit_application.php" method="POST" enctype="multipart/form-data" id="applicationForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="applicant_name" id="applicant_name" placeholder="Enter your full name" required>
                        <span class="error-message" id="applicantNameError"></span>
                    </div>


                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="applicant_email" id="applicant_email" placeholder="your.email@example.com" required>
                        <span class="error-message" id="applicantEmailError"></span>
                    </div>


                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="applicant_phone" id="applicant_phone" placeholder="+65 1234 5678" required>
                    </div>


                    <div class="form-group">
                        <label>Available Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" id="start_date" required>
                        <span class="error-message" id="startDateError"></span>
                    </div>


                    <div class="form-group full-width">
                        <label>Position Applying For <span class="required">*</span></label>
                        <select name="position" required>
                            <option value="">-- Select Position --</option>
                            <option value="Box Office Staff">Box Office Staff</option>
                            <option value="Concession Staff">Concession Staff</option>
                            <option value="Usher">Usher</option>
                            <option value="Projectionist">Projectionist</option>
                            <option value="Manager">Manager</option>
                            <option value="Cleaner">Cleaner</option>
                        </select>
                    </div>


                    <div class="form-group full-width">
                        <label>Previous Experience <span class="required">*</span></label>
                        <textarea name="experience" id="experience" placeholder="Describe your relevant work experience and skills..." required></textarea>
                        <span class="error-message" id="experienceError"></span>
                    </div>


                    <div class="form-group full-width">
                        <label>Why do you want to join IE Theatre? <span class="required">*</span></label>
                        <textarea name="why_join" placeholder="Tell us what motivates you to join our team..." required></textarea>
                    </div>


                    <div class="form-group full-width">
                        <label>Resume/CV (PDF or DOC) <span class="required">*</span></label>
                        <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
                    </div>
                </div>


                <button type="submit" class="btn-submit">Submit Application</button>
            </form>
        </div>
    </div>


    <?php include 'footer.php'; ?>


    <script>
        // Date validation - set minimum to tomorrow
        document.addEventListener("DOMContentLoaded", function() {
            const startDateInput = document.getElementById("start_date");
            const today = new Date();
            today.setDate(today.getDate() + 1); // Set to tomorrow
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, "0");
            const dd = String(today.getDate()).padStart(2, "0");
            startDateInput.min = `${yyyy}-${mm}-${dd}`;


            // Form validation for application form
            document.getElementById("applicationForm").addEventListener("submit", function(event) {
                let hasErrors = false;


                // Name validation (must contain letters and spaces only)
                const name = document.getElementById("applicant_name").value.trim();
                const nameError = document.getElementById("applicantNameError");
                const onlyLettersSpaces = /^[A-Za-z\s]+$/.test(name);
                const hasLetter = /[A-Za-z]/.test(name);
                const hasSpace = /\s/.test(name);


                if (!(onlyLettersSpaces && hasLetter && hasSpace)) {
                    nameError.textContent = "Name must contain alphabet characters and spaces. No symbols";
                    hasErrors = true;
                } else {
                    nameError.textContent = "";
                }


                // Email validation (only needs @)
                const email = document.getElementById("applicant_email").value.trim();
                const emailError = document.getElementById("applicantEmailError");


                if (email === "" || !email.includes("@")) {
                    emailError.textContent = "Please enter a valid email address with @";
                    hasErrors = true;
                } else {
                    emailError.textContent = "";
                }


                // Experience validation (cannot be empty)
                const experience = document.getElementById("experience").value.trim();
                const experienceError = document.getElementById("experienceError");
                if (!experience) {
                    experienceError.textContent = "Experience field cannot be empty.";
                    hasErrors = true;
                } else {
                    experienceError.textContent = "";
                }


                // Date validation (must be tomorrow or later)
                const startDate = document.getElementById("start_date").value;
                const startDateError = document.getElementById("startDateError");
                const selectedDate = new Date(startDate);
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(0, 0, 0, 0);
                selectedDate.setHours(0, 0, 0, 0);


                if (selectedDate < tomorrow) {
                    startDateError.textContent = "Start date must be tomorrow or later.";
                    hasErrors = true;
                } else {
                    startDateError.textContent = "";
                }


                if (hasErrors) {
                    event.preventDefault();
                }
            });


            // Contact form validation
            document.getElementById("contactForm").addEventListener("submit", function(event) {
                let hasErrors = false;


                // Name validation
                const contactName = document.getElementById("contact_name").value.trim();
                const contactNameError = document.getElementById("contactNameError");
                const onlyLettersSpaces = /^[A-Za-z\s]+$/.test(contactName);
                const hasLetter = /[A-Za-z]/.test(contactName);
                const hasSpace = /\s/.test(contactName);


                if (!(onlyLettersSpaces && hasLetter && hasSpace)) {
                    contactNameError.textContent = "Name must contain alphabet characters and spaces. No symbols";
                    hasErrors = true;
                } else {
                    contactNameError.textContent = "";
                }


                // Email validation (only needs @)
                const contactEmail = document.getElementById("contact_email").value.trim();
                const contactEmailError = document.getElementById("contactEmailError");


                if (contactEmail === "" || !contactEmail.includes("@")) {
                    contactEmailError.textContent = "Please enter a valid email address with @";
                    hasErrors = true;
                } else {
                    contactEmailError.textContent = "";
                }


                if (hasErrors) {
                    event.preventDefault();
                }
            });
        });
    </script>


</body>
</html>
