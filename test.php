<?php
// =============================================================
//  test.php — Change Password page
//  Simple placeholder for password change functionality
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  UserCheck(0); // Redirect to login if not authenticated

  if (empty($_SESSION['db_user']) || empty($_SESSION['db_pass']) || empty($_SESSION['email']))
    {
    header("Location: login_page.php");
    exit;
    }

  $success = '';
  $error   = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']))
    {
    $current_pass = $_POST['current_pass'] ?? '';
    $new_pass     = $_POST['new_pass'] ?? '';
    $confirm_pass = $_POST['confirm_pass'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass))
      {
      $error = "All fields are required.";
      }
    else if ($new_pass !== $confirm_pass)
      {
      $error = "New passwords do not match.";
      }
    else if (strlen($new_pass) < 6)
      {
      $error = "New password must be at least 6 characters.";
      }
    else
      {
      // Verify current password
      $db = new mysqli('localhost', 'DB_identity', 'IdentitySecret', 'Baseball');
      if ($db->connect_errno)
        {
        $error = "Database connection error.";
        }
      else
        {
        $stmt = $db->prepare("
          SELECT Password FROM Users_accounts WHERE User_email = ?
        ");
        if ($stmt)
          {
          $stmt->bind_param('s', $_SESSION['email']);
          $stmt->execute();
          $stmt->bind_result($hash);
          $stmt->fetch();
          $stmt->close();

          if (!password_verify($current_pass, $hash))
            {
            $error = "Current password is incorrect.";
            }
          else
            {
            // Update password
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $update_stmt = $db->prepare("
              UPDATE Users_accounts SET Password = ? WHERE User_email = ?
            ");
            if ($update_stmt)
              {
              $update_stmt->bind_param('ss', $new_hash, $_SESSION['email']);
              if ($update_stmt->execute())
                {
                $success = "Password changed successfully!";
                }
              else
                {
                $error = "Failed to update password.";
                }
              $update_stmt->close();
              }
            else
              {
              $error = "Database error.";
              }
            }
          }
        $db->close();
        }
      }
    }

  $menu_display_name = $_SESSION['username'] ?? 'User';
  $menu_team_name    = '';
  $role              = $_SESSION['role'] ?? 'observer';

  $db = new mysqli('localhost', $_SESSION['db_user'], $_SESSION['db_pass'], 'Baseball');
  if (!$db->connect_errno)
    {
    $me_stmt = $db->prepare("
      SELECT UI.First_name, UI.Last_name, T.Name
      FROM   Users_info AS UI
      JOIN   Teams      AS T  ON T.ID = UI.Team_num
      WHERE  UI.Email = ?
    ");
    if ($me_stmt)
      {
      $me_stmt->bind_param('s', $_SESSION['email']);
      $me_stmt->execute();
      $me_stmt->bind_result($me_first, $me_last, $me_team);
      if ($me_stmt->fetch())
        {
        $menu_display_name = $me_first . ' ' . $me_last;
        $menu_team_name    = $me_team;
        }
      $me_stmt->close();
      }
    $db->close();
    }
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Change Password - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>
    <?php MenuOpen(false, $menu_display_name, $menu_team_name, $role); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Change Password</h2>

        <?php if (!empty($success)): ?>
          <p class = "msg-ok"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method = "post">
          <table>
            <tr>
              <td>Current Password:</td>
              <td><input type = "password" name = "current_pass" required></td>
            </tr>
            <tr>
              <td>New Password:</td>
              <td><input type = "password" name = "new_pass" required></td>
            </tr>
            <tr>
              <td>Confirm Password:</td>
              <td><input type = "password" name = "confirm_pass" required></td>
            </tr>
          </table>
          <button type = "submit" name = "change_password" class = "btn-save">Change Password</button>
        </form>

        <a href = "home_page.php" class = "back-link">← Back to Home</a>
      </div>
    </div>
  </body>
</html>
