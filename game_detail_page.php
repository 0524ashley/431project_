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
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">

      <!-- ============================================================
           Game header card
           ============================================================ -->
      <table class = "game-card" style = "width:96%; margin: 10px auto;">
        <tr class = "row-header">
          <td style = "width:40%; text-align:center;">Home Team</td>
          <td class = "vs-cell">V.S.</td>
          <td style = "width:40%; text-align:center;">Away Team</td>
        </tr>
        <tr class = "row-teams">
          <td style = "text-align:center;">
            <?= htmlspecialchars($game->home_team()) ?>
          </td>
          <td class = "vs-cell">&nbsp;</td>
          <td style = "text-align:center;">
            <?= htmlspecialchars($game->away_team()) ?>
          </td>
        </tr>
        <tr class = "row-scores">
          <td style = "text-align:center;"><?= $game->home_score() ?></td>
          <td class = "vs-cell">&mdash;</td>
          <td style = "text-align:center;"><?= $game->away_score() ?></td>
        </tr>
        <tr class = "row-meta">
          <td colspan = "3">
            <strong>Date:</strong>
            <?= htmlspecialchars($game->game_date() ?: 'TBD') ?>
            &nbsp;&nbsp;
            <strong>Location:</strong>
            <?= htmlspecialchars($game->location() ?: 'TBD') ?>
          </td>
        </tr>
      </table>

      <a href = "game_page.php" style = "display:inline-block; margin:6px 0 10px 0;">
        &larr; Back to All Games
      </a>

      <!-- ============================================================
           Player stats — two columns: Home | Away
           ============================================================ -->
      <table style = "width:96%; margin:0 auto; border-collapse:collapse;">
        <tr style = "vertical-align:top;">

          <!-- HOME TEAM column -->
          <td style = "width:50%; padding-right:8px;">
            <h3 style = "color:tan; margin:6px 0;">
              <?= htmlspecialchars($game->home_team()) ?>
              <span style = "font-size:0.8em; color:lightgray;">
                (<?= $home_goals ?> goal<?= $home_goals !== 1 ? 's' : '' ?>)
              </span>
            </h3>

            <?php if (empty($home_stats)): ?>
              <p style = "color:lightgray;"><em>No stats recorded for this team.</em></p>
            <?php else: ?>
              <table style = "border-collapse:collapse; background:blue; width:100%;">
                <tr>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Player</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Goals</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Assists</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">HR</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Time</th>
                </tr>
                <?php foreach ($home_stats as $gs): ?>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                      <?= htmlspecialchars($gs->playerName()) ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->goals() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->assists() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->homeRuns() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->timeFormatted() ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </td>

          <!-- AWAY TEAM column -->
          <td style = "width:50%; padding-left:8px;">
            <h3 style = "color:tan; margin:6px 0;">
              <?= htmlspecialchars($game->away_team()) ?>
              <span style = "font-size:0.8em; color:lightgray;">
                (<?= $away_goals ?> goal<?= $away_goals !== 1 ? 's' : '' ?>)
              </span>
            </h3>

            <?php if (empty($away_stats)): ?>
              <p style = "color:lightgray;"><em>No stats recorded for this team.</em></p>
            <?php else: ?>
              <table style = "border-collapse:collapse; background:blue; width:100%;">
                <tr>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Player</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Goals</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Assists</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">HR</th>
                  <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Time</th>
                </tr>
                <?php foreach ($away_stats as $gs): ?>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                      <?= htmlspecialchars($gs->playerName()) ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->goals() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->assists() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->homeRuns() ?>
                    </td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <?= $gs->timeFormatted() ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </td>

        </tr>
      </table>

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
