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
  // Load all games via Game class
  // ------------------------------------------------------------------
  $games = Game::getAll($db);
  $db->close();

  // Flash message after delete/create
  $flash = $_GET['deleted'] ?? '';
  $flash = $flash ? "Game deleted successfully." : '';
  if (!$flash && isset($_GET['created']))
    $flash = "Game created successfully.";
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Game Management - Baseball League</title>
    <style>
      #texta
        {
        overflow-y: auto;
        }
      .top-links
        {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        }
      .top-links a
        {
        color: black;
        text-decoration: underline;
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
      td
        {
        vertical-align: top;
        border: 4px solid darkorange;
        background: lightgreen;
        color: black;
        padding: 4px 8px;
        }
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
      .btn-create
        {
        display: inline-block;
        padding: 5px 14px;
        font-size: 0.95em;
        font-weight: bold;
        text-decoration: none;
        border: 2px solid darkorange;
        background: lightgreen;
        color: black;
        cursor: pointer;
        }
      .btn-create:hover
        {
        background: darkorange;
        }
      .msg-ok
        {
        color: green;
        font-weight: bold;
        margin-bottom: 8px;
        }
      .no-games
        {
        border: 4px solid darkorange;
        background: lightgreen;
        color: black;
        padding: 6px 10px;
        font-style: italic;
        }
    </style>
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align:center; color:tan; margin-top:4px;">Game Management</h2>

      <!-- top bar: Return to Management (left) | Create New Game (right) -->
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
                   href = "update_game.php?id=<?= $g->game_id() ?>">Modify</a>
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
