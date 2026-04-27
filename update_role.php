<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
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

  $player_id   = (int)($_REQUEST['id']         ?? 0);
  $change_team = (int)($_REQUEST['change_team'] ?? 0); // 1 = team-change mode
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

  $error   = '';
  $success = '';

  // ------------------------------------------------------------------
  // Fetch logged-in manager's display info for the menu
  // ------------------------------------------------------------------
  $menu_display_name = $_SESSION['username'] ?? 'User';
  $menu_team_name    = '';

  $me = $db->prepare("
    SELECT UI.First_name, UI.Last_name, T.Name
    FROM   Users_info AS UI
    JOIN   Teams      AS T ON T.ID = UI.Team_num
    WHERE  UI.Email = ?
  ");
  if ($me)
    {
    $me->bind_param('s', $_SESSION['email']);
    $me->execute();
    $me->bind_result($me_first, $me_last, $me_team);
    if ($me->fetch())
      {
      $menu_display_name = $me_first . ' ' . $me_last;
      $menu_team_name    = $me_team;
      }
    $me->close();
    }

  // ------------------------------------------------------------------
  // Load target user
  // ------------------------------------------------------------------
  $target = null;

  $load = $db->prepare("
    SELECT UI.First_name, UI.Last_name, UI.Email,
           UI.Team_num, T.Name AS team_name,
           UA.Role_type, R.Role_name
    FROM   Users_info     AS UI
    JOIN   Users_accounts AS UA ON UA.User_email = UI.Email
    JOIN   Teams          AS T  ON T.ID          = UI.Team_num
    JOIN   Roles          AS R  ON R.ID          = UA.Role_type
    WHERE  UI.ID_num = ?
  ");
  if ($load)
    {
    $load->bind_param('i', $player_id);
    $load->execute();
    $load->bind_result($t_first, $t_last, $t_email,
                       $t_team_id, $t_team_name,
                       $t_role_type, $t_role_name);
    if ($load->fetch())
      {
      $target = [
        'name'      => $t_first . ' ' . $t_last,
        'email'     => $t_email,
        'team_id'   => $t_team_id,
        'team_name' => $t_team_name,
        'role_type' => $t_role_type,
        'role_name' => $t_role_name,
      ];
      }
    $load->close();
    }

  if ($target === null || $target['role_type'] == 3)
    {
    header("Location: role_management_page.php");
    exit;
    }

  // Load roles and teams for dropdowns
  $roles = [];
  $res = $db->query("SELECT ID, Role_name FROM Roles ORDER BY ID");
  while ($row = $res->fetch_assoc())
    $roles[] = $row;

  $teams = [];
  $res = $db->query("SELECT ID, Name FROM Teams ORDER BY ID");
  while ($row = $res->fetch_assoc())
    $teams[] = $row;

  // ------------------------------------------------------------------
  // Handle POST
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
    if (isset($_POST['save_role']) && !$change_team)
      {
      $new_role = (int)($_POST['role_type'] ?? 1);
      if ($new_role < 1 || $new_role > 2)
        {
        $error = "Invalid role selected.";
        }
      else
        {
        $upd = $db->prepare(
          "UPDATE Users_accounts SET Role_type = ? WHERE User_email = ?"
        );
        if ($upd)
          {
          $upd->bind_param('is', $new_role, $target['email']);
          if ($upd->execute())
            {
            $success = "Role updated for " . htmlspecialchars($target['name']) . ".";
            $target['role_type'] = $new_role;
            foreach ($roles as $r)
              if ((int)$r['ID'] === $new_role) $target['role_name'] = $r['Role_name'];
            }
          else
            {
            $error = "Update failed. Please try again.";
            }
          $upd->close();
          }
        }
      }
    elseif (isset($_POST['save_team']) && $change_team)
      {
      $new_team = (int)($_POST['team_id'] ?? 1);
      $upd = $db->prepare(
        "UPDATE Users_info SET Team_num = ? WHERE ID_num = ?"
      );
      if ($upd)
        {
        $upd->bind_param('ii', $new_team, $player_id);
        if ($upd->execute())
          {
          $success = "Team updated for " . htmlspecialchars($target['name']) . ".";
          $target['team_id'] = $new_team;
          foreach ($teams as $t)
            if ((int)$t['ID'] === $new_team) $target['team_name'] = $t['Name'];
          }
        else
          {
          $error = "Update failed. Please try again.";
          }
        $upd->close();
        }
      }
    }

  $db->close();

  $page_title = $change_team ? "Change Team" : "Change Role";
?>
<!DOCTYPE html>
<html>
  <head>
    <title><?= $page_title ?> - Baseball League</title>
    <style>
      #texta
        {
        overflow-y: auto;
        }
      .form-wrap
        {
        margin: 14px 10px;
        }
      .form-wrap h2
        {
        color: tan;
        margin-top: 0;
        }
      .form-wrap table td
        {
        padding: 5px 8px;
        color: black;
        }
      .form-wrap select
        {
        padding: 3px 6px;
        border: 1px solid black;
        background: lightgreen;
        min-width: 140px;
        }
      .current-val
        {
        color: black;
        font-size: 0.92em;
        margin-bottom: 10px;
        }
      .btn-save
        {
        padding: 5px 16px;
        background: lightgreen;
        color: black;
        border: 2px solid darkorange;
        font-weight: bold;
        cursor: pointer;
        }
      .btn-save:hover
        {
        background: darkorange;
        }
      .back-link
        {
        display: inline-block;
        margin-top: 10px;
        color: black;
        text-decoration: underline;
        }
      .msg-ok  { color: green; margin-bottom: 8px; font-weight: bold; }
      .msg-err { color: red;   margin-bottom: 8px; font-weight: bold; }
    </style>
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2><?= $page_title ?>: <?= htmlspecialchars($target['name']) ?></h2>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (!$change_team): ?>

          <p class = "current-val">
            Current role: <strong><?= htmlspecialchars(ucfirst($target['role_name'])) ?></strong>
            &bull; Team: <?= htmlspecialchars($target['team_name']) ?>
          </p>

          <form method = "post" action = "update_role.php?id=<?= $player_id ?>">
            <table style = "border-collapse:collapse; background:blue;">
              <tr>
                <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">New Role</th>
                <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Select</th>
              </tr>
              <tr>
                <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Role:</td>
                <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                  <select name = "role_type">
                    <?php foreach ($roles as $r): ?>
                      <?php if ((int)$r['ID'] === 3) continue; ?>
                      <option value = "<?= (int)$r['ID'] ?>"
                        <?= ((int)$r['ID'] === (int)$target['role_type']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($r['Role_name'])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </table>
            <br/>
            <button type = "submit" name = "save_role" class = "btn-save">Save Role</button>
          </form>

        <?php else: ?>

          <p class = "current-val">
            Current team: <strong><?= htmlspecialchars($target['team_name']) ?></strong>
            &bull; Role: <?= htmlspecialchars(ucfirst($target['role_name'])) ?>
          </p>

          <form method = "post" action = "update_role.php?id=<?= $player_id ?>&change_team=1">
            <table style = "border-collapse:collapse; background:blue;">
              <tr>
                <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">New Team</th>
                <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Select</th>
              </tr>
              <tr>
                <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Team:</td>
                <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                  <select name = "team_id">
                    <?php foreach ($teams as $t): ?>
                      <option value = "<?= (int)$t['ID'] ?>"
                        <?= ((int)$t['ID'] === (int)$target['team_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['Name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </table>
            <br/>
            <button type = "submit" name = "save_team" class = "btn-save">Save Team</button>
          </form>

        <?php endif; ?>

        <a class = "back-link" href = "role_management_page.php">&larr; Back to Management</a>
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
