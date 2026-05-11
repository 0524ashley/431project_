<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  UserCheck(0);

  if (($_SESSION['role'] ?? '') !== 'manager')
    {
    header("Location: home_page.php");
    exit;
    }

  if (empty($_SESSION['db_user']) || empty($_SESSION['db_pass']))
    {
    header("Location: login_page.php");
    exit;
    }

  $player_id = (int)($_REQUEST['id'] ?? 0);
  if ($player_id <= 0)
    {
    header("Location: role_management_page.php");
    exit;
    }

  $db = new mysqli('localhost', $_SESSION['db_user'], $_SESSION['db_pass'], 'Baseball');
  if ($db->connect_errno)
    {
    header("Location: login_page.php");
    exit;
    }

  $error = '';

  // ------------------------------------------------------------------
  // Fetch logged-in manager's display info for the menu
  // ------------------------------------------------------------------
  $menu_display_name = $_SESSION['username'] ?? 'User';
  $menu_team_name    = '';

  $me = User::getByEmail($db, $_SESSION['email'] ?? '');
  if ($me)
    {
    $menu_display_name = $me->fullName();
    $menu_team_name    = $me->teamName();
    }

  // ------------------------------------------------------------------
  // Load target user via Player class
  // ------------------------------------------------------------------
  $target = User::getById($db, $player_id);

  if ($target === null)
    {
    header("Location: role_management_page.php");
    exit;
    }

  // Block deleting a manager
  if (strtolower($target->roleName()) === 'manager')
    {
    header("Location: role_management_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Handle confirmed deletion via Player class
  // CASCADE removes Users_accounts + Users_statistics automatically
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']))
    {
    if (User::delete($db, $player_id))
      {
      $db->close();
      header("Location: role_management_page.php?deleted=" . urlencode($target->fullName()));
      exit;
      }
    else
      {
      $error = "Delete failed. Please try again.";
      }
    }

  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Delete Account - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "confirm-wrap">
        <h2>&#9888; Confirm Delete Account</h2>

        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class = "user-detail">
          <strong>Name:</strong> <?= htmlspecialchars($target->fullName()) ?><br/>
          <strong>Username:</strong> @<?= htmlspecialchars($target->username()) ?><br/>
          <strong>Email:</strong> <?= htmlspecialchars($target->email()) ?><br/>
          <strong>Team:</strong> <?= htmlspecialchars($target->teamName()) ?><br/>
          <strong>Role:</strong> <?= htmlspecialchars(ucfirst($target->roleName())) ?>
        </div>

        <p class = "warning-text">
          This will permanently delete the account and all associated statistics.
          This action cannot be undone.
        </p>

        <form method = "post" action = "delete_user.php?id=<?= $player_id ?>">
          <button type = "submit" name = "confirm_delete" class = "btn-confirm">
            Yes, Delete Account
          </button>
          <a class = "back-link" href = "role_management_page.php">Cancel</a>
        </form>
      </div>
    </div>

    <?php
      $button = $_POST['button'] ?? '';
      if ($button == "Open Menu")
        {
        MenuOpen(1, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
        }
      elseif ($button == "Close Menu")
        {
        MenuOpen(0, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
        }
      else
        {
        MenuOpen(0, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
        }
    ?>
  </body>
</html>
