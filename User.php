<?php
// =============================================================
//  User — represents one row from Users_info joined with
//           Users_accounts, Teams, and Roles.
//
//  Static factory methods handle all DB interaction:
//
//    User::getAll($db)               returns User[]
//    User::getById($db, $id)         returns User|null
//    User::getByEmail($db, $email)   returns User|null
//    User::getByTeamAndRole($db, $teamId, $roleType)  returns User[]
//
//    User::updateInfo($db, $id, $firstName, $lastName)
//    User::updatePassword($db, $email, $plainPassword)
//    User::updateTeamAssign($db, $id, $teamId)   — pass teamId=1 to unassign
//    User::updateRole($db, $email, $roleType)
//    User::delete($db, $id)
//
//  Instance getters:
//    id(), email(), username()
//    firstName(), lastName(), fullName()
//    teamId(), teamName()
//    roleType(), roleName()
// =============================================================

class User
  {
  // ------ instance properties ------
  private $id        = 0;
  private $email     = '';
  private $username  = '';
  private $firstName = '';
  private $lastName  = '';
  private $teamId    = 0;
  private $teamName  = '';
  private $roleType  = 0;
  private $roleName  = '';


  // ------ getters ------
  function id()        { return $this->id;        }
  function email()     { return $this->email;     }
  function username()  { return $this->username;  }
  function firstName() { return $this->firstName; }
  function lastName()  { return $this->lastName;  }
  function fullName()  { return $this->firstName . ' ' . $this->lastName; }
  function teamId()    { return $this->teamId;    }
  function teamName()  { return $this->teamName;  }
  function roleType()  { return $this->roleType;  }
  function roleName()  { return $this->roleName;  }


  // ------ private constructor — use static factories ------
  private function __construct() {}


  // ------ shared SELECT for all fetch methods ------
  private static function baseQuery()
    {
    return "
      SELECT  UI.ID_num,
              UI.Email,
              UA.Username,
              UI.First_name,
              UI.Last_name,
              T.ID          AS team_id,
              T.Name        AS team_name,
              UA.Role_type,
              R.Role_name
      FROM    Users_info     AS UI
      JOIN    Users_accounts AS UA ON UA.User_email = UI.Email
      JOIN    Teams          AS T  ON T.ID          = UI.Team_num
      JOIN    Roles          AS R  ON R.ID          = UA.Role_type
    ";
    }


  // ------ hydrate a User from bound result variables ------
  // -- "hydrate" means "create an instance from DB data row" --
  private static function hydrate(
    $id, $email, $username, $first, $last,
    $team_id, $team_name,
    $role_type, $role_name
    )
    {
    $u            = new User();
    $u->id        = (int)$id;
    $u->email     = (string)$email;
    $u->username  = (string)$username;
    $u->firstName = (string)$first;
    $u->lastName  = (string)$last;
    $u->teamId    = (int)$team_id;
    $u->teamName  = (string)$team_name;
    $u->roleType  = (int)$role_type;
    $u->roleName  = (string)$role_name;
    return $u;
    }


  // ------ execute stmt, fetch all rows into User[] ------
  private static function bindAndFetch(mysqli_stmt $stmt)
    {
    $stmt->bind_result(
      $id, $email, $username, $first, $last,
      $team_id, $team_name,
      $role_type, $role_name
    );
    $stmt->store_result();
    $rows = [];
    while ($stmt->fetch())
      {
      $rows[] = self::hydrate(
        $id, $email, $username, $first, $last,
        $team_id, $team_name,
        $role_type, $role_name
      );
      }
    $stmt->close();
    return $rows;
    }


  // =============================================================
  //  User::getAll($db)
  //  Returns all users ordered by role DESC, team, last name.
  // =============================================================
  public static function getAll(mysqli $db)
    {
    $stmt = $db->prepare(
      self::baseQuery() .
      "ORDER BY UA.Role_type DESC, T.ID ASC,
                UI.Last_name ASC, UI.First_name ASC"
    );
    if (!$stmt) return [];
    $stmt->execute();
    return self::bindAndFetch($stmt);
    }


  // =============================================================
  //  User::getByTeamAndRole($db, $teamId, $roleType)
  //  Returns User[] filtered by team and role.
  //  Used by coaches to load only their own team's players.
  // =============================================================
  public static function getByTeamAndRole(mysqli $db, int $teamId, int $roleType)
    {
    $stmt = $db->prepare(
      self::baseQuery() .
      "WHERE UI.Team_num = ? AND UA.Role_type = ?
       ORDER BY UI.Last_name ASC, UI.First_name ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $teamId, $roleType);
    $stmt->execute();
    return self::bindAndFetch($stmt);
    }


  // =============================================================
  //  User::getById($db, $id)
  //  Returns a single User by ID_num, or null.
  // =============================================================
  public static function getById(mysqli $db, int $id)
    {
    $stmt = $db->prepare(
      self::baseQuery() . "WHERE UI.ID_num = ?"
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rows = self::bindAndFetch($stmt);
    return $rows[0] ?? null;
    }


  // =============================================================
  //  User::getByEmail($db, $email)
  //  Returns a single User by email, or null.
  //  Used to load the logged-in user's menu info.
  // =============================================================
  public static function getByEmail(mysqli $db, string $email)
    {
    $stmt = $db->prepare(
      self::baseQuery() . "WHERE UI.Email = ?"
    );
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $rows = self::bindAndFetch($stmt);
    return $rows[0] ?? null;
    }


  // =============================================================
  //  User::updateInfo($db, $id, $firstName, $lastName)
  //  Updates First_name and Last_name in Users_info.
  //  Used by both manager and coach (coach: own team only,
  //  enforced at application layer — see design notes).
  //  Returns true on success.
  // =============================================================
  public static function updateInfo(mysqli $db, int $id, string $firstName, string $lastName)
    {
    $firstName = trim($firstName);
    $lastName  = trim($lastName);
    if ($firstName === '' || $lastName === '') return false;

    $stmt = $db->prepare("
      UPDATE Users_info SET First_name = ?, Last_name = ? WHERE ID_num = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param('ssi', $firstName, $lastName, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  User::updatePassword($db, $email, $hashedPassword)
  //  Updates Password in Users_accounts.
  //  Caller must bcrypt the plaintext before passing it in.
  //  Used by both manager and coach (coach: own team only).
  //  Returns true on success.
  // =============================================================
  public static function updatePassword(mysqli $db, string $email, string $hashedPassword)
    {
    $stmt = $db->prepare("
      UPDATE Users_accounts SET Password = ? WHERE User_email = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $hashedPassword, $email);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  User::updateRole($db, $email, $roleType)
  //  Updates Role_type in Users_accounts.
  //  Returns true on success.
  // =============================================================
  public static function updateRole(mysqli $db, string $email, int $roleType)
    {
    $stmt = $db->prepare("
      UPDATE Users_accounts SET Role_type = ? WHERE User_email = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param('is', $roleType, $email);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  User::updateTeamAssign($db, $id, $teamId)
  //  Sets Team_num = $teamId for the given player.
  //  Pass teamId = 1 to remove a player from their team (N/A).
  //  Accessible by manager (any player) and coach (own team only,
  //  enforced by the calling page).
  //  DB-level: 'User' credential has UPDATE (Team_num) on
  //  Users_info — row-scope is application-enforced.
  //  Returns true on success.
  // =============================================================
  public static function updateTeamAssign(mysqli $db, int $id, int $teamId)
    {
    $stmt = $db->prepare("
      UPDATE Users_info SET Team_num = ? WHERE ID_num = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $teamId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  User::delete($db, $id)
  //  Deletes from Users_info; FK CASCADE removes
  //  Users_accounts and Users_statistics automatically.
  //  Returns true on success.
  // =============================================================
  public static function delete(mysqli $db, int $id)
    {
    $stmt = $db->prepare("DELETE FROM Users_info WHERE ID_num = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }

  } // end class User
?>
