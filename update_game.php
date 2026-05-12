<?php
// =============================================================
//  update_game.php
//
//  Manager-only page for editing a game's player statistics.
//  Game info (teams, date, location, score) is shown read-only
//  using the game-row card layout.
//  Score is always derived from SUM(Goals) — never edited here.
//
//  All stat edits and deletions are handled inline via POST;
//  recomputePlayerTotals() and recomputeGameScore() run after
//  every change.
//
//  Expects GET:
//    id — Game_ID to manage
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('Game.php');
  require_once('GameStatistic.php');
  require_once('User.php');
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

  $error   = '';
  $success = '';

  // ------------------------------------------------------------------
  // Menu display info
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
  // Load game
  // ------------------------------------------------------------------
  $game = Game::getById($db, $game_id);
  if ($game === null)
    {
    $db->close();
    header("Location: game_management_page.php");
    exit;
    }

  // ------------------------------------------------------------------
  // Handle POST — edit existing player stat row
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stat']))
    {
    $pid = (int)($_POST['player_id'] ?? 0);
    if ($pid > 0)
      {
      $data = [
        'goals'     => (int)($_POST['goals']    ?? 0),
        'assists'   => (int)($_POST['assists']  ?? 0),
        'home_runs' => (int)($_POST['homeruns'] ?? 0),
        'time_mins' => (int)($_POST['mins']     ?? 0),
        'time_secs' => (int)($_POST['secs']     ?? 0),
      ];
      if (GameStatistic::upsert($db, $game_id, $pid, $data))
        {
        GameStatistic::recomputePlayerTotals($db, $pid);
        GameStatistic::recomputeGameScore($db, $game_id);
        $success = "Stats updated and score recalculated.";
        $game    = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Save failed. Please try again.";
        }
      }
    }

  // ------------------------------------------------------------------
  // Handle POST — add new player stat row
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stat']))
    {
    $pid = (int)($_POST['new_player_id'] ?? 0);
    if ($pid > 0)
      {
      $data = [
        'goals'     => (int)($_POST['new_goals']    ?? 0),
        'assists'   => (int)($_POST['new_assists']  ?? 0),
        'home_runs' => (int)($_POST['new_homeruns'] ?? 0),
        'time_mins' => (int)($_POST['new_mins']     ?? 0),
        'time_secs' => (int)($_POST['new_secs']     ?? 0),
      ];
      if (GameStatistic::upsert($db, $game_id, $pid, $data))
        {
        GameStatistic::recomputePlayerTotals($db, $pid);
        GameStatistic::recomputeGameScore($db, $game_id);
        $success = "Player stat added and score recalculated.";
        $game    = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Add failed. Please try again.";
        }
      }
    else
      {
      $error = "Please select a player to add.";
      }
    }

  // ------------------------------------------------------------------
  // Handle POST — delete player stat row
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stat']))
    {
    $pid = (int)($_POST['player_id'] ?? 0);
    if ($pid > 0)
      {
      if (GameStatistic::delete($db, $game_id, $pid))
        {
        GameStatistic::recomputePlayerTotals($db, $pid);
        GameStatistic::recomputeGameScore($db, $game_id);
        $success = "Stat removed and score recalculated.";
        $game    = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Delete failed. Please try again.";
        }
      }
    }

  // ------------------------------------------------------------------
  // Load all current stat rows (enriched with name/team info)
  // ------------------------------------------------------------------
  $all_stats = GameStatistic::getByGame($db, $game_id);

  // Split by team
  $home_stats = [];
  $away_stats = [];
  foreach ($all_stats as $r)
    {
    if ($r->playerTeamId() === $game->home_team_id())
      $home_stats[] = $r;
    elseif ($r->playerTeamId() === $game->away_team_id())
      $away_stats[] = $r;
    }

  // Players eligible to be added (role 1, not already in game)
  $existing_ids   = array_column($all_stats, 'player_id');
  $eligible_players = [];
  $ep_stmt = $db->prepare("
    SELECT  UI.ID_num, UI.First_name, UI.Last_name,
            UI.Team_num, T.Name AS team_name
    FROM    Users_info     AS UI
    JOIN    Users_accounts AS UA ON UA.User_email = UI.Email
    JOIN    Teams          AS T  ON T.ID          = UI.Team_num
    WHERE   UA.Role_type = 1
      AND   UI.Team_num IN (?, ?)
    ORDER BY T.ID ASC, UI.Last_name ASC
  ");
  if ($ep_stmt)
    {
    $ep_stmt->bind_param('ii', $game->home_team_id(), $game->away_team_id());
    $ep_stmt->execute();
    $ep_stmt->bind_result($ep_id, $ep_first, $ep_last, $ep_team_num, $ep_team_name);
    $ep_stmt->store_result();
    while ($ep_stmt->fetch())
      {
      if (!in_array((int)$ep_id, $existing_ids))
        $eligible_players[] = [
          'ID_num'    => (int)$ep_id,
          'First_name' => $ep_first,
          'Last_name'  => $ep_last,
          'Team_num'   => (int)$ep_team_num,
          'team_name'  => $ep_team_name,
        ];
      }
    $ep_stmt->close();
    }

  $db->close();

  // ------------------------------------------------------------------
  // Helper: render one team's inline-edit stat cards
  // ------------------------------------------------------------------
  function renderTeamStats(array $team_rows, string $team_label, int $game_id)
    {
    echo "<h3 class='subsection-heading'>" . htmlspecialchars($team_label) . "</h3>";

    if (empty($team_rows))
      {
      echo "<p class='muted-note'><em>No stats recorded for this team yet.</em></p>";
      return;
      }

    foreach ($team_rows as $r):
      $pid = (int)$r->playerId();
      ?>
      <div class = "stat-card-wrap">
        <form method = "post" action = "update_game.php?id=<?= $game_id ?>">
          <input type = "hidden" name = "player_id" value = "<?= $pid ?>"/>
          <table class = "stat-table">
            <tr>
              <th colspan = "2"><?= htmlspecialchars($r->playerName()) ?></th>
            </tr>
            <tr>
              <td>Goals</td>
              <td><input type = "number" name = "goals"    min = "0" value = "<?= $r->goals()    ?>"/></td>
            </tr>
            <tr>
              <td>Assists</td>
              <td><input type = "number" name = "assists"  min = "0" value = "<?= $r->assists()  ?>"/></td>
            </tr>
            <tr>
              <td>Home Runs</td>
              <td><input type = "number" name = "homeruns" min = "0" value = "<?= $r->homeRuns()?>"/></td>
            </tr>
            <tr>
              <td>Mins</td>
              <td><input type = "number" name = "mins"     min = "0" value = "<?= $r->timeMins()?>"/></td>
            </tr>
            <tr>
              <td>Secs</td>
              <td><input type = "number" name = "secs"     min = "0" max = "59" value = "<?= $r->timeSecs()?>"/></td>
            </tr>
            <tr>
              <td colspan = "2" class = "stat-num">
                <button type = "submit" name = "save_stat"   class = "btn-save-sm">Save</button>
                <button type = "submit" name = "delete_stat" class = "btn-delete-sm"
                        onclick = "return confirm('Remove <?= htmlspecialchars(addslashes($r->playerName())) ?> from this game?');">
                  Remove
                </button>
              </td>
            </tr>
          </table>
        </form>
      </div>
      <?php
    endforeach;
    }
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Modify Game Stats - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class = "page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Modify Game #<?= $game_id ?> Stats</h2>

        <a class = "back-link" href = "game_management_page.php">&larr; Back to Game Management</a>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Read-only game info using the shared game-row card -->
        <table class = "game-row">
          <tr class = "game-row-header">
            <td colspan = "2">Game #<?= $game_id ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Home Team</td>
            <td><?= htmlspecialchars($game->home_team()) ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Away Team</td>
            <td><?= htmlspecialchars($game->away_team()) ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Date</td>
            <td><?= htmlspecialchars($game->game_date() ?: 'TBD') ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Location</td>
            <td><?= htmlspecialchars($game->location() ?: 'TBD') ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Home Score</td>
            <td class = "game-row-score"><?= $game->home_score() ?></td>
          </tr>
          <tr>
            <td class = "game-row-label">Away Score</td>
            <td class = "game-row-score"><?= $game->away_score() ?></td>
          </tr>
          <tr class = "game-row-note">
            <td colspan = "2">Score is calculated automatically from player goals</td>
          </tr>
        </table>

        <p class = "hint-text">
          Edit or remove player stats below. The score updates automatically when goals change.
        </p>

        <!-- Home team stat cards -->
        <?php renderTeamStats($home_stats, 'Home — ' . $game->home_team(), $game_id); ?>

        <!-- Away team stat cards -->
        <?php renderTeamStats($away_stats, 'Away — ' . $game->away_team(), $game_id); ?>

        <!-- Add a new player to this game -->
        <h3 class = "subsection-heading-lg">Add Player to Game</h3>

        <?php if (empty($eligible_players)): ?>
          <p class = "muted-note"><em>All eligible players for both teams are already in this game.</em></p>
        <?php else: ?>
          <form method = "post" action = "update_game.php?id=<?= $game_id ?>">
            <table class = "stat-table">
              <tr>
                <th>Player</th>
                <th>
                  <select name = "new_player_id">
                    <option value = "">-- Select Player --</option>
                    <?php
                      $cur_team = -1;
                      foreach ($eligible_players as $ep):
                        if ($ep['Team_num'] !== $cur_team):
                          if ($cur_team !== -1) echo '</optgroup>';
                          echo '<optgroup label="' . htmlspecialchars($ep['team_name']) . '">';
                          $cur_team = $ep['Team_num'];
                        endif;
                    ?>
                        <option value = "<?= $ep['ID_num'] ?>">
                          <?= htmlspecialchars($ep['First_name'] . ' ' . $ep['Last_name']) ?>
                        </option>
                    <?php
                      endforeach;
                      if ($cur_team !== -1) echo '</optgroup>';
                    ?>
                  </select>
                </th>
              </tr>
              <tr>
                <td>Goals</td>
                <td><input type = "number" name = "new_goals"    min = "0" value = "0"/></td>
              </tr>
              <tr>
                <td>Assists</td>
                <td><input type = "number" name = "new_assists"  min = "0" value = "0"/></td>
              </tr>
              <tr>
                <td>Home Runs</td>
                <td><input type = "number" name = "new_homeruns" min = "0" value = "0"/></td>
              </tr>
              <tr>
                <td>Mins</td>
                <td><input type = "number" name = "new_mins"     min = "0" value = "0"/></td>
              </tr>
              <tr>
                <td>Secs</td>
                <td><input type = "number" name = "new_secs"     min = "0" max = "59" value = "0"/></td>
              </tr>
              <tr>
                <td colspan = "2" class = "stat-num">
                  <button type = "submit" name = "add_stat" class = "btn-save">Add to Game</button>
                </td>
              </tr>
            </table>
          </form>
        <?php endif; ?>


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
