<?php
// =============================================================
//  update_role.php
//
//  Handles four distinct modes via GET flag:
//
//    (default)          Change Role  — manager only
//    ?change_team=1     Change Team  — manager only
//    ?change_info=1     Change Info  — manager OR coach (own team)
//    ?remove_from_team=1  Remove player from team — coach OR manager
//
//  "Change Info" allows editing: First_name, Last_name, Password.
//  "Remove from Team" sets Team_num = 1 (N/A) — account and all
//  statistics are preserved.
//
//  DB enforcement:
//    'User' MySQL credential cannot UPDATE Role_type or DELETE users.
//    Coach row-scope (own team only) is application-enforced.
// =============================================================

  if (session_status() === PHP_SESSION_NONE)
    {
    session_start();
    }
  require_once('functions.php');
  require_once('User.php');
  UserCheck(0);

  $role       = $_SESSION['role'] ?? '';
  $is_manager = ($role === 'manager');
  $is_coach   = ($role === 'user');

  if (!$is_manager && !$is_coach)
    {
    header("Location: home_page.php");
    exit;
    }

  if (empty($_SESSION['db_user']) || empty($_SESSION['db_pass']))
    {
    header("Location: login_page.php");
    exit;
    }

  // Determine mode
  $change_team       = (int)($_REQUEST['change_team']       ?? 0);
  $change_info       = (int)($_REQUEST['change_info']       ?? 0);
  $remove_from_team  = (int)($_REQUEST['remove_from_team']  ?? 0);

  // Coaches may only access change_info and remove_from_team modes
  if ($is_coach && !$change_info && !$remove_from_team)
    {
    header("Location: role_management_page.php");
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

  $error   = '';
  $success = '';

  // ------------------------------------------------------------------
  // Logged-in user's display info for the menu
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
  // Load target user
  // ------------------------------------------------------------------
  $target = User::getById($db, $player_id);

  if ($target === null || $target->roleType() >= 3)
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Coach can only act on players (role 1) from their own team
  $coach_team_id = (int)($_SESSION['team_id'] ?? 0);
  if ($is_coach &&
      ($target->teamId() !== $coach_team_id || $target->roleType() !== 1))
    {
    $db->close();
    header("Location: role_management_page.php");
    exit;
    }

  // Load roles and teams for manager dropdowns
  $roles = [];
  $teams = [];
  if ($is_manager)
    {
    $res = $db->query("SELECT ID, Role_name FROM Roles ORDER BY ID");
    while ($row = $res->fetch_assoc()) $roles[] = $row;

    $res = $db->query("SELECT ID, Name FROM Teams ORDER BY ID");
    while ($row = $res->fetch_assoc()) $teams[] = $row;
    }

  // ------------------------------------------------------------------
  // Handle POST
  // ------------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {

    // ---- Change Role (manager only) --------------------------------
    if (isset($_POST['save_role']) && $is_manager && !$change_team && !$change_info)
      {
      $new_role = (int)($_POST['role_type'] ?? 1);
      if ($new_role < 1 || $new_role > 2)
        {
        $error = "Invalid role selected.";
        }
      else
        {
        if (User::updateRole($db, $target->email(), $new_role))
          {
          $success = "Role updated for " . htmlspecialchars($target->fullName()) . ".";
          $target  = User::getById($db, $player_id);
          }
        else
          {
          $error = "Update failed. Please try again.";
          }
        }
      }

    // ---- Change Team (manager only) --------------------------------
    elseif (isset($_POST['save_team']) && $is_manager && $change_team)
      {
      $new_team = (int)($_POST['team_id'] ?? 1);
      if (User::updateTeam($db, $player_id, $new_team))
        {
        $success = "Team updated for " . htmlspecialchars($target->fullName()) . ".";
        $target  = User::getById($db, $player_id);
        }
      else
        {
        $error = "Update failed. Please try again.";
        }
      }

    // ---- Change Info (manager OR coach, own team) ------------------
    elseif (isset($_POST['save_info']) && $change_info)
      {
      $new_first = trim($_POST['first_name'] ?? '');
      $new_last  = trim($_POST['last_name']  ?? '');
      $new_pass  = $_POST['new_password']    ?? '';
      $confirm   = $_POST['confirm_password'] ?? '';

      $info_ok = true;

      // Update name if provided
      if ($new_first !== '' || $new_last !== '')
        {
        $first_to_save = $new_first !== '' ? $new_first : $target->firstName();
        $last_to_save  = $new_last  !== '' ? $new_last  : $target->lastName();

        if (!User::updateInfo($db, $player_id, $first_to_save, $last_to_save))
          {
          $error   = "Name update failed. Please try again.";
          $info_ok = false;
          }
        }

      // Update password if provided
      if ($info_ok && $new_pass !== '')
        {
        if (strlen($new_pass) < 8)
          {
          $error   = "Password must be at least 8 characters.";
          $info_ok = false;
          }
        elseif ($new_pass !== $confirm)
          {
          $error   = "Passwords do not match.";
          $info_ok = false;
          }
        else
          {
          $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
          if (!User::updatePassword($db, $target->email(), $hashed))
            {
            $error   = "Password update failed. Please try again.";
            $info_ok = false;
            }
          }
        }

      if ($info_ok && $error === '')
        {
        $success = "Info updated for " . htmlspecialchars($target->fullName()) . ".";
        $target  = User::getById($db, $player_id);
        }
      }

    // ---- Remove from Team (manager OR coach) -----------------------
    elseif (isset($_POST['confirm_remove']) && $remove_from_team)
      {
      if (User::updateTeam($db, $player_id, 1))
        {
        $db->close();
        header("Location: role_management_page.php?removed=" . urlencode($target->fullName()));
        exit;
        }
      else
        {
        $error = "Remove failed. Please try again.";
        }
      }

    }

  $db->close();

  // Page title per mode
  if      ($remove_from_team) $page_title = "Remove from Team";
  elseif  ($change_info)      $page_title = "Change Info";
  elseif  ($change_team)      $page_title = "Change Team";
  else                        $page_title = "Change Role";

  $back_url = "role_management_page.php";
?>
<!DOCTYPE html>
<html>
  <head>
    <title><?= $page_title ?> - Baseball League</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <h1 class="page-title">Baseball League Statistics</h1>
    <?php Format("texta", 10, 8, 150, "black", 2, "black", "gray", 83, 89.6); ?>

    <div id="texta">
      <div class="form-wrap">
        <h2><?= $page_title ?>: <?= htmlspecialchars($target->fullName()) ?></h2>

        <?php if ($success): ?>
          <p class="msg-ok"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="msg-err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (!$remove_from_team): ?>
          <a class="back-link" href="<?= $back_url ?>">&larr; Back</a>
        <?php endif; ?>

        <!-- ======================================================
             Mode: Change Info (manager OR coach)
             ====================================================== -->
        <?php if ($change_info): ?>

          <p class="current-val">
            Current name: <strong><?= htmlspecialchars($target->fullName()) ?></strong>
            &bull; Team: <?= htmlspecialchars($target->teamName()) ?>
            &bull; Role: <?= htmlspecialchars(ucfirst($target->roleName())) ?>
          </p>

          <form method="post"
                action="update_role.php?id=<?= $player_id ?>&change_info=1">
            <table class="form-table">
              <tr>
                <th>Field</th>
                <th>New Value</th>
              </tr>
              <tr>
                <td>First Name</td>
                <td>
                  <input type="text" name="first_name" class="input-white"
                         placeholder="<?= htmlspecialchars($target->firstName()) ?>"
                         value=""/>
                </td>
              </tr>
              <tr>
                <td>Last Name</td>
                <td>
                  <input type="text" name="last_name" class="input-white"
                         placeholder="<?= htmlspecialchars($target->lastName()) ?>"
                         value=""/>
                </td>
              </tr>
              <tr>
                <td>New Password</td>
                <td>
                  <input type="password" name="new_password" class="input-white"
                         placeholder="Min 8 characters"/>
                </td>
              </tr>
              <tr>
                <td>Confirm Password</td>
                <td>
                  <input type="password" name="confirm_password" class="input-white"
                         placeholder="Repeat new password"/>
                </td>
              </tr>
            </table>
            <br/>
            <button type="submit" name="save_info" class="btn-save">Save Info</button>
          </form>

        <!-- ======================================================
             Mode: Remove from Team (manager OR coach)
             Sets Team_num = 1 (N/A) — account and stats kept.
             ====================================================== -->
        <?php elseif ($remove_from_team): ?>

          <div class="user-detail">
            <strong>Player:</strong> <?= htmlspecialchars($target->fullName()) ?><br/>
            <strong>Current Team:</strong> <?= htmlspecialchars($target->teamName()) ?><br/>
            <strong>Username:</strong> @<?= htmlspecialchars($target->username()) ?>
          </div>

          <p class="warning-text">
            This will move <?= htmlspecialchars($target->firstName()) ?> to the
            unassigned pool (Team N/A). Their account and all statistics are kept.
            A manager can reassign them to a team later.
          </p>

          <form method="post"
                action="update_role.php?id=<?= $player_id ?>&remove_from_team=1">
            <button type="submit" name="confirm_remove" class="btn-confirm">
              Yes, Remove from Team
            </button>
            <a class="back-link" href="<?= $back_url ?>">Cancel</a>
          </form>

        <!-- ======================================================
             Mode: Change Team (manager only)
             ====================================================== -->
        <?php elseif ($change_team && $is_manager): ?>

          <p class="current-val">
            Current team: <strong><?= htmlspecialchars($target->teamName()) ?></strong>
            &bull; Role: <?= htmlspecialchars(ucfirst($target->roleName())) ?>
          </p>

          <form method="post"
                action="update_role.php?id=<?= $player_id ?>&change_team=1">
            <table class="form-table">
              <tr>
                <th>New Team</th>
                <th>Select</th>
              </tr>
              <tr>
                <td>Team:</td>
                <td>
                  <select name="team_id" class="input-white">
                    <?php foreach ($teams as $t): ?>
                      <option value="<?= (int)$t['ID'] ?>"
                        <?= ((int)$t['ID'] === $target->teamId()) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['Name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </table>
            <br/>
            <button type="submit" name="save_team" class="btn-save">Save Team</button>
          </form>

        <!-- ======================================================
             Mode: Change Role (manager only)
             ====================================================== -->
        <?php elseif ($is_manager): ?>

          <p class="current-val">
            Current role: <strong><?= htmlspecialchars(ucfirst($target->roleName())) ?></strong>
            &bull; Team: <?= htmlspecialchars($target->teamName()) ?>
          </p>

          <form method="post" action="update_role.php?id=<?= $player_id ?>">
            <table class="form-table">
              <tr>
                <th>New Role</th>
                <th>Select</th>
              </tr>
              <tr>
                <td>Role:</td>
                <td>
                  <select name="role_type" class="input-white">
                    <?php foreach ($roles as $r): ?>
                      <?php if ((int)$r['ID'] === 3) continue; ?>
                      <option value="<?= (int)$r['ID'] ?>"
                        <?= ((int)$r['ID'] === $target->roleType()) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($r['Role_name'])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </table>
            <br/>
            <button type="submit" name="save_role" class="btn-save">Save Role</button>
          </form>

        <?php endif; ?>

      </div>
    </div>

    <?php
      $button = $_POST['button'] ?? '';
      if ($button == "Open Menu")
        MenuOpen(1, $menu_display_name, $menu_team_name, $role);
      elseif ($button == "Close Menu")
        MenuOpen(0, $menu_display_name, $menu_team_name, $role);
      else
        MenuOpen(0, $menu_display_name, $menu_team_name, $role);
    ?>
  </body>
</html>
