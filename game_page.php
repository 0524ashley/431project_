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
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align:center; color:tan; margin-top:4px;">All Games</h2>

      <a href = "home_page.php">&larr; Return to Home</a>

      <div id = "game_window">
        <?php if (empty($games)): ?>
          <div class = "no-games">No games found.</div>
        <?php else: ?>
          <?php foreach ($games as $g): ?>

            <table class = "game-card">

              <!-- Row 1: Home Team | V.S. | Away Team labels -->
              <tr class = "row-header">
                <td style = "width:40%;">Home Team</td>
                <td class = "vs-cell">V.S.</td>
                <td style = "width:40%;">Away Team</td>
              </tr>

              <!-- Row 2: team names -->
              <tr class = "row-teams">
                <td style = "text-align:center;">
                  <?= htmlspecialchars($g->home_team()) ?>
                </td>
                <td class = "vs-cell">&nbsp;</td>
                <td style = "text-align:center;">
                  <?= htmlspecialchars($g->away_team()) ?>
                </td>
              </tr>

              <!-- Row 3: scores -->
              <tr class = "row-scores">
                <td style = "text-align:center;">
                  <?= $g->home_score() ?>
                </td>
                <td class = "vs-cell">&mdash;</td>
                <td style = "text-align:center;">
                  <?= $g->away_score() ?>
                </td>
              </tr>

              <!-- Row 4: date -->
              <tr class = "row-meta">
                <td colspan = "3">
                  <strong>Date:</strong>
                  <?= htmlspecialchars($g->game_date() ?: 'TBD') ?>
                </td>
              </tr>

              <!-- Row 5: location -->
              <tr class = "row-meta">
                <td colspan = "3">
                  <strong>Location:</strong>
                  <?= htmlspecialchars($g->location() ?: 'TBD') ?>
                </td>
              </tr>

              <!-- Row 6: Details button — visible to all roles -->
              <tr class = "row-meta">
                <td colspan = "3" style = "text-align:center; padding:6px 0;">
                  <a class = "btn"
                     href = "game_detail_page.php?id=<?= $g->game_id() ?>">
                    View Details
                  </a>
                </td>
              </tr>

            </table>

          <?php endforeach; ?>
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
