<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  UserCheck(0);

  // ------------------------------------------------------------------
  // Access control:
  //   manager (role=3) — sees all users, full action set
  //   coach   (role=2) — sees own team players only, limited actions
  //
  // DB enforcement:
  //   The 'User' MySQL credential physically cannot DELETE from
  //   Users_info or UPDATE Users_accounts.Role_type. Any attempt
  //   by a coach to reach a write path via URL manipulation will be
  //   rejected by MySQL before any data changes.
  //   Row-level scope (own team only) is application-enforced —
  //   documented design constraint; see README.
  // ------------------------------------------------------------------
  $role       = $_SESSION['role'] ?? '';
  $is_manager = ($role === 'manager');
  $is_coach   = ($role === 'user');

  if (!$is_manager && !$is_coach)
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

  // Logged-in user's display info for the menu
  $menu_display_name = $_SESSION['username'] ?? 'User';
  $menu_team_name    = '';

  $me = User::getByEmail($db, $_SESSION['email'] ?? '');
  if ($me)
    {
    $menu_display_name = $me->fullName();
    $menu_team_name    = $me->teamName();
    }

  $coach_team_id = (int)($_SESSION['team_id'] ?? 0);

  // Load all users then filter for coach view
  $all_users = User::getAll($db);
  $db->close();

  $users = [];
  foreach ($all_users as $u)
    {
    if ($is_coach)
      {
      // Coach only sees role-1 players on their own team
      if ($u->roleType() === 1 && $u->teamId() === $coach_team_id)
        $users[1][] = $u;
      }
    else
      {
      $users[$u->roleType()][] = $u;
      }
    }

  // Flash message after delete
  $flash = '';
  if (isset($_GET['deleted']))
    $flash = "Account for " . htmlspecialchars($_GET['deleted']) . " deleted.";


  // ------------------------------------------------------------------
  // render_user_row()
  //
  // Manager button set (role < 3 targets only):
  //   Change Info | Change Stats (players) | Change Role |
  //   Change Team (coaches) | Delete Account
  //
  // Coach button set (own team players only):
  //   Change Info | Change Stats | Remove from Team
  //
  // "Remove from Team" sets Team_num = 1 (N/A) — account and all
  // statistics are kept intact.
  // ------------------------------------------------------------------
  function render_user_row(User $u, bool $is_manager)
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

    if ($is_manager)
      {
      if ($role < 3)
        {
        echo "<a class='btn btn-action'
                 href='update_role.php?id={$id}&change_info=1'>Change Info</a> ";

        if ($role === 1)
          echo "<a class='btn btn-action'
                   href='update_statistic.php?id={$id}'>Change Stats</a> ";

        echo "<a class='btn btn-action'
                 href='update_role.php?id={$id}'>Change Role</a> ";

        if ($role === 2)
          echo "<a class='btn btn-action'
                   href='update_role.php?id={$id}&change_team=1'>Change Team</a> ";

        echo "<a class='btn btn-delete'
                 href='delete_user.php?id={$id}'
                 onclick=\"return confirm('Delete account for {$name}? This cannot be undone.');\">Delete Account</a>";
        }
      }
    else
      {
      // Coach — limited action set, DB rejects any escalation attempt
      echo "<a class='btn btn-action'
               href='update_role.php?id={$id}&change_info=1'>Change Info</a> ";

      echo "<a class='btn btn-action'
               href='update_statistic.php?id={$id}'>Change Stats</a> ";

      echo "<a class='btn btn-delete'
               href='update_role.php?id={$id}&remove_from_team=1'
               onclick=\"return confirm('Remove {$name} from team? Their account and stats are kept.');\">Remove from Team</a>";
      }

    echo "</td>";
    echo "</tr>";
    }
?>
<!DOCTYPE html>
<html>
  <head>
    <title><?= $is_manager ? 'Management' : 'My Team' ?> - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align:center; color:tan; margin-top:4px;">
        <?= $is_manager
              ? 'User Management'
              : 'My Team &mdash; ' . htmlspecialchars($menu_team_name) ?>
      </h2>

      <div class = "top-links">
        <a href = "home_page.php">&larr; Return to Home</a>
        <?php if ($is_manager): ?>
          <a href = "game_management_page.php">Go to Game Management &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if ($flash): ?>
        <p class = "msg-ok"><?= $flash ?></p>
      <?php endif; ?>

      <?php
        $sections = $is_manager
          ? [3 => 'Managers', 2 => 'Coaches', 1 => 'Players']
          : [1 => 'Players'];

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
              <?php render_user_row($u, $is_manager); ?>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>

      <?php endforeach; ?>
    </div>

    <?php
      $button = $_POST['button'] ?? '';
      if ($button == "Open Menu")
        MenuOpen(1, $menu_display_name, $menu_team_name, $role);
      elseif ($button == "Close Menu")
        MenuOpen(0, $menu_display_name, $menu_team_name, $role);
      else
        MenuOpen(0, $menu_display_name, $menu_team_name, $role);
    ?>
  </body>
</html>
