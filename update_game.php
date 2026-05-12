<?php
// =============================================================
//  update_game.php
//
//  Manage per-player statistics for a specific game (manager only).
//  Score is never entered manually — it is always recalculated
//  automatically as SUM(Goals) per team after any stat change.
//
//  Displays all existing Games_statistics rows for this game
//  (players split by team), with inline edit forms for each row.
//  A separate "Add Player Stat" form lets the manager add a new
//  player to the game.
//
//  On every save or delete:
//    1. GameStatistic::upsert() / GameStatistic::delete()
//    2. GameStatistic::recomputePlayerTotals($db, $playerId)
//    3. GameStatistic::recomputeGameScore($db, $gameId)
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
  // Load the game
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
        // Reload game to reflect new score
        $game = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Save failed. Please try again.";
        }
      }
    }

  // ------------------------------------------------------------------
  // Handle POST — add a new player stat row
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
        $game = Game::getById($db, $game_id);
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
  // Handle POST — delete a player stat row
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
        $game = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Delete failed. Please try again.";
        }
      }
    }

  // ------------------------------------------------------------------
  // Load all current stat rows for this game (enriched with names)
  // ------------------------------------------------------------------
  $all_stats = GameStatistic::getByGame($db, $game_id);

  // Split by team
  $home_stats = [];
  $away_stats = [];
  foreach ($all_stats as $gs)
    {
    if ($gs->playerTeamId() === $game->home_team_id())      $home_stats[] = $gs;
    elseif ($gs->playerTeamId() === $game->away_team_id())  $away_stats[] = $gs;
    }

  // Players already in the game (for exclusion from "add" dropdown)
  $existing_player_ids = array_map(fn($gs) => $gs->player_id(), $all_stats);

  // Eligible players: members of either participating team, role 1 or 2
  $eligible_players = [];
  $ep_stmt = $db->prepare("
    SELECT UI.ID_num, UI.First_name, UI.Last_name, UI.Team_num, T.Name AS team_name
    FROM   Users_info     AS UI
    JOIN   Users_accounts AS UA ON UA.User_email = UI.Email
    JOIN   Teams          AS T  ON T.ID = UI.Team_num
    WHERE  UI.Team_num IN (?, ?)
      AND  UA.Role_type IN (1, 2)
    ORDER BY UI.Team_num ASC, UI.Last_name ASC, UI.First_name ASC
  ");
  if ($ep_stmt)
    {
    $htid = $game->home_team_id();
    $atid = $game->away_team_id();
    $ep_stmt->bind_param('ii', $htid, $atid);
    $ep_stmt->execute();
    $ep_result = $ep_stmt->get_result();
    while ($row = $ep_result->fetch_assoc())
      {
      if (!in_array((int)$row['ID_num'], $existing_player_ids))
        $eligible_players[] = $row;
      }
    $ep_stmt->close();
    }

  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Manage Game Stats - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">

        <h2>Manage Stats &mdash; Game #<?= $game_id ?></h2>

        <!-- Game summary header -->
        <table class = "game-card" style = "width:96%; margin:6px auto 10px;">
          <tr class = "row-header">
            <td style = "width:40%; text-align:center;">Home Team</td>
            <td class = "vs-cell">V.S.</td>
            <td style = "width:40%; text-align:center;">Away Team</td>
          </tr>
          <tr class = "row-teams">
            <td style = "text-align:center;"><?= htmlspecialchars($game->home_team()) ?></td>
            <td class = "vs-cell">&nbsp;</td>
            <td style = "text-align:center;"><?= htmlspecialchars($game->away_team()) ?></td>
          </tr>
          <tr class = "row-scores">
            <td style = "text-align:center;"><?= $game->home_score() ?></td>
            <td class = "vs-cell">&mdash;</td>
            <td style = "text-align:center;"><?= $game->away_score() ?></td>
          </tr>
          <tr class = "row-meta">
            <td colspan = "3">
              <strong>Date:</strong> <?= htmlspecialchars($game->game_date() ?: 'TBD') ?>
              &nbsp;&nbsp;
              <strong>Location:</strong> <?= htmlspecialchars($game->location() ?: 'TBD') ?>
            </td>
          </tr>
          <tr class = "row-meta">
            <td colspan = "3" style = "color:lightgray; font-size:0.85em;">
              Score is calculated automatically from player goals.
            </td>
          </tr>
        </table>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- ===========================================================
             Existing player stat rows, editable inline
             =========================================================== -->
        <?php
          // Helper to render one team's editable stat table
          function renderTeamStats($team_name, $stats, $game_id)
            {
            echo '<h3 style="color:tan; margin:14px 0 6px;">' . htmlspecialchars($team_name) . '</h3>';
            if (empty($stats))
              {
              echo '<p style="color:lightgray;"><em>No stats recorded for this team yet.</em></p>';
              return;
              }

            foreach ($stats as $gs):
            ?>
              <form method = "post" action = "update_game.php?id=<?= $game_id ?>"
                    style = "display:inline-block; vertical-align:top; margin:0 6px 10px 0;">
                <input type = "hidden" name = "player_id" value = "<?= $gs->player_id() ?>"/>
                <table style = "border-collapse:collapse; background:blue;">
                  <tr>
                    <th colspan = "2" style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                      <?= htmlspecialchars($gs->playerName()) ?>
                    </th>
                  </tr>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">Goals</td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">
                      <input type = "number" name = "goals" min = "0"
                             value = "<?= $gs->goals() ?>" style = "width:60px;"/>
                    </td>
                  </tr>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">Assists</td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">
                      <input type = "number" name = "assists" min = "0"
                             value = "<?= $gs->assists() ?>" style = "width:60px;"/>
                    </td>
                  </tr>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">Home Runs</td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">
                      <input type = "number" name = "homeruns" min = "0"
                             value = "<?= $gs->homeRuns() ?>" style = "width:60px;"/>
                    </td>
                  </tr>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">Mins</td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">
                      <input type = "number" name = "mins" min = "0"
                             value = "<?= $gs->timeMins() ?>" style = "width:60px;"/>
                    </td>
                  </tr>
                  <tr>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">Secs</td>
                    <td style = "border:3px solid darkorange; background:lightgreen; padding:3px;">
                      <input type = "number" name = "secs" min = "0" max = "59"
                             value = "<?= $gs->timeSecs() ?>" style = "width:60px;"/>
                    </td>
                  </tr>
                  <tr>
                    <td colspan = "2" style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
                      <button type = "submit" name = "save_stat" class = "btn-save"
                              style = "padding:2px 8px; margin-right:4px;">Save</button>
                      <button type = "submit" name = "delete_stat" class = "btn-delete"
                              style = "padding:2px 8px;"
                              onclick = "return confirm('Remove <?= htmlspecialchars(addslashes($gs->playerName())) ?> from this game?');">
                        Remove
                      </button>
                    </td>
                  </tr>
                </table>
              </form>
            <?php
            endforeach;
            }

          renderTeamStats($game->home_team(), $home_stats, $game_id);
          renderTeamStats($game->away_team(), $away_stats,  $game_id);
        ?>

        <!-- ===========================================================
             Add a new player to this game
             =========================================================== -->
        <h3 style = "color:tan; margin:18px 0 6px;">Add Player to Game</h3>

        <?php if (empty($eligible_players)): ?>
          <p style = "color:lightgray;"><em>All eligible players for both teams are already in this game.</em></p>
        <?php else: ?>
          <form method = "post" action = "update_game.php?id=<?= $game_id ?>">
            <table style = "border-collapse:collapse; background:blue;">
              <tr>
                <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Player</th>
                <th style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
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
                        <option value = "<?= (int)$ep['ID_num'] ?>">
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
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Goals</td>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                  <input type = "number" name = "new_goals" min = "0" value = "0" style = "width:60px;"/>
                </td>
              </tr>
              <tr>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Assists</td>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                  <input type = "number" name = "new_assists" min = "0" value = "0" style = "width:60px;"/>
                </td>
              </tr>
              <tr>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Home Runs</td>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                  <input type = "number" name = "new_homeruns" min = "0" value = "0" style = "width:60px;"/>
                </td>
              </tr>
              <tr>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Mins</td>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                  <input type = "number" name = "new_mins" min = "0" value = "0" style = "width:60px;"/>
                </td>
              </tr>
              <tr>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">Secs</td>
                <td style = "border:3px solid darkorange; background:lightgreen; padding:4px;">
                  <input type = "number" name = "new_secs" min = "0" max = "59" value = "0" style = "width:60px;"/>
                </td>
              </tr>
              <tr>
                <td colspan = "2" style = "border:3px solid darkorange; background:lightgreen; padding:4px; text-align:center;">
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
