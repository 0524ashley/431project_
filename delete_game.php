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

  $game_id = (int)($_REQUEST['id'] ?? 0);
  if ($game_id <= 0)
    {
    header("Location: game_management_page.php");
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

  // Load game via Game class
  $game = Game::getById($db, $game_id);
  if ($game === null)
    {
    $db->close();
    header("Location: game_management_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Handle confirmed deletion via Game class
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']))
    {
    if (Game::delete($db, $game_id))
      {
      $db->close();
      header("Location: game_management_page.php?deleted=1");
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
    <title>Delete Game - Baseball League</title>
    <style>
      #texta
        {
        overflow-y: auto;
        }
      .confirm-wrap
        {
        margin: 14px 10px;
        }
      .confirm-wrap h2
        {
        color: tan;
        margin-top: 0;
        }
      .game-detail
        {
        border: 4px solid darkorange;
        background: lightgreen;
        color: black;
        padding: 10px 14px;
        margin: 10px 0;
        line-height: 1.8em;
        display: inline-block;
        min-width: 300px;
        }
      .warning-text
        {
        color: red;
        font-weight: bold;
        margin: 10px 0;
        }
      .btn-confirm
        {
        padding: 5px 16px;
        background: red;
        color: white;
        border: 2px solid darkred;
        font-weight: bold;
        cursor: pointer;
        }
      .btn-confirm:hover
        {
        background: darkred;
        }
      .back-link
        {
        display: inline-block;
        margin-left: 12px;
        color: black;
        text-decoration: underline;
        }
      .msg-err
        {
        color: red;
        font-weight: bold;
        margin-bottom: 8px;
        }
    </style>
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "confirm-wrap">
        <h2>&#9888; Confirm Delete Game</h2>

        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class = "game-detail">
          <strong>Game #:</strong> <?= $game->game_id() ?><br/>
          <strong>Home Team:</strong> <?= htmlspecialchars($game->home_team()) ?><br/>
          <strong>Away Team:</strong> <?= htmlspecialchars($game->away_team()) ?><br/>
          <strong>Score:</strong> <?= $game->home_score() ?> &ndash; <?= $game->away_score() ?><br/>
          <strong>Date:</strong> <?= htmlspecialchars($game->game_date()) ?><br/>
          <strong>Location:</strong> <?= htmlspecialchars($game->location()) ?>
        </div>

        <p class = "warning-text">
          This will permanently delete the game record. This action cannot be undone.
        </p>

        <form method = "post" action = "delete_game.php?id=<?= $game_id ?>">
          <button type = "submit" name = "confirm_delete" class = "btn-confirm">
            Yes, Delete Game
          </button>
          <a class = "back-link" href = "game_management_page.php">Cancel</a>
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
