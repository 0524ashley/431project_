<?php
// =============================================================
//  processStatisticDelete.php
//
//  Shows a confirmation screen, then deletes one player's stat
//  row from Games_statistics for a specific game.
//  After deletion, recomputes Player_statistics career totals
//  and the game score automatically.
//
//  Access: manager (any player) OR user/coach (own team only).
//
//  Expects GET:
//    game_id   — ID of the game
//    player_id — ID of the player
//    back      — 'update_game' | 'detail' | (default: player_stats)
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  require_once('Game.php');
  require_once('GameStatistic.php');
  UserCheck(0);

  $role = $_SESSION['role'] ?? '';
  if ($role !== 'manager' && $role !== 'user')
    {
    header("Location: home_page.php");
    exit;
    }

  if (empty($_SESSION['db_user']) || empty($_SESSION['db_pass']))
    {
    header("Location: login_page.php");
    exit;
    }

  $game_id   = (int)($_REQUEST['game_id']   ?? 0);
  $player_id = (int)($_REQUEST['player_id'] ?? 0);
  $back_raw  = $_REQUEST['back'] ?? '';
  $back      = in_array($back_raw, ['update_game', 'detail']) ? $back_raw : 'player_stats';

  if ($game_id <= 0 || $player_id <= 0)
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
  // Menu display info
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
  // Load game, player, and stat row
  // ------------------------------------------------------------------
  $game   = Game::getById($db, $game_id);
  $player = User::getById($db, $player_id);
  $stat   = ($game && $player)
            ? GameStatistic::getByGameAndPlayer($db, $game_id, $player_id)
            : null;

  if ($game === null || $player === null || $stat === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Coaches may only delete stats for their own team
  if ($role === 'user' && $player->teamId() !== (int)($_SESSION['team_id'] ?? 0))
    {
    $db->close();
    header("Location: home_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Handle confirmed deletion then recompute
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']))
    {
    if (GameStatistic::delete($db, $game_id, $player_id))
      {
      GameStatistic::recomputePlayerTotals($db, $player_id);
      GameStatistic::recomputeGameScore($db, $game_id);
      $db->close();

      $redirect = match($back) {
        'update_game' => "update_game.php?id={$game_id}&gs_deleted=1",
        'detail'      => "game_detail_page.php?id={$game_id}&gs_deleted=1",
        default       => "update_statistic.php?id={$player_id}&gs_deleted=1",
      };
      header("Location: " . $redirect);
      exit;
      }
    else
      {
      $error = "Delete failed. Please try again.";
      }
    }

  $db->close();

  $back_href = match($back) {
    'update_game' => "update_game.php?id={$game_id}",
    'detail'      => "game_detail_page.php?id={$game_id}",
    default       => "update_statistic.php?id={$player_id}",
  };
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Delete Game Stat - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class = "page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "confirm-wrap">
        <h2>&#9888; Confirm Delete Game Stat</h2>

        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class = "game-detail">
          <strong>Game #:</strong> <?= $game_id ?><br/>
          <strong>Match:</strong>
            <?= htmlspecialchars($game->home_team()) ?>
            vs
            <?= htmlspecialchars($game->away_team()) ?><br/>
          <strong>Date:</strong> <?= htmlspecialchars($game->game_date()) ?><br/>
          <br/>
          <strong>Player:</strong> <?= htmlspecialchars($player->fullName()) ?><br/>
          <strong>Team:</strong>   <?= htmlspecialchars($player->teamName()) ?><br/>
          <br/>
          <strong>Goals:</strong>         <?= $stat->goals()         ?><br/>
          <strong>Assists:</strong>       <?= $stat->assists()       ?><br/>
          <strong>Home Runs:</strong>     <?= $stat->homeRuns()      ?><br/>
          <strong>Time on Field:</strong> <?= $stat->timeFormatted() ?><br/>
        </div>

        <p class = "warning-text">
          Deleting this entry will also update the player's career totals and the game score automatically.
          This action cannot be undone.
        </p>

        <form method = "post"
              action = "processStatisticDelete.php?game_id=<?= $game_id ?>&player_id=<?= $player_id ?>&back=<?= $back ?>">
          <button type = "submit" name = "confirm_delete" class = "btn-confirm">
            Yes, Delete Game Stat
          </button>
          <a class = "back-link" href = "<?= htmlspecialchars($back_href) ?>">Cancel</a>
        </form>
      </div>
    </div>

    <?php
      $button = $_POST['button'] ?? '';
      if ($button == "Open Menu")
        MenuOpen(1, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
      elseif ($button == "Close Menu")
        MenuOpen(0, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
      else
        MenuOpen(0, $menu_display_name, $menu_team_name, $_SESSION['role'] ?? '');
    ?>
  </body>
</html>
