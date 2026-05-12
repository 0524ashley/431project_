<?php
// =============================================================
//  update_statistic.php
//
//  Shows a player's career totals (read-only, auto-computed
//  from Games_statistics) and lists their per-game entries
//  with links to add, edit, or delete individual game rows.
//
//  The Career Totals section is intentionally NOT editable here.
//  They are always recomputed automatically whenever a game-stat
//  row is saved or deleted via processStatisticUpdate/Delete.php.
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  require_once('PlayerStatistic.php');
  require_once('GameStatistic.php');
  require_once('Game.php');
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

  $player_id = (int)($_REQUEST['id'] ?? 0);
  if ($player_id <= 0)
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
  // Load target player, career totals, and per-game stats
  // ------------------------------------------------------------------
  $player = User::getById($db, $player_id);

  if ($player === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Coaches may only view/edit stats for their own team
  if ($role === 'user' && $player->teamId() !== (int)($_SESSION['team_id'] ?? 0))
    {
    $db->close();
    header("Location: home_page.php");
    exit;
    }

  $stat = PlayerStatistic::getById($db, $player_id);

  if ($stat === null)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Per-game rows for this player
  $game_stats = GameStatistic::getByPlayer($db, $player_id);

  // All games for the "Add / Edit" dropdown
  $all_games = Game::getAll($db);

  $db->close();

  // ------------------------------------------------------------------
  // Flash messages from process scripts
  // ------------------------------------------------------------------
  $flash = '';
  if (isset($_GET['gs_saved']))    $flash = 'Game stat saved and career totals updated.';
  if (isset($_GET['gs_deleted']))  $flash = 'Game stat deleted and career totals updated.';
  if (isset($_GET['gs_error']))    $flash = 'An error occurred. Please try again.';
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Player Stats - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Player Stats: <?= htmlspecialchars($player->fullName()) ?></h2>

        <a class = "back-link" href = "role_management_page.php">&larr; Back to Management</a>

        <?php if ($flash): ?>
          <?php if (isset($_GET['gs_error'])): ?>
            <p class = "msg-err"><?= htmlspecialchars($flash) ?></p>
          <?php else: ?>
            <p class = "msg-ok"><?= htmlspecialchars($flash) ?></p>
          <?php endif; ?>
        <?php endif; ?>

        <!-- ======================================================
             Section 1: Career Totals — READ ONLY
             These values are always recomputed automatically from
             Games_statistics. They cannot be edited directly.
             ====================================================== -->
        <h3 style = "color:tan; margin-top:16px;">Career Totals
          <span style = "font-size:0.75em; font-weight:normal; color:lightgray;">
            (auto-updated from game entries)
          </span>
        </h3>

        <table style = "border-collapse:collapse; background:blue;">
          <tr>
            <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Stat</th>
            <th style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Total</th>
          </tr>
          <tr>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Goals</td>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
              <?= $stat->goals() ?>
            </td>
          </tr>
          <tr>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Assists</td>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
              <?= $stat->assists() ?>
            </td>
          </tr>
          <tr>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Home Runs</td>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
              <?= $stat->home_runs() ?>
            </td>
          </tr>
          <tr>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">Time on Field</td>
            <td style = "vertical-align:top; border:4px solid darkorange; background:lightgreen;">
              <?= htmlspecialchars($stat->time_on_field()) ?>
            </td>
          </tr>
        </table>

        <!-- ======================================================
             Section 2: Per-Game Stats (Games_statistics)
             ====================================================== -->
        <h3 style = "color:tan; margin-top:24px;">Per-Game Stats</h3>

        <!-- Dropdown to add/edit a game-stat row — managers only -->
        <?php if ($role === 'manager' && !empty($all_games)): ?>
          <div style = "margin-bottom:12px;">
            <form method = "get" action = "processStatisticUpdate.php" style = "display:inline;">
              <input type = "hidden" name = "player_id" value = "<?= $player_id ?>"/>
              <select name = "game_id">
                <option value = "">-- Select game to add / edit --</option>
                <?php foreach ($all_games as $g): ?>
                  <option value = "<?= $g->game_id() ?>">
                    #<?= $g->game_id() ?>:
                    <?= htmlspecialchars($g->home_team()) ?>
                    vs
                    <?= htmlspecialchars($g->away_team()) ?>
                    (<?= htmlspecialchars($g->game_date()) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <button type = "submit" class = "btn-save" style = "padding:3px 10px;">
                Add / Edit Game Stat
              </button>
            </form>
          </div>
        <?php endif; ?>

        <!-- Table of existing per-game rows -->
        <?php if (empty($game_stats)): ?>
          <div class = "no-games">No per-game stats recorded for this player yet.</div>
        <?php else: ?>
          <table>
            <tr>
              <th>Game #</th>
              <th>Date</th>
              <th>Goals</th>
              <th>Assists</th>
              <th>HR</th>
              <th>Time</th>
              <th>Actions</th>
            </tr>
            <?php foreach ($game_stats as $gs): ?>
              <?php
                // Look up the game date for display
                $gdate = '';
                foreach ($all_games as $g)
                  {
                  if ($g->game_id() === $gs->gameId())
                    {
                    $gdate = $g->game_date();
                    break;
                    }
                  }
              ?>
              <tr>
                <td><?= (int)$gs->gameId()    ?></td>
                <td><?= htmlspecialchars($gdate) ?></td>
                <td><?= $gs->goals()           ?></td>
                <td><?= $gs->assists()         ?></td>
                <td><?= $gs->homeRuns()        ?></td>
                <td><?= $gs->timeFormatted()   ?></td>
                <td>
                  <a class = "btn"
                     href = "processStatisticUpdate.php?game_id=<?= $gs->gameId() ?>&player_id=<?= $player_id ?>">
                    Edit
                  </a>
                  <a class = "btn-delete"
                     href = "processStatisticDelete.php?game_id=<?= $gs->gameId() ?>&player_id=<?= $player_id ?>"
                     onclick = "return confirm('Delete stats for Game #<?= $gs->gameId() ?>? Career totals will update automatically.');">
                    Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
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
