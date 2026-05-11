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

  $db = new mysqli('localhost', $_SESSION['db_user'], $_SESSION['db_pass'], 'Baseball');
  if ($db->connect_errno)
    {
    header("Location: login_page.php");
    exit;
    }

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
  // Fetch all users via User class — grouped by role_type
  // ------------------------------------------------------------------
  $all_players = User::getAll($db);
  $db->close();

  $users = [];
  foreach ($all_players as $p)
    $users[$p->roleType()][] = $p;

  // ------------------------------------------------------------------
  // Helper: one user row with action buttons
  // ------------------------------------------------------------------
  function render_user_row(User $u)
    {
    $id   = $u->id();
    $name = htmlspecialchars($u->fullName());
    $team = htmlspecialchars($u->teamName());
    $role = $u->roleType();

    echo "<tr>";
    echo "<td style='vertical-align:top; border:4px solid darkorange; background:lightgreen;'>"
         . $name . "</td>";
    echo "<td style='vertical-align:top; border:4px solid darkorange; background:lightgreen;'>"
         . "@" . htmlspecialchars($u->username()) . "</td>";
    echo "<td style='vertical-align:top; border:4px solid darkorange; background:lightgreen;'>"
         . $team . "</td>";

    echo "<td style='vertical-align:top; border:4px solid darkorange; background:lightgreen;'>";
    if ($role < 3)
      {
      echo "<a class='btn btn-action'
               href='update_role.php?id={$id}'>Change Role</a> ";

      if ($role === 1)
        {
        echo "<a class='btn btn-action'
                 href='update_statistic.php?id={$id}'>Change Stats</a> ";
        }

      if ($role === 2)
        {
        echo "<a class='btn btn-action'
                 href='update_role.php?id={$id}&change_team=1'>Change Team</a> ";
        }

      echo "<a class='btn btn-delete'
               href='delete_user.php?id={$id}'
               onclick=\"return confirm('Delete account for {$name}? This cannot be undone.');\">Delete Account</a>";
      }
    echo "</td>";
    echo "</tr>";
    }
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Management - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align:center; color:tan; margin-top:4px;">User Management</h2>

      <div class = "top-links">
        <a href = "home_page.php">&larr; Return to Home</a>
        <a href = "game_management_page.php">&larr; Go to Game Management</a>
      </div>

      <?php
        $sections = [
          3 => 'Managers',
          2 => 'Coaches',
          1 => 'Players',
        ];
        foreach ($sections as $role_type => $label):
      ?>

        <h3 class = "section-label"><?= $label ?></h3>

        <?php if (empty($users[$role_type])): ?>
          <div class = "no-users">No <?= strtolower($label) ?> found.</div>
        <?php else: ?>
          <table>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Team</th>
              <th>Actions</th>
            </tr>
            <?php foreach ($users[$role_type] as $u): ?>
              <?php render_user_row($u); ?>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>

      <?php endforeach; ?>
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
