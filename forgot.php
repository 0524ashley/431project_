<?php
  // TEMPORARY: Show errors while debugging.
  // After email works, you can remove these 3 lines.
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  require_once('functions.php');

  // ------------------------------------------------------------
  // Read full SMTP response, including multi-line responses.
  // Gmail often sends multi-line responses, so reading only one
  // fgets() can break the SMTP command order.
  // ------------------------------------------------------------
  function smtp_get_response($socket)
    {
    $response = '';

    while (($line = fgets($socket, 515)) !== false)
      {
      $response .= $line;

      // SMTP final response line has a space after the code, e.g. "250 OK"
      // Continued lines have a dash, e.g. "250-smtp.gmail.com"
      if (isset($line[3]) && $line[3] === ' ')
        {
        break;
        }
      }

    return $response;
    }

  function smtp_send_command($socket, $command)
    {
    fputs($socket, $command . "\r\n");
    return smtp_get_response($socket);
    }

  // ------------------------------------------------------------
  // Send password reset email using Gmail SMTP.
  //
  // IMPORTANT:
  // - $smtp_user = your Gmail address
  // - $smtp_pass = Google App Password, NOT your normal Gmail password
  // - Gmail account must have 2-Step Verification enabled
  // ------------------------------------------------------------
  function send_reset_email($to, $new_pass, &$smtp_error)
    {
    $smtp_host = "smtp.gmail.com";
    $smtp_port = 587;

    // We decide to use Google's SMTP server (2-step verification must be enabled on the account):
    $smtp_user = "estsubasa98@gmail.com";
    $smtp_pass = "yaeybwwgbxxytbnh";

    $from = $smtp_user;
    $subject = "Baseball League - Password Reset";

    $body = "Your password has been reset.\r\n\r\n"
          . "New temporary password: $new_pass\r\n\r\n"
          . "Please log in and change your password.";

    $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);

    if (!$socket)
      {
      $smtp_error = "Connection failed: $errno - $errstr";
      return false;
      }

    // Opening response should be 220
    $response = smtp_get_response($socket);
    if (substr($response, 0, 3) !== "220")
      {
      $smtp_error = "Bad opening response: " . $response;
      fclose($socket);
      return false;
      }

    // EHLO
    $response = smtp_send_command($socket, "EHLO localhost");
    if (substr($response, 0, 3) !== "250")
      {
      $smtp_error = "EHLO failed: " . $response;
      fclose($socket);
      return false;
      }

    // STARTTLS
    $response = smtp_send_command($socket, "STARTTLS");
    if (substr($response, 0, 3) !== "220")
      {
      $smtp_error = "STARTTLS failed: " . $response;
      fclose($socket);
      return false;
      }

    // Enable TLS
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
      {
      $smtp_error = "TLS failed. OpenSSL may not be enabled in PHP.";
      fclose($socket);
      return false;
      }

    // EHLO again after TLS
    $response = smtp_send_command($socket, "EHLO localhost");
    if (substr($response, 0, 3) !== "250")
      {
      $smtp_error = "EHLO after TLS failed: " . $response;
      fclose($socket);
      return false;
      }

    // AUTH LOGIN
    $response = smtp_send_command($socket, "AUTH LOGIN");
    if (substr($response, 0, 3) !== "334")
      {
      $smtp_error = "AUTH LOGIN failed: " . $response;
      fclose($socket);
      return false;
      }

    // Gmail username
    $response = smtp_send_command($socket, base64_encode($smtp_user));
    if (substr($response, 0, 3) !== "334")
      {
      $smtp_error = "Username rejected: " . $response;
      fclose($socket);
      return false;
      }

    // Gmail app password
    $response = smtp_send_command($socket, base64_encode($smtp_pass));
    if (substr($response, 0, 3) !== "235")
      {
      $smtp_error = "Password rejected: " . $response;
      fclose($socket);
      return false;
      }

    // Sender
    $response = smtp_send_command($socket, "MAIL FROM:<$from>");
    if (substr($response, 0, 3) !== "250")
      {
      $smtp_error = "MAIL FROM failed: " . $response;
      fclose($socket);
      return false;
      }

    // Recipient
    $response = smtp_send_command($socket, "RCPT TO:<$to>");
    if (substr($response, 0, 3) !== "250")
      {
      $smtp_error = "RCPT TO failed: " . $response;
      fclose($socket);
      return false;
      }

    // Message body
    $response = smtp_send_command($socket, "DATA");
    if (substr($response, 0, 3) !== "354")
      {
      $smtp_error = "DATA failed: " . $response;
      fclose($socket);
      return false;
      }

    $headers = "From: $from\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");

    $response = smtp_get_response($socket);
    if (substr($response, 0, 3) !== "250")
      {
      $smtp_error = "Message failed: " . $response;
      fclose($socket);
      return false;
      }

    smtp_send_command($socket, "QUIT");
    fclose($socket);

    return true;
    }

  // ------------------------------------------------------------
  // Page logic
  // ------------------------------------------------------------
  UserCheck(1);

  $message = '';
  $error   = '';
  $email   = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
      {
      $error = "Please enter a valid email address.";
      }
    else
      {
      // Check whether the submitted email exists.
      $db    = new mysqli('localhost', 'Observer', 'ObserverSecret', 'Baseball');
      $found = false;

      if ($db->connect_errno)
        {
        $error = "Database connection error. Please try again.";
        }
      else
        {
        $stmt = $db->prepare(
          "SELECT UA.User_email
           FROM Users_accounts UA
           JOIN Users_info UI ON UA.User_email = UI.Email
           WHERE UI.Email = ?"
        );

        if (!$stmt)
          {
          $error = "Database query error. Please try again.";
          }
        else
          {
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $stmt->store_result();
          $found = ($stmt->num_rows > 0);
          $stmt->close();
          }

        $db->close();
        }

      if ($error === '' && !$found)
        {
        $error = "No account found with that email address.";
        }
      elseif ($error === '')
        {
        // Generate temporary password.
        $new_pass = bin2hex(random_bytes(8));
        $hash     = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update password in database.
        $db = new mysqli('localhost', 'User', 'UserSecret', 'Baseball');

        if ($db->connect_errno)
          {
          $error = "Database error. Please try again.";
          }
        else
          {
          $stmt = $db->prepare(
            "UPDATE Users_accounts SET Password = ? WHERE User_email = ?"
          );

          if (!$stmt)
            {
            $error = "Database update error. Please try again.";
            }
          else
            {
            $stmt->bind_param("ss", $hash, $email);

            if (!$stmt->execute())
              {
              $error = "Password update failed. Please try again.";
              }
            else
              {
              $smtp_error = '';

              if (send_reset_email($email, $new_pass, $smtp_error))
                {
                $message = "A new password has been sent to your email address.";
                $email = '';
                }
              else
                {
                $error = "Password was reset, but email was not sent. SMTP error: " . $smtp_error;
                }
              }

            $stmt->close();
            }

          $db->close();
          }
        }
      }
    }
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Forgot Password - Baseball League</title>
  </head>

  <body>
    <h1 style = "text-align:center">Welcome to the Baseball League:</h1>

    <?php Format("forgotbox", 10, 30, 150, "black", 2, "red", "gray", 38, 40); ?>

    <div id = "forgotbox">
      <h2 style = "color: tan;">Reset Password</h2>

      <?php if ($error): ?>
        <p style = "color: red;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <?php if ($message): ?>
        <p style = "color: green;"><?= htmlspecialchars($message) ?></p>
        <p><a href = "login_page.php">Back to login</a></p>
      <?php else: ?>
        <form action = "forgot.php" method = "post">
          <table>
            <tr>
              <td>Email Address:</td>
              <td>
                <input
                  type = "text"
                  name = "email"
                  value = "<?= htmlspecialchars($email) ?>"
                  size = "25"
                  maxlength = "50"
                />
              </td>
            </tr>

            <tr>
              <td colspan = "2">
                <input type = "submit" value = "Send New Password"/>
              </td>
            </tr>
          </table>
        </form>

        <p><a href = "login_page.php">Back to login</a></p>
      <?php endif; ?>
    </div>
  </body>
</html>