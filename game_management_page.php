<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('Game.php');
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

  $me = $db->prepare("
    SELECT UI.First_name, UI.Last_name, T.Name
    FROM   Users_info AS UI
    LEFT JOIN Teams   AS T ON T.ID = UI.Team_num
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
  // Load all games via Game class
  // ------------------------------------------------------------------
  $games = Game::getAll($db);
  $db->close();

  // Flash messages after delete / create
  $flash = '';
  if (isset($_GET['deleted'])) $flash = "Game deleted successfully.";
  if (isset($_GET['created'])) $flash = "Game created successfully.";
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Game Management - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align:center; color:tan; margin-top:4px;">Game Management</h2>

      <div class = "top-links">
        <a href = "role_management_page.php">&larr; Return to Management</a>
        <a href = "home_page.php">&larr; Return to Home</a>
        <a class = "btn-create" href = "create_game.php">+ Create New Game</a>
      </div>

      <?php if ($flash): ?>
        <p class = "msg-ok"><?= htmlspecialchars($flash) ?></p>
      <?php endif; ?>

      <?php if (empty($games)): ?>
        <div class = "no-games">No games found.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>#</th>
            <th>Home Team</th>
            <th>Away Team</th>
            <th>Score</th>
            <th>Date</th>
            <th>Location</th>
            <th>Actions</th>
          </tr>
          <?php foreach ($games as $g): ?>
            <tr>
              <td><?= $g->game_id() ?></td>
              <td><?= htmlspecialchars($g->home_team()) ?></td>
              <td><?= htmlspecialchars($g->away_team()) ?></td>
              <td style = "text-align:center;">
                <?= $g->home_score() ?> &ndash; <?= $g->away_score() ?>
              </td>
              <td><?= htmlspecialchars($g->game_date()) ?></td>
              <td><?= htmlspecialchars($g->location()) ?></td>
              <td>
                <a class = "btn"
                   href = "update_game.php?id=<?= $g->game_id() ?>">Manage Stats</a>
                <a class = "btn-delete"
                   href = "delete_game.php?id=<?= $g->game_id() ?>"
                   onclick = "return confirm('Delete this game? This cannot be undone.');">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
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
