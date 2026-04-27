<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  require_once('PlayerStatistic.php');
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

  $error   = '';
  $success = '';

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
  // Load target player identity (name, team) via User class
  // Load target player stats via PlayerStatistic class
  // ------------------------------------------------------------------
  $player = User::getById($db, $player_id);

  if ($player === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  $stat = PlayerStatistic::getById($db, $player_id);

  if ($stat === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Handle POST — update via PlayerStatistic class
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stats']))
    {
    $data = [
      'goals'     => (int)($_POST['goals']    ?? 0),
      'assists'   => (int)($_POST['assists']  ?? 0),
      'home_runs' => (int)($_POST['homeruns'] ?? 0),
      'time_mins' => (int)($_POST['mins']     ?? 0),
      'time_secs' => (int)($_POST['secs']     ?? 0),
    ];

    if (PlayerStatistic::update($db, $player_id, $data))
      {
      $success = "Stats updated for " . htmlspecialchars($player->fullName()) . ".";
      $stat    = PlayerStatistic::getById($db, $player_id); // reload fresh values
      }
    else
      {
      $error = "Update failed. Please try again.";
      }
    }

  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Change Stats - Baseball League</title>
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
      .form-wrap input[type=number]
        {
        width: 80px;
        padding: 3px 6px;
        border: 1px solid black;
        background: lightgreen;
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
      .msg-ok  { color: green;  margin-bottom: 8px; font-weight: bold; }
      .msg-err { color: red;    margin-bottom: 8px; font-weight: bold; }
    </style>
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Change Stats: <?= htmlspecialchars($player->fullName()) ?></h2>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method = "post" action = "update_statistic.php?id=<?= $player_id ?>">
          <table style = "border-collapse:collapse; background:blue;">
            <tr>
              <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Field</th>
              <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Value</th>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Goals</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "goals" min = "0"
                       value = "<?= $stat->goals() ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Assists</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "assists" min = "0"
                       value = "<?= $stat->assists() ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Home Runs</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "homeruns" min = "0"
                       value = "<?= $stat->home_runs() ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Time (mins)</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "mins" min = "0"
                       value = "<?= explode(':', $stat->time_on_field())[0] ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Time (secs)</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "secs" min = "0" max = "59"
                       value = "<?= explode(':', $stat->time_on_field())[1] ?>"/>
              </td>
            </tr>
          </table>
          <br/>
          <button type = "submit" name = "save_stats" class = "btn-save">Save Changes</button>
        </form>

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
