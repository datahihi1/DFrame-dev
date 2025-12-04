<?php

namespace DFrame\Application;

use DFrame\Application\View;

/**
 * #### Simple SMTP Gmail Mailer (v0.1.20251204-mini+dev)
 *
 * Secure Mail class supporting Gmail SMTP, CC, BCC, and Attachments.
 */
class Mail
{
    private $smtp_host = "smtp.gmail.com";
    private $smtp_port = 587;
    private $username;
    private $password;
    private $from;
    private $from_name;

    private $to = [];
    private $cc = [];
    private $bcc = [];
    
    private $subject;
    private $body;
    private $attachments = [];

    /**
     * Constructor to initialize SMTP settings
     * 
     * @param array|null $config Optional configuration array with keys:
     *                           - host: SMTP server host
     *                           - port: SMTP server port
     *                           - username: SMTP username
     *                           - password: SMTP password
     *                           - from: From email address
     *                           - fromname: From name
     */
    public function __construct(?array $config = null)
    {
        $this->username   = env('MAIL_USERNAME') ?? $config['username'] ?? '';
        $this->password   = env('MAIL_PASSWORD') ?? $config['password'] ?? '';
        $this->from       = env('MAIL_FROM_ADDRESS') ?? $config['from'] ?? $this->username;
        $this->from_name  = env('MAIL_FROM_NAME') ?? $config['fromname'] ?? "No-Reply";

        if (isset($config['host'])) $this->smtp_host = $config['host'];
        if (isset($config['port'])) $this->smtp_port = $config['port'];
    }

    /**
     * Sanitize input to prevent Header Injection
     */
    private function sanitize(string $string): string
    {
        return str_replace(["\r", "\n"], "", trim($string));
    }

    // --- Networking Helpers ---

    private function sendLine($fp, string $line): void
    {
        fwrite($fp, $line . "\r\n");
    }

    private function getLine($fp): string
    {
        $line = fgets($fp, 515);
        if ($line === false) throw new \RuntimeException("SMTP read failed");
        return $line;
    }

    private function getMultiline($fp): void
    {
        while (($line = fgets($fp, 515)) !== false) {
            if (substr($line, 3, 1) !== '-') break;
        }
    }

    // --- Recipient Methods ---

    /**
     * Add a recipient email address
     */
    public function to(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $this->to[] = $email;
        return $this;
    }

    /**
     * Add a CC email address
     */
    public function cc(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $this->cc[] = $email;
        return $this;
    }

    /**
     * Add a BCC email address
     */
    public function bcc(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $this->bcc[] = $email;
        return $this;
    }

    // --- Content Methods ---

    /**
     * Set the email subject
     */
    public function subject(string $subject): self
    {
        $this->subject = $this->sanitize($subject);
        return $this;
    }

    /**
     * Set the email body (HTML)
     */
    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Add an attachment file path
     */
    public function addAttachment(string $filePath): self
    {
        if (file_exists($filePath)) {
            $this->attachments[] = $filePath;
        }
        return $this;
    }

    /**
     * Set the email body as HTML
     */
    public function html(string $html): self
    {
        $this->body = $html;
        return $this;
    }

    /**
     * Set the email body as plain text (converted to HTML)
     */
    public function text(string $text): self
    {
        $this->body = nl2br(htmlspecialchars($text));
        return $this;
    }

    /**
     * Set the email body from a view template
     */
    public function view(string $viewName, ?array $data = null): self
    {
        $view = new View();
        $this->body = $view->render($viewName, $data);
        return $this;
    }

    // --- Main Logic ---

    /**
     * Send the email via SMTP
     * 
     * @return bool True on success
     * @throws \RuntimeException on failure
     */
    public function send(): bool
    {
        if (empty($this->username) || empty($this->password)) {
            throw new \RuntimeException("SMTP credentials not configured.");
        }

        // 1. Setup Secure Context (Anti-MITM)
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false
            ]
        ]);

        $errno = $errstr = null;
        $fp = @stream_socket_client(
            "tcp://{$this->smtp_host}:{$this->smtp_port}", 
            $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
        );

        if (!$fp) {
            // Log error securely instead of showing to user
            error_log("SMTP Connect Error: $errstr ($errno)");
            throw new \RuntimeException("Could not connect to Mail Server.");
        }

        // 2. Handshake & Auth
        $this->getLine($fp);
        $this->sendLine($fp, "EHLO " . gethostname());
        $this->getMultiline($fp);

        // STARTTLS
        $this->sendLine($fp, "STARTTLS");
        $this->getLine($fp);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
             throw new \RuntimeException("TLS Negotiation Failed");
        }
        
        $this->sendLine($fp, "EHLO " . gethostname());
        $this->getMultiline($fp);

        // AUTH
        $this->sendLine($fp, "AUTH LOGIN");
        $this->getLine($fp);
        $this->sendLine($fp, base64_encode($this->username));
        $this->getLine($fp);
        $this->sendLine($fp, base64_encode($this->password));
        $resp = $this->getLine($fp);
        
        if (strpos($resp, '235') === false) {
             throw new \RuntimeException("SMTP Auth Failed. Check App Password.");
        }

        // 3. Send Recipients (To + CC + BCC)
        // MAIL FROM
        $this->sendLine($fp, "MAIL FROM:<{$this->from}>");
        $this->getLine($fp);

        // RCPT TO (Send to everyone, but BCC is hidden in header later)
        $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
        if (empty($allRecipients)) throw new \RuntimeException("No recipients specified.");

        foreach ($allRecipients as $rcpt) {
            $this->sendLine($fp, "RCPT TO:<$rcpt>");
            $this->getLine($fp);
        }

        // 4. Build Payload (Data)
        $this->sendLine($fp, "DATA");
        $this->getLine($fp);

        // Generate a unique boundary for multipart
        $boundary = "dframe_" . md5(uniqid(time()));

        // --- Headers ---
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date("r") . "\r\n";
        $headers .= "From: " . $this->sanitize($this->from_name) . " <{$this->from}>\r\n";
        $headers .= "Subject: {$this->subject}\r\n";
        
        // Visible Recipients in Header
        if (!empty($this->to)) $headers .= "To: " . implode(", ", $this->to) . "\r\n";
        if (!empty($this->cc)) $headers .= "Cc: " . implode(", ", $this->cc) . "\r\n";
        // BCC header is intentionally OMITTED for privacy

        // Content-Type for Mixed (Body + Attachments)
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $headers .= "\r\n"; // End of main headers

        fwrite($fp, $headers);

        // --- Body Part ---
        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $this->body . "\r\n\r\n";
        fwrite($fp, $message);

        // --- Attachment Parts ---
        foreach ($this->attachments as $filePath) {
            if (file_exists($filePath)) {
                $fileName = basename($filePath);
                $fileData = chunk_split(base64_encode(file_get_contents($filePath)));

                $att  = "--{$boundary}\r\n";
                $att .= "Content-Type: application/octet-stream; name=\"{$fileName}\"\r\n";
                $att .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
                $att .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $att .= $fileData . "\r\n\r\n";
                
                fwrite($fp, $att);
            }
        }

        // End of Data
        fwrite($fp, "--{$boundary}--\r\n");
        fwrite($fp, ".\r\n"); // End signal
        $this->getLine($fp);

        // Quit
        $this->sendLine($fp, "QUIT");
        fclose($fp);

        return true;
    }
}