<?php
/**
 * CONTACT FORM EMAIL HANDLER - WEBMAIL/cPanel VERSION
 * 
 * For: info@claricentgroup.com (or any custom domain email)
 * 
 * QUICK SETUP:
 * 1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer
 * 2. Upload the PHPMailer folder to your server
 * 3. Update the settings below (lines 25-35)
 * 4. Upload this file to your website
 * 5. Test the form!
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Prevent direct access - only allow POST requests
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Return JSON error instead of redirect for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    } else {
        header("Location: index.html");
    }
    exit();
}

// ============================================
// YOUR SETTINGS - UPDATE THESE!
// ============================================

// SMTP Server Settings (Get from your hosting provider)
$smtp_host = 'mail.claricentgroup.com';      // Usually: mail.yourdomain.com
$smtp_port = 465;                            // Try 587 first, then 465 if it doesn't work

// Your Email Credentials
$smtp_username = 'info@claricentgroup.com';  // Your full email address
$smtp_password = 'Claricentgroup'; // Your email password (same as webmail login)

// Security Type
$smtp_secure = 'ssl';                        // Use 'tls' for port 587, 'ssl' for port 465

// Where to receive contact form submissions
$to_email = 'info@claricentgroup.com';       // Your email address

// Email sender details
$from_email = 'info@claricentgroup.com';  // Can be same as $to_email
$from_name = 'Claricent Company Limited';    // Your company name

// Auto-reply to customers?
$send_auto_reply = true;                     // Set to false to disable auto-replies

// Debug mode (IMPORTANT: Set to false after testing!)
$debug_mode = false;                         // Set to true to see detailed error messages

// ============================================
// END OF SETTINGS
// ============================================

// Get form data
$fullname = isset($_POST['fullname']) ? strip_tags(trim($_POST['fullname'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$phone = isset($_POST['phone']) ? strip_tags(trim($_POST['phone'])) : '';
$subject = isset($_POST['subject']) ? strip_tags(trim($_POST['subject'])) : '';
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

// Validate required fields
if (empty($fullname) || empty($email) || empty($phone) || empty($subject) || empty($message)) {
    echo "Please fill in all required fields.";
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format.";
    exit();
}

// Create new PHPMailer instance
$mail = new PHPMailer(true);

try {
    // ============================================
    // SMTP CONFIGURATION
    // ============================================
    
    // Enable debug output if in debug mode
    if ($debug_mode) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';
    }
    
    // Tell PHPMailer to use SMTP
    $mail->isSMTP();
    
    // SMTP server settings
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password;
    $mail->SMTPSecure = $smtp_secure;
    $mail->Port       = $smtp_port;
    
    // If you get SSL certificate errors, uncomment these lines:
    /*
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    */

    // ============================================
    // EMAIL RECIPIENTS
    // ============================================
    
    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to_email);
    $mail->addReplyTo($email, $fullname);

    // ============================================
    // EMAIL CONTENT
    // ============================================
    
    $mail->isHTML(true);
    $mail->Subject = "New Contact Form Submission From Claricent Website: $subject";
    $mail->CharSet = 'UTF-8';
    
    // HTML Email Body
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: #ffffff;
            }
            .header { 
                background: #2C3E50; 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
            }
            .header p {
                margin: 5px 0 0 0;
                opacity: 0.9;
            }
            .content { 
                background: #f9f9f9; 
                padding: 30px 20px; 
            }
            .info-row { 
                margin-bottom: 20px; 
                padding-bottom: 20px; 
                border-bottom: 1px solid #ddd; 
            }
            .info-row:last-child { 
                border-bottom: none; 
            }
            .label { 
                font-weight: bold; 
                color: #2C3E50; 
                display: block;
                margin-bottom: 5px;
            }
            .value { 
                color: #555; 
            }
            .value a {
                color: #1E90FF;
                text-decoration: none;
            }
            .value a:hover {
                text-decoration: underline;
            }
            .message-box { 
                background: white; 
                padding: 20px; 
                border-left: 4px solid #1E90FF; 
                margin-top: 20px; 
                line-height: 1.8;
            }
            .footer { 
                background: #2C3E50; 
                color: white; 
                padding: 20px; 
                text-align: center; 
                font-size: 12px; 
            }
            .footer p {
                margin: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>📧 New Contact Form Submission</h2>
                <p>' . htmlspecialchars($from_name) . '</p>
            </div>
            <div class="content">
                <div class="info-row">
                    <span class="label">👤 Name:</span>
                    <span class="value">' . htmlspecialchars($fullname) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">📧 Email:</span>
                    <span class="value">
                        <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">📱 Phone:</span>
                    <span class="value">
                        <a href="tel:' . htmlspecialchars($phone) . '">' . htmlspecialchars($phone) . '</a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">📋 Subject:</span>
                    <span class="value">' . htmlspecialchars($subject) . '</span>
                </div>
                <div class="message-box">
                    <div class="label">💬 Message:</div>
                    <div style="margin-top: 10px;">' . nl2br(htmlspecialchars($message)) . '</div>
                </div>
            </div>
            <div class="footer">
                <p>📅 Submitted on: ' . date('F d, Y \a\t h:i A') . '</p>
                <p>🌐 From: ' . htmlspecialchars($from_name) . ' Contact Form</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Plain text version (for email clients that don't support HTML)
    $mail->AltBody = "New Contact Form Submission\n\n"
                   . "Name: $fullname\n"
                   . "Email: $email\n"
                   . "Phone: $phone\n"
                   . "Subject: $subject\n\n"
                   . "Message:\n$message\n\n"
                   . "Submitted on: " . date('F d, Y \a\t h:i A');

    // Send the email
    $mail->send();
    
    // ============================================
    // SEND AUTO-REPLY TO CUSTOMER
    // ============================================
    
    if ($send_auto_reply) {
        $autoReply = new PHPMailer(true);
        
        // Debug mode
        if ($debug_mode) {
            $autoReply->SMTPDebug = 2;
            $autoReply->Debugoutput = 'html';
        }
        
        // SMTP settings (same as main email)
        $autoReply->isSMTP();
        $autoReply->Host       = $smtp_host;
        $autoReply->SMTPAuth   = true;
        $autoReply->Username   = $smtp_username;
        $autoReply->Password   = $smtp_password;
        $autoReply->SMTPSecure = $smtp_secure;
        $autoReply->Port       = $smtp_port;
        
        // Recipients
        $autoReply->setFrom($from_email, $from_name);
        $autoReply->addAddress($email, $fullname);
        
        // Content
        $autoReply->isHTML(true);
        $autoReply->CharSet = 'UTF-8';
        $autoReply->Subject = "Thank you for contacting $from_name";
        
        $autoReply->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; }
                .header { background: #2C3E50; color: white; padding: 30px 20px; text-align: center; }
                .header h2 { margin: 0; }
                .content { background: #f9f9f9; padding: 30px 20px; }
                .message-copy { background: white; padding: 20px; border-left: 4px solid #1E90FF; margin: 20px 0; }
                .contact-info { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #2C3E50; color: white; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>✅ Thank You for Contacting Us!</h2>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
                    
                    <p>Thank you for reaching out to <strong>' . htmlspecialchars($from_name) . '</strong>. 
                    We have received your message and will get back to you as soon as possible.</p>
                    
                    <div class="message-copy">
                        <strong>📝 Your message:</strong><br><br>
                        ' . nl2br(htmlspecialchars($message)) . '
                    </div>
                    
                    <div class="contact-info">
                        <p><strong>📞 Need immediate assistance?</strong></p>
                        <p>Call us at:<br>
                        <strong>+233 243368425</strong><br>
                        <strong>+233 500153340</strong><br>
                        <strong>+233 209128276</strong></p>
                    </div>
                    
                    <p>Best regards,<br>
                    <strong>' . htmlspecialchars($from_name) . ' Team</strong></p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($from_name) . '. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $autoReply->AltBody = "Dear $fullname,\n\n"
                            . "Thank you for reaching out to $from_name. "
                            . "We have received your message and will get back to you as soon as possible.\n\n"
                            . "Your message:\n$message\n\n"
                            . "Need immediate assistance? Call us at:\n"
                            . "+233 243368425\n"
                            . "+233 500153340\n"
                            . "+233 209128276\n\n"
                            . "Best regards,\n$from_name Team";
        
        $autoReply->send();
    }
    
    // Success!
    echo 'success';
    
} catch (Exception $e) {
    // Error handling
    if ($debug_mode) {
        // Show detailed error in debug mode
        echo "Error: {$mail->ErrorInfo}";
    } else {
        // Generic error message for production
        echo "Oops! Something went wrong. Please try again later or contact us directly.";
    }
}
?>