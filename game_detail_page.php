<?php
// =============================================================
//  game_detail_page.php
//
//  Shows the full stat breakdown for a single game.
//  Accessible to all logged-in roles (observer, user, manager).
//
//  Expects GET:
//    id — Game_ID to display
//
//  Displays:
//    - Game header (teams, score, date, location)
//    - Two side-by-side sections: Home team players | Away team players
//    - Each section lists player name, goals, assists, home runs, time
//    - Score is the sum of Goals from each team's players
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('Game.php');
  require_once('GameStatistic.php');
  UserCheck(0); // must be logged in

  if (empty($_SESSION['db_user']) || empty($_SESSION['db_pass']))
    {
    header("Location: login_page.php");
    exit;
    }

  $game_id = (int)($_GET['id'] ?? 0);
  if ($game_id <= 0)
    {
    header("Location: game_page.php");
    exit;
    }

  $db = new mysqli('localhost', $_SESSION['db_user'], $_SESSION['db_pass'], 'Baseball');
  if ($db->connect_errno)
    {
    header("Location: login_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Fetch logged-in user's display info for the menu
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
  // Load game details
  // ------------------------------------------------------------------
  $game = Game::getById($db, $game_id);
  if ($game === null)
    {
    $db->close();
    header("Location: game_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Load all player stats for this game via GameStatistic::getByGame()
  // Each stat object carries playerName() and playerTeamId()
  // ------------------------------------------------------------------
  $all_stats = GameStatistic::getByGame($db, $game_id);
  $db->close();

  // Split into home / away buckets using playerTeamId()
  $home_stats = [];
  $away_stats = [];
  foreach ($all_stats as $gs)
    {
    if ($gs->playerTeamId() === $game->home_team_id())
      $home_stats[] = $gs;
    elseif ($gs->playerTeamId() === $game->away_team_id())
      $away_stats[] = $gs;
    }

  // Tally goals from stats (should match stored score after recompute)
  $home_goals = array_sum(array_map(fn($gs) => $gs->goals(), $home_stats));
  $away_goals = array_sum(array_map(fn($gs) => $gs->goals(), $away_stats));
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Game Detail - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class="page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id="texta">

      <a class="detail-back-link" href="game_page.php">&larr; Back to All Games</a>
      <br />

      <!-- ============================================================
           Game header — detail box (no table)
           ============================================================ -->
      <div class="game-detail">
        <strong>Game ID:</strong> <?= $game->game_id() ?><br>
        <strong>Home Team:</strong>
          <?= htmlspecialchars($game->home_team()) ?>
          (ID&nbsp;<?= $game->home_team_id() ?>)
          &mdash; Score: <?= $game->home_score() ?><br>
        <strong>Away Team:</strong>
          <?= htmlspecialchars($game->away_team()) ?>
          (ID&nbsp;<?= $game->away_team_id() ?>)
          &mdash; Score: <?= $game->away_score() ?><br>
        <strong>Date:</strong> <?= htmlspecialchars($game->game_date() ?: 'TBD') ?><br>
        <strong>Location:</strong> <?= htmlspecialchars($game->location() ?: 'TBD') ?>
      </div>

      <!-- ============================================================
           Player stats — Home team
           ============================================================ -->
      <h3 class="stats-team-heading">
        <?= htmlspecialchars($game->home_team()) ?>
        <span class="stats-team-id">
          (<?= $home_goals ?> goal<?= $home_goals !== 1 ? 's' : '' ?>)
        </span>
      </h3>

      <?php if (empty($home_stats)): ?>
        <p class="no-stats"><em>No stats recorded for this team.</em></p>
      <?php else: ?>
        <table class="stats-inner-table">
          <tr>
            <th>Player</th>
            <th>Goals</th>
            <th>Assists</th>
            <th>HR</th>
            <th>Time</th>
          </tr>
          <?php foreach ($home_stats as $gs): ?>
            <tr>
              <td><?= htmlspecialchars($gs->playerName()) ?></td>
              <td class="num"><?= $gs->goals() ?></td>
              <td class="num"><?= $gs->assists() ?></td>
              <td class="num"><?= $gs->homeRuns() ?></td>
              <td class="num"><?= $gs->timeFormatted() ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <!-- ============================================================
           Player stats — Away team
           ============================================================ -->
      <h3 class="stats-team-heading">
        <?= htmlspecialchars($game->away_team()) ?>
        <span class="stats-team-id">
          (<?= $away_goals ?> goal<?= $away_goals !== 1 ? 's' : '' ?>)
        </span>
      </h3>

      <?php if (empty($away_stats)): ?>
        <p class="no-stats"><em>No stats recorded for this team.</em></p>
      <?php else: ?>
        <table class="stats-inner-table">
          <tr>
            <th>Player</th>
            <th>Goals</th>
            <th>Assists</th>
            <th>HR</th>
            <th>Time</th>
          </tr>
          <?php foreach ($away_stats as $gs): ?>
            <tr>
              <td><?= htmlspecialchars($gs->playerName()) ?></td>
              <td class="num"><?= $gs->goals() ?></td>
              <td class="num"><?= $gs->assists() ?></td>
              <td class="num"><?= $gs->homeRuns() ?></td>
              <td class="num"><?= $gs->timeFormatted() ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

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
