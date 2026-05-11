<?php
// =============================================================
//  processStatisticUpdate.php
//
//  Adds or updates one player's stat row in Games_statistics
//  for a specific game (composite PK: Game_ID + Player_ID),
//  then immediately recomputes and overwrites that player's
//  Player_statistics totals via SUM() across all their
//  Games_statistics rows — so Player_statistics is always in
//  sync and never needs to be edited manually.
//
//  Access: manager (any player) OR user/coach (own team only).
//
//  Expects GET/POST:
//    game_id   — ID of the game
//    player_id — ID of the player
//
//  On POST with 'save_game_stat' button:
//    goals, assists, home_runs, time_mins, time_secs
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
  // Fetch logged-in user's display info for the menu
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
  // Load game and target player
  // ------------------------------------------------------------------
  $game   = Game::getById($db, $game_id);
  $player = User::getById($db, $player_id);

  if ($game === null || $player === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Coaches may only edit stats for their own team
  if ($role === 'user' && $player->teamId() !== (int)($_SESSION['team_id'] ?? 0))
    {
    $db->close();
    header("Location: home_page.php");
    exit;
    }

  // Load existing row for pre-fill (null = new entry)
  $stat = GameStatistic::getByGameAndPlayer($db, $game_id, $player_id);

  // ------------------------------------------------------------------
  // Handle POST — upsert game stat, then recompute career totals
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
      // ----------------------------------------------------------------
      // Recompute Player_statistics from scratch using SUM() over all
      // of this player's Games_statistics rows.
      //
      // Time arithmetic: sum everything into raw seconds, then split
      // back into normalised mins (FLOOR / 60) and secs (MOD 60).
      // ----------------------------------------------------------------
      $sync = $db->prepare("
        UPDATE Player_statistics
        SET    Total_goals              = (
                 SELECT COALESCE(SUM(Goals), 0)
                 FROM   Games_statistics WHERE Player_ID = ?
               ),
               Total_assists            = (
                 SELECT COALESCE(SUM(Assists), 0)
                 FROM   Games_statistics WHERE Player_ID = ?
               ),
               Total_home_runs          = (
                 SELECT COALESCE(SUM(Home_runs), 0)
                 FROM   Games_statistics WHERE Player_ID = ?
               ),
               Total_time_on_field_mins = (
                 SELECT FLOOR(
                   COALESCE(SUM(Time_on_field_mins * 60 + Time_on_field_secs), 0) / 60
                 )
                 FROM   Games_statistics WHERE Player_ID = ?
               ),
               Total_time_on_field_secs = (
                 SELECT MOD(
                   COALESCE(SUM(Time_on_field_mins * 60 + Time_on_field_secs), 0), 60
                 )
                 FROM   Games_statistics WHERE Player_ID = ?
               )
        WHERE  Player_ID = ?
      ");

      if ($sync)
        {
        $sync->bind_param('iiiiii',
          $player_id, $player_id, $player_id,
          $player_id, $player_id, $player_id
        );
        $sync->execute();
        $sync->close();
        }

      $success = "Game stats saved and career totals updated for "
               . htmlspecialchars($player->fullName()) . ".";
      $stat    = GameStatistic::getByGameAndPlayer($db, $game_id, $player_id);
      }
    else
      {
      $error = "Save failed. Please try again.";
      }
    }

  $db->close();

  // Pre-fill display values
  $cur_goals    = $stat ? $stat->goals()    : 0;
  $cur_assists  = $stat ? $stat->assists()  : 0;
  $cur_homeruns = $stat ? $stat->homeRuns() : 0;
  $cur_mins     = $stat ? $stat->timeMins() : 0;
  $cur_secs     = $stat ? $stat->timeSecs() : 0;
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Game Stats - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>
          Game Stats &mdash; <?= htmlspecialchars($player->fullName()) ?>
        </h2>
        <p style = "color:tan; margin:0 0 10px 0;">
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
              action = "processStatisticUpdate.php?game_id=<?= $game_id ?>&player_id=<?= $player_id ?>">
          <table style = "border-collapse:collapse; background:blue;">
            <tr>
              <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Field</th>
              <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Value</th>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Goals</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "goals" min = "0" value = "<?= $cur_goals ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Assists</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "assists" min = "0" value = "<?= $cur_assists ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Home Runs</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "homeruns" min = "0" value = "<?= $cur_homeruns ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Time (mins)</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "mins" min = "0" value = "<?= $cur_mins ?>"/>
              </td>
            </tr>
            <tr>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Time (secs)</td>
              <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
                <input type = "number" name = "secs" min = "0" max = "59" value = "<?= $cur_secs ?>"/>
              </td>
            </tr>
          </table>
          <br/>
          <button type = "submit" name = "save_game_stat" class = "btn-save">Save Game Stats</button>
        </form>

        <a class = "back-link" href = "update_statistic.php?id=<?= $player_id ?>">&larr; Back to Player Stats</a>
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
