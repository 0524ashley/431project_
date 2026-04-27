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
    <style>
      #texta
        {
        overflow-y: auto;
        }
      .section-label
        {
        color: tan;
        font-size: 1.1em;
        margin: 14px 0 4px 0;
        }
      table
        {
        border-collapse: collapse;
        width: 100%;
        background: blue;
        }
      th
        {
        vertical-align: top;
        border: 4px solid darkorange;
        background: lightgreen;
        color: black;
        padding: 4px 8px;
        }
      /* ---- action buttons ---- */
      .btn
        {
        display: inline-block;
        padding: 3px 8px;
        font-size: 0.82em;
        font-weight: bold;
        text-decoration: none;
        border: 1px solid black;
        background: lightgreen;
        color: black;
        cursor: pointer;
        }
      .btn:hover
        {
        background: darkorange;
        border-color: darkorange;
        color: black;
        }
      .btn-delete
        {
        display: inline-block;
        padding: 3px 8px;
        font-size: 0.82em;
        font-weight: bold;
        text-decoration: none;
        border: 1px solid darkred;
        background: red;
        color: white;
        cursor: pointer;
        }
      .btn-delete:hover
        {
        background: darkred;
        }
      .no-users
        {
        color: black;
        font-style: italic;
        padding: 4px 8px;
        background: lightgreen;
        border: 4px solid darkorange;
        }
      .top-links
        {
        margin-bottom: 10px;
        }
      .top-links a
        {
        color: black;
        margin-right: 14px;
        text-decoration: underline;
        }
    </style>
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
