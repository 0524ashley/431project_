<?php
//   Registration page for the Baseball League application.
//
//   Collects: first name, last name, email, team, username,
//   password, confirm password.
//
//   Address and phone fields have been removed per updated design.
//   All new accounts are assigned Role_type = 1 (observer) by
//   default. A Manager can promote them later.
//
//   DB account used: App_register
//     - SELECT on Teams          (populate team dropdown)
//     - SELECT on Users_info     (duplicate email check)
//     - SELECT on Users_accounts (duplicate username check)
//     - INSERT on Users_info     (create player record)
//     - INSERT on Users_accounts (create login record)

  require_once('functions.php');
  UserCheck(1); // redirect to home_page.php if already logged in

  $errors   = [];
  $username = $email = $first = $last = '';
  $team_id  = '';

  // Connect as App_register to load the team dropdown.
  // This connection is read-only at this stage.
  $db_reg = new mysqli('localhost', 'App_register', 'RegisterSecret', 'Baseball');
  $teams  = [];
  if (!$db_reg->connect_errno)
    {
    $res = $db_reg->query("SELECT ID, Name FROM Teams ORDER BY ID");
    while ($row = $res->fetch_assoc())
      $teams[] = $row;
    // Leave connection open — reused for duplicate checks and INSERT below.
    }

  if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
    $username = trim($_POST['username']         ?? '');
    $password =      $_POST['password']         ?? '';
    $confirm  =      $_POST['confirm_password'] ?? '';
    $email    = trim($_POST['email']            ?? '');
    $first    = trim($_POST['first_name']       ?? '');
    $last     = trim($_POST['last_name']        ?? '');
    $team_id  =      $_POST['team_id']          ?? '';

    // --- Validation ---

    if (strlen($username) < 3 || strlen($username) > 50)
      $errors[] = "Username must be 3-50 characters.";
    elseif (!preg_match('/^\w+$/', $username))
      $errors[] = "Username may only contain letters, numbers, and underscores.";

    if (strlen($password) < 8 || strlen($password) > 50)
      $errors[] = "Password must be 8-50 characters.";
    if ($password !== $confirm)
      $errors[] = "Passwords do not match.";

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
      $errors[] = "A valid email address is required.";
    elseif (strlen($email) > 50)
      $errors[] = "Email must be 50 characters or fewer.";

    if (empty($first) || strlen($first) > 30)
      $errors[] = "First name is required (max 30 characters).";
    if (empty($last) || strlen($last) > 30)
      $errors[] = "Last name is required (max 30 characters).";

    if (empty($team_id))
      $errors[] = "Please select a team.";

    // --- Duplicate checks (still as App_register) ---

    if (empty($errors) && !$db_reg->connect_errno)
      {
      $stmt = $db_reg->prepare(
        "SELECT Email FROM Users_info WHERE Email = ?"
      );
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0)
        $errors[] = "An account with that email already exists.";
      $stmt->close();

      if (empty($errors))
        {
        $stmt = $db_reg->prepare(
          "SELECT Username FROM Users_accounts WHERE Username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0)
          $errors[] = "That username is already taken.";
        $stmt->close();
        }
      }

    // --- Insert new user (still as App_register) ---
    //
    //   Two inserts in order:
    //     1. Users_info  — the player's personal record.
    //     2. Users_accounts — the login record, Role_type = 1 (observer).
    //
    //   password_hash() produces a bcrypt hash. The plaintext password
    //   is never stored or logged anywhere.

    if (empty($errors) && !$db_reg->connect_errno)
      {
      $hash     = password_hash($password, PASSWORD_DEFAULT);
      $team_int = (int)$team_id;

      $stmt = $db_reg->prepare(
        "INSERT INTO Users_info (Team_num, First_name, Last_name, Email)
         VALUES (?, ?, ?, ?)"
      );
      $stmt->bind_param("isss", $team_int, $first, $last, $email);

      if (!$stmt->execute())
        {
        $errors[] = "Registration failed. Please try again.";
        $stmt->close();
        }
      else
        {
        $stmt->close();

        $stmt = $db_reg->prepare(
          "INSERT INTO Users_accounts (User_email, Role_type, Username, Password)
           VALUES (?, 1, ?, ?)"
        );
        $stmt->bind_param("sss", $email, $username, $hash);

        if (!$stmt->execute())
          {
          $errors[] = "Failed to create account. Please try again.";
          $stmt->close();
          }
        else
          {
          $stmt->close();
          $db_reg->close();
          // Redirect to login on success.
          header("Location: login_page.php");
          exit;
          }
        }
      }
    }

  if (!$db_reg->connect_errno)
    $db_reg->close();
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Register - Baseball League</title>
  </head>
  <body>
    <h1 style = "text-align:center">Welcome to the Baseball League:</h1>
    <?php Format("regbox", 2, 25, 150, "black", 2, "red", "gray", 50, 94); ?>
    <div id = "regbox" style = "height: auto; padding: 10px 16px;">
      <h2 style = "color: tan;">Create an Account</h2>
      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
          <p style = "color: red;"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
      <form action = "register.php" method = "post">
        <table>
          <tr>
            <td style = "font-size: 110%">First Name:</td>
            <td><input type = "text" name = "first_name" value = "<?= htmlspecialchars($first) ?>" size = "25" maxlength = "30"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Last Name:</td>
            <td><input type = "text" name = "last_name" value = "<?= htmlspecialchars($last) ?>" size = "25" maxlength = "30"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Email:</td>
            <td><input type = "text" name = "email" value = "<?= htmlspecialchars($email) ?>" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Team:</td>
            <td>
              <select name = "team_id">
                <option value = "">-- Select a team --</option>
                <?php foreach ($teams as $t): ?>
                  <option value = "<?= (int)$t['ID'] ?>" <?= ($team_id == $t['ID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['Name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Username:</td>
            <td><input type = "text" name = "username" value = "<?= htmlspecialchars($username) ?>" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Password:</td>
            <td><input type = "password" name = "password" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td style = "font-size: 110%">Confirm Password:</td>
            <td><input type = "password" name = "confirm_password" size = "25" maxlength = "50"/></td>
          </tr>
          <tr>
            <td colspan = "2"><input type = "submit" value = "Register"/></td>
          </tr>
          <tr>
            <td colspan = "2"><a href = "login_page.php">Back to login</a></td>
          </tr>
        </table>
      </form>
    </div>
  </body>
</html>