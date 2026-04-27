<?php
//   Login page for the Baseball League application.
//
//   Auth flow:
//     1. PHP connects as DB_identity (read-only, two columns only)
//        to fetch the stored bcrypt hash and role for the submitted
//        username. DB_identity has no write access anywhere.
//     2. PHP runs password_verify() entirely in memory — the
//        plaintext password never touches the database.
//     3. On success, PHP maps the role name to the correct DB
//        credentials and opens a second connection to confirm
//        the role account is reachable.
//     4. Session variables are written and the user is redirected
//        to home_page.php. All subsequent pages use $_SESSION to
//        open their own connections under the correct role account.

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  require_once('functions.php');
  UserCheck(1); // redirect to home_page.php if already logged in

  $error = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($username) || empty($password))
      {
      $error = "Please enter both username and password.";
      }
    else
      {
      // Step 1 — connect as DB_identity to retrieve the hash and role.
      // This account can only SELECT two columns from Users_accounts
      // and two columns from Roles. Nothing else.
      $db = new mysqli('localhost', 'DB_identity', 'IdentitySecret', 'Baseball');

      if ($db->connect_errno)
        {
        $error = "Database connection error. Please try again.";
        }
      else
        {
        $stmt = $db->prepare(
          "SELECT UA.User_email, UA.Password, UA.Role_type, R.Role_name
           FROM   Users_accounts UA
           JOIN   Roles R ON UA.Role_type = R.ID
           WHERE  UA.Username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0)
          {
          $error = "Invalid username or password.";
          $stmt->close();
          $db->close();
          }
        else
          {
          $stmt->bind_result($user_email, $hashed_password, $role_type, $role_name);
          $stmt->fetch();
          $stmt->close();

          // Step 2 — also fetch Team_num from Users_info while still
          // connected as DB_identity, so we can store it in the session.
          // DB_identity needs SELECT on Users_info for this column.
          $stmt2 = $db->prepare(
            "SELECT Team_num FROM Users_info WHERE Email = ?"
          );
          $stmt2->bind_param("s", $user_email);
          $stmt2->execute();
          $stmt2->store_result();
          $stmt2->bind_result($team_num);
          $stmt2->fetch();
          $stmt2->close();
          $db->close();

          // Step 3 — password_verify() runs entirely in PHP.
          // The submitted plaintext never leaves the application layer.
          if (!password_verify($password, $hashed_password))
            {
            $error = "Invalid username or password.";
            }
          else
            {
            // Step 4 — map role name to the DB account that session
            // pages will use. These credentials are the only place
            // role-to-DB-account mapping is defined.
            $role_map = [
              'observer' => ['db_user' => 'Observer', 'db_pass' => 'ObserverSecret'],
              'user'     => ['db_user' => 'User',     'db_pass' => 'UserSecret'],
              'manager'  => ['db_user' => 'Manager',  'db_pass' => 'ManagerSecret'],
            ];

            $role_key = strtolower($role_name);

            if (!isset($role_map[$role_key]))
              {
              $error = "Unrecognized role. Please contact an administrator.";
              }
            else
              {
              // Step 5 — write session. home_page.php and all other
              // authenticated pages open connections using db_user/db_pass.
              // team_id is used by User (coach) pages to scope stat queries
              // to their own team only.
              $_SESSION['username'] = $username;
              $_SESSION['email']    = $user_email;
              $_SESSION['role']     = $role_key;
              $_SESSION['team_id']  = (int)$team_num;
              $_SESSION['db_user']  = $role_map[$role_key]['db_user'];
              $_SESSION['db_pass']  = $role_map[$role_key]['db_pass'];

              header("Location: home_page.php");
              exit;
              }
            }
          }
        }
      }
    }
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Login - Baseball League</title>
  </head>
  <body>
    <h1 style = "text-align:center">Welcome to the Baseball League:</h1>
    <?php Format("texta", 10, 35, 250, "black", 2, "red", "gray", 30, 60); ?>
    <div id = "texta">
      <h2 style = "color: tan;">Login Below</h2>
      <?php if ($error): ?>
        <p style = "color: red;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <form action = "login_page.php" method = "post">
        <table>
          <tr>
            <td style = "font-size: 110%">Username:</td>
            <td><input type = "text" name = "username" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Password:</td>
            <td><input type = "password" name = "password" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td colspan = "2"><input type = "submit" value = "Login"/></td>
          </tr>
          <tr>
            <td><a href = "register.php">Register</a></td>
          </tr>
          <tr>
            <td><a href = "forgot.php">Forgot password?</a></td>
          </tr>
        </table>
      </form>
    </div>
  </body>
</html>
