<?php
// =============================================================
//  processStatisticUpdate.php
//
//  Adds or updates one player's stat row in Games_statistics
//  for a specific game, then automatically recomputes that
//  player's Player_statistics career totals and the game score.
//
//  Access: manager (any player) OR user/coach (own team only).
//
//  Expects GET/POST:
//    game_id   — ID of the game
//    player_id — ID of the player
//    back      — 'update_game' | 'detail' | (default: player_stats)
//
//  On POST with 'save_game_stat':
//    goals, assists, homeruns, mins, secs
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

  $error   = '';
  $success = '';

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
  // Load game and player
  // ------------------------------------------------------------------
  $game   = Game::getById($db, $game_id);
  $player = User::getById($db, $player_id);

  if ($game === null || $player === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Coaches may only edit stats for players on their own team
  if ($role === 'user' && $player->teamId() !== (int)($_SESSION['team_id'] ?? 0))
    {
    $db->close();
    header("Location: home_page.php");
    exit;
    }

  // Load existing row (null = new entry; form pre-fills zeros)
  $stat = GameStatistic::getByGameAndPlayer($db, $game_id, $player_id);

  // ------------------------------------------------------------------
  // Handle POST — upsert then recompute
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_game_stat']))
    {
    $data = [
      'goals'     => (int)($_POST['goals']    ?? 0),
      'assists'   => (int)($_POST['assists']  ?? 0),
      'home_runs' => (int)($_POST['homeruns'] ?? 0),
      'time_mins' => (int)($_POST['mins']     ?? 0),
      'time_secs' => (int)($_POST['secs']     ?? 0),
    ];

    if (GameStatistic::upsert($db, $game_id, $player_id, $data))
      {
      GameStatistic::recomputePlayerTotals($db, $player_id);
      GameStatistic::recomputeGameScore($db, $game_id);

      // Redirect back to the calling page
      $db->close();
      if ($back === 'update_game')
        { header("Location: update_game.php?id={$game_id}&gs_saved=1"); exit; }
      if ($back === 'detail')
        { header("Location: game_detail_page.php?id={$game_id}&gs_saved=1"); exit; }

      header("Location: update_statistic.php?id={$player_id}&gs_saved=1");
      exit;
      }
    else
      {
      $error = "Save failed. Please try again.";
      }
    }

  $db->close();

  // Pre-fill values
  $cur_goals    = $stat ? $stat->goals()    : 0;
  $cur_assists  = $stat ? $stat->assists()  : 0;
  $cur_homeruns = $stat ? $stat->homeRuns() : 0;
  $cur_mins     = $stat ? $stat->timeMins() : 0;
  $cur_secs     = $stat ? $stat->timeSecs() : 0;

  // Back-link target
  $back_href = match($back) {
    'update_game' => "update_game.php?id={$game_id}",
    'detail'      => "game_detail_page.php?id={$game_id}",
    default       => "update_statistic.php?id={$player_id}",
  };
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Game Stats - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class = "page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Game Stats &mdash; <?= htmlspecialchars($player->fullName()) ?></h2>

        <p class = "game-subtitle">
          Game #<?= $game_id ?>:
          <?= htmlspecialchars($game->home_team()) ?>
          vs
          <?= htmlspecialchars($game->away_team()) ?>
          &bull; <?= htmlspecialchars($game->game_date()) ?>
        </p>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method = "post"
              action = "processStatisticUpdate.php?game_id=<?= $game_id ?>&player_id=<?= $player_id ?>&back=<?= $back ?>">
          <table class = "stat-table">
            <tr>
              <th>Field</th>
              <th>Value</th>
            </tr>
            <tr>
              <td>Goals</td>
              <td><input type = "number" name = "goals"    min = "0" value = "<?= $cur_goals    ?>"/></td>
            </tr>
            <tr>
              <td>Assists</td>
              <td><input type = "number" name = "assists"  min = "0" value = "<?= $cur_assists  ?>"/></td>
            </tr>
            <tr>
              <td>Home Runs</td>
              <td><input type = "number" name = "homeruns" min = "0" value = "<?= $cur_homeruns ?>"/></td>
            </tr>
            <tr>
              <td>Time (mins)</td>
              <td><input type = "number" name = "mins"     min = "0" value = "<?= $cur_mins     ?>"/></td>
            </tr>
            <tr>
              <td>Time (secs)</td>
              <td><input type = "number" name = "secs"     min = "0" max = "59" value = "<?= $cur_secs ?>"/></td>
            </tr>
          </table>
          <br/>
          <button type = "submit" name = "save_game_stat" class = "btn-save">Save Game Stats</button>
        </form>

        <a class = "back-link" href = "<?= htmlspecialchars($back_href) ?>">&larr; Back</a>
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
