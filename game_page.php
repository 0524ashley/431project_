<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('Game.php');
  UserCheck(0); // must be logged in

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
  // Fetch logged-in user's display info for the menu
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
  // Fetch all games via Game class (ordered by date DESC)
  // ------------------------------------------------------------------
  $games = Game::getAll($db);
  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>All Games - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class="page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id="texta">
      <h2 class="section-title">All Games</h2>

      <a href="home_page.php">&larr; Return to Home</a>

      <div id="game_window">
        <?php if (empty($games)): ?>
          <div class="no-games">No games found.</div>
        <?php else: ?>

          <table class="games-table">
            <thead>
              <tr>
                <th>Game ID</th>
                <th>Home Team ID</th>
                <th>Away Team ID</th>
                <th>Date</th>
                <th>Location</th>
                <th>Home Score</th>
                <th>Away Score</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($games as $g): ?>
                <tr>
                  <td class="col-id"><?= $g->game_id() ?></td>
                  <td><?= $g->home_team_id() ?> &mdash; <?= htmlspecialchars($g->home_team()) ?></td>
                  <td><?= $g->away_team_id() ?> &mdash; <?= htmlspecialchars($g->away_team()) ?></td>
                  <td class="col-date"><?= htmlspecialchars($g->game_date() ?: 'TBD') ?></td>
                  <td class="col-location"><?= htmlspecialchars($g->location() ?: 'TBD') ?></td>
                  <td class="col-score"><?= $g->home_score() ?></td>
                  <td class="col-score"><?= $g->away_score() ?></td>
                  <td class="col-action">
                    <?php if (($_SESSION['role'] ?? '') !== 'manager'): ?>
                      <a class="btn" href="game_detail_page.php?id=<?= $g->game_id() ?>">View Detail</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

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
