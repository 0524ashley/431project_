<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('Game.php');
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

  // Load game via Game class
  $game = Game::getById($db, $game_id);
  if ($game === null)
    {
    $db->close();
    header("Location: game_management_page.php");
    exit;
    }

  // Load teams for dropdowns (exclude Team 1 N/A)
  $teams = [];
  $res = $db->query("SELECT ID, Name FROM Teams WHERE ID > 1 ORDER BY ID");
  while ($row = $res->fetch_assoc())
    $teams[] = $row;

  // ------------------------------------------------------------------
  // Handle POST — update via Game class
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_game']))
    {
    $data = [
      'home_team_id' => (int)($_POST['home_team_id'] ?? 0),
      'away_team_id' => (int)($_POST['away_team_id'] ?? 0),
      'game_date'    => trim($_POST['game_date']    ?? ''),
      'location'     => trim($_POST['location']     ?? ''),
      'home_score'   => (int)($_POST['home_score']  ?? 0),
      'away_score'   => (int)($_POST['away_score']  ?? 0),
    ];

    if ($data['home_team_id'] === $data['away_team_id'])
      {
      $error = "Home team and away team cannot be the same.";
      }
    elseif ($data['home_team_id'] <= 0 || $data['away_team_id'] <= 0)
      {
      $error = "Please select both teams.";
      }
    elseif (empty($data['game_date']))
      {
      $error = "Please enter a game date.";
      }
    else
      {
      if (Game::update($db, $game_id, $data))
        {
        $success = "Game updated successfully.";
        // Reload updated game object
        $game = Game::getById($db, $game_id);
        }
      else
        {
        $error = "Update failed. Please try again.";
        }
      }
    }

  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Modify Game - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align:center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <div class = "form-wrap">
        <h2>Modify Game #<?= $game_id ?></h2>

        <?php if ($success): ?>
          <p class = "msg-ok"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class = "msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method = "post" action = "update_game.php?id=<?= $game_id ?>">
          <table>
            <tr>
              <th>Field</th>
              <th>Value</th>
            </tr>
            <tr>
              <td>Home Team</td>
              <td>
                <select name = "home_team_id">
                  <?php foreach ($teams as $t): ?>
                    <option value = "<?= (int)$t['ID'] ?>"
                      <?= ((int)$t['ID'] === $game->home_team_id()) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($t['Name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Away Team</td>
              <td>
                <select name = "away_team_id">
                  <?php foreach ($teams as $t): ?>
                    <option value = "<?= (int)$t['ID'] ?>"
                      <?= ((int)$t['ID'] === $game->away_team_id()) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($t['Name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Home Score</td>
              <td>
                <input type = "number" name = "home_score" min = "0"
                       value = "<?= $game->home_score() ?>"/>
              </td>
            </tr>
            <tr>
              <td>Away Score</td>
              <td>
                <input type = "number" name = "away_score" min = "0"
                       value = "<?= $game->away_score() ?>"/>
              </td>
            </tr>
            <tr>
              <td>Game Date</td>
              <td>
                <input type = "date" name = "game_date"
                       value = "<?= htmlspecialchars($game->game_date()) ?>"/>
              </td>
            </tr>
            <tr>
              <td>Location</td>
              <td>
                <input type = "text" name = "location" maxlength = "100"
                       value = "<?= htmlspecialchars($game->location()) ?>"/>
              </td>
            </tr>
          </table>
          <br/>
          <button type = "submit" name = "save_game" class = "btn-save">Save Changes</button>
        </form>

        <a class = "back-link" href = "game_management_page.php">&larr; Back to Game Management</a>
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
