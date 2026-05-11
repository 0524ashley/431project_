<?php
//   This page allows users who have forgotten their password to request
// a new one. After submitting their registered email address, a random
// temporary password is generated, hashed with bcrypt, and stored in
// the database. The plaintext temporary password is then sent to the
// user via email so they can log back in.

  require_once('functions.php');
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
      //   Connect as Observer to look up whether the submitted email belongs
      // to an existing account in Users_accounts. No data is modified at
      // this stage, so read-only Observer credentials are appropriate.
      $db    = new mysqli('localhost', 'Observer', 'ObserverSecret', 'Baseball');
      $found = false;

      if (!$db->connect_errno)
        {
        $stmt = $db->prepare(
          "SELECT UA.User_email
           FROM Users_accounts UA
           JOIN Users_info UI ON UA.User_email = UI.Email
           WHERE UI.Email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $found = ($stmt->num_rows > 0);
        $stmt->close();
        $db->close();
        }

      if (!$found)
        {
        $error = "No account found with that email address.";
        }
      else
        {
        //   Generate a random 16-character temporary password and hash it
        // with bcrypt. The User account is used for the UPDATE since it
        // holds the required write privileges on Users_accounts. The
        // plaintext temporary password is then emailed to the user.
        $new_pass = bin2hex(random_bytes(8));
        $hash     = password_hash($new_pass, PASSWORD_DEFAULT);

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
          $stmt->bind_param("ss", $hash, $email);
          $stmt->execute();
          $stmt->close();
          $db->close();

          $subject = "Baseball League - Password Reset";
          $body    = "Your password has been reset.\r\n\r\n"
                   . "New temporary password: $new_pass\r\n\r\n"
                   . "Please log in and change your password.";
          mail($email, $subject, $body);

          $message = "A new password has been sent to your email address.";
          $email   = '';
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
              <td><input type = "text" name = "email" value = "<?= htmlspecialchars($email) ?>" size = "25" maxlength = "30"/></td>
            </tr>
            <tr>
              <td colspan = "2"><input type = "submit" value = "Send New Password"/></td>
            </tr>
          </table>
        </form>
        <p><a href = "login_page.php">Back to login</a></p>
      <?php endif; ?>
    </div>
  </body>
</html>
