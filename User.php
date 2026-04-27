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
//
//    User::updateRole($db, $email, $roleType)
//    User::updateTeam($db, $id, $teamId)
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
  //  User::updateTeam($db, $id, $teamId)
  //  Updates Team_num in Users_info.
  //  Returns true on success.
  // =============================================================
  public static function updateTeam(mysqli $db, int $id, int $teamId)
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
