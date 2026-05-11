<?php
  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  UserCheck(0); // Redirect to login if not authenticated

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
  // Fetch the logged-in user's display name and team name for the menu.
  // ------------------------------------------------------------------
  $menu_display_name = $_SESSION['username'] ?? 'User';
  $menu_team_name    = '';

  $me_stmt = $db->prepare("
    SELECT UI.First_name, UI.Last_name, T.Name
    FROM   Users_info AS UI
    JOIN   Teams      AS T  ON T.ID = UI.Team_num
    WHERE  UI.Email = ?
  ");
  if ($me_stmt)
    {
    $me_stmt->bind_param('s', $_SESSION['email']);
    $me_stmt->execute();
    $me_stmt->bind_result($me_first, $me_last, $me_team);
    if ($me_stmt->fetch())
      {
      $menu_display_name = $me_first . ' ' . $me_last;
      $menu_team_name    = $me_team;
      }
    $me_stmt->close();
    }

  // ------------------------------------------------------------------
  // Main query — all teams 2/3/4, coaches (role 2) and players (role 1).
  // Reads career totals from Player_statistics (Total_ prefixed columns).
  // ORDER BY role DESC so coaches appear first.
  // ------------------------------------------------------------------
  $teams = [];

  $stmt = $db->prepare("
    SELECT  T.ID             AS team_id,
            T.Name           AS team_name,
            UA.Role_type,
            UI.First_name,
            UI.Last_name,
            PS.Total_goals,
            PS.Total_assists,
            PS.Total_home_runs,
            PS.Total_time_on_field_mins,
            PS.Total_time_on_field_secs
    FROM    Users_info           AS UI
    JOIN    Users_accounts       AS UA ON UA.User_email = UI.Email
    JOIN    Teams                AS T  ON T.ID          = UI.Team_num
    LEFT JOIN Player_statistics  AS PS ON PS.Player_ID  = UI.ID_num
    WHERE   UI.Team_num   IN (2, 3, 4)
      AND   UA.Role_type  IN (1, 2)
    ORDER BY T.ID ASC, UA.Role_type DESC, UI.Last_name ASC, UI.First_name ASC
  ");
  if (!$stmt)
    die("Prepare failed: " . $db->error);

  $stmt->execute();
  $stmt->bind_result(
    $team_id, $team_name, $role_type,
    $first_name, $last_name,
    $goals, $assists, $home_runs,
    $time_mins, $time_secs
  );
  $stmt->store_result();

  while ($stmt->fetch())
    {
    if (!isset($teams[$team_id]))
      {
      $teams[$team_id] = [
        'team_name' => $team_name,
        'coach'     => null,
        'players'   => []
      ];
      }

    if ($role_type == 2) // coach
      {
      if ($teams[$team_id]['coach'] === null)
        $teams[$team_id]['coach'] = htmlspecialchars($first_name . ' ' . $last_name);
      }
    else // player
      {
      $teams[$team_id]['players'][] = [
        'name'     => htmlspecialchars($first_name . ' ' . $last_name),
        'goals'    => (int)$goals,
        'assists'  => (int)$assists,
        'homeruns' => (int)$home_runs,
        'time'     => (int)$time_mins . ':' . str_pad((int)$time_secs, 2, '0', STR_PAD_LEFT)
      ];
      }
    }

  $stmt->close();
  $db->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Baseball League - Team Statistics</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 style = "text-align: center;">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id = "texta">
      <h2 style = "text-align: center; color: tan;">Teams &amp; Player Statistics</h2>

      <?php if (empty($teams)): ?>
        <p style = "color: red;">No team data found.</p>
      <?php else: ?>
        <?php foreach ($teams as $tid => $team): ?>
          <div class = "team-block">

            <h2 class = "team-title">
              Team: <?= htmlspecialchars($team['team_name']) ?>
            </h2>

            <div class = "coach-line">
              <strong>Coach:</strong>
              <?= $team['coach'] !== null
                    ? $team['coach']
                    : '<em>No coach assigned</em>' ?>
            </div>

            <?php if (empty($team['players'])): ?>
              <p style = "color: lightgray;"><em>No players on this team.</em></p>
            <?php else: ?>
              <table style = "background: blue;">
                <tr>
                  <th>Player</th>
                  <th>Total Goals</th>
                  <th>Total Assists</th>
                  <th>Total Home Runs</th>
                  <th>Total Time on Field</th>
                </tr>
                <?php foreach ($team['players'] as $p): ?>
                  <tr>
                    <td><?= $p['name']     ?></td>
                    <td><?= $p['goals']    ?></td>
                    <td><?= $p['assists']  ?></td>
                    <td><?= $p['homeruns'] ?></td>
                    <td><?= $p['time']     ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
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
