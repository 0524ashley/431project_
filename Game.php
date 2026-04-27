<?php
// =============================================================
//  Game — represents one row from the Games table.
//
//  Static factory methods handle all DB interaction so
//  the pages never write raw SQL themselves:
//
//    Game::getAll($db)              returns Game[]  ordered by date DESC
//    Game::getById($db, $id)        returns Game|null
//    Game::create($db, $data)       inserts a new row, returns new Game
//    Game::update($db, $id, $data)  updates an existing row
//    Game::delete($db, $id)         deletes the row
//
//  $data array keys for create/update:
//    home_team_id, away_team_id, game_date, location,
//    home_score, away_score
// =============================================================

class Game
  {
  // ------ instance properties ------
  private $game_id      = 0;
  private $home_team_id = 0;
  private $away_team_id = 0;
  private $home_team    = '';   // resolved team name (read-only from JOIN)
  private $away_team    = '';   // resolved team name (read-only from JOIN)
  private $home_score   = 0;
  private $away_score   = 0;
  private $game_date    = '';
  private $location     = '';


  // ------ getters ------
  function game_id()      { return $this->game_id;      }
  function home_team_id() { return $this->home_team_id; }
  function away_team_id() { return $this->away_team_id; }
  function home_team()    { return $this->home_team;    }
  function away_team()    { return $this->away_team;    }
  function home_score()   { return $this->home_score;   }
  function away_score()   { return $this->away_score;   }
  function game_date()    { return $this->game_date;    }
  function location()     { return $this->location;     }


  // ------ private constructor — use static factories ------
  private function __construct() {}


  // ------ hydrate from a result row ------
  private static function hydrate(
    $game_id, $home_team_id, $away_team_id,
    $home_team, $away_team,
    $home_score, $away_score,
    $game_date, $location
    )
    {
    $g               = new Game();
    $g->game_id      = (int)$game_id;
    $g->home_team_id = (int)$home_team_id;
    $g->away_team_id = (int)$away_team_id;
    $g->home_team    = (string)$home_team;
    $g->away_team    = (string)$away_team;
    $g->home_score   = (int)$home_score;
    $g->away_score   = (int)$away_score;
    $g->game_date    = (string)$game_date;
    $g->location     = (string)$location;
    return $g;
    }


  // =============================================================
  //  Game::getAll($db)
  //  Returns an array of Game objects, newest first.
  // =============================================================
  public static function getAll(mysqli $db)
    {
    $games = [];

    $stmt = $db->prepare("
      SELECT  G.Game_ID,
              G.Home_Team_ID,
              G.Away_Team_ID,
              TH.Name      AS home_team,
              TA.Name      AS away_team,
              G.Home_score,
              G.Away_score,
              G.Game_date,
              G.Location
      FROM    Games AS G
      JOIN    Teams AS TH ON TH.ID = G.Home_Team_ID
      JOIN    Teams AS TA ON TA.ID = G.Away_Team_ID
      ORDER BY G.Game_date DESC
    ");
    if (!$stmt) return $games;

    $stmt->execute();
    $stmt->bind_result(
      $gid, $htid, $atid,
      $ht, $at,
      $hs, $as_,
      $gd, $loc
    );
    while ($stmt->fetch())
      {
      $games[] = self::hydrate($gid, $htid, $atid, $ht, $at, $hs, $as_, $gd, $loc);
      }
    $stmt->close();
    return $games;
    }


  // =============================================================
  //  Game::getById($db, $id)
  //  Returns a single Game or null if not found.
  // =============================================================
  public static function getById(mysqli $db, int $id)
    {
    $stmt = $db->prepare("
      SELECT  G.Game_ID,
              G.Home_Team_ID,
              G.Away_Team_ID,
              TH.Name      AS home_team,
              TA.Name      AS away_team,
              G.Home_score,
              G.Away_score,
              G.Game_date,
              G.Location
      FROM    Games AS G
      JOIN    Teams AS TH ON TH.ID = G.Home_Team_ID
      JOIN    Teams AS TA ON TA.ID = G.Away_Team_ID
      WHERE   G.Game_ID = ?
    ");
    if (!$stmt) return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result(
      $gid, $htid, $atid,
      $ht, $at,
      $hs, $as_,
      $gd, $loc
    );
    $game = null;
    if ($stmt->fetch())
      {
      $game = self::hydrate($gid, $htid, $atid, $ht, $at, $hs, $as_, $gd, $loc);
      }
    $stmt->close();
    return $game;
    }


  // =============================================================
  //  Game::create($db, $data)
  //  Inserts a new game row. Returns the new Game on success,
  //  or null on failure.
  //
  //  $data keys: home_team_id, away_team_id, game_date,
  //              location, home_score, away_score
  // =============================================================
  public static function create(mysqli $db, array $data)
    {
    $home_team_id = (int)($data['home_team_id'] ?? 0);
    $away_team_id = (int)($data['away_team_id'] ?? 0);
    $game_date    = $data['game_date'] ?? '';
    $location     = $data['location']  ?? '';
    $home_score   = max(0, (int)($data['home_score'] ?? 0));
    $away_score   = max(0, (int)($data['away_score'] ?? 0));

    $stmt = $db->prepare("
      INSERT INTO Games
        (Home_Team_ID, Away_Team_ID, Game_date, Location, Home_score, Away_score)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) return null;

    $stmt->bind_param('iissii',
      $home_team_id, $away_team_id,
      $game_date, $location,
      $home_score, $away_score
    );
    if (!$stmt->execute())
      {
      $stmt->close();
      return null;
      }
    $new_id = (int)$db->insert_id;
    $stmt->close();

    return self::getById($db, $new_id);
    }


  // =============================================================
  //  Game::update($db, $id, $data)
  //  Updates the game with the given ID.
  //  Returns true on success, false on failure.
  // =============================================================
  public static function update(mysqli $db, int $id, array $data)
    {
    $home_team_id = (int)($data['home_team_id'] ?? 0);
    $away_team_id = (int)($data['away_team_id'] ?? 0);
    $game_date    = $data['game_date'] ?? '';
    $location     = $data['location']  ?? '';
    $home_score   = max(0, (int)($data['home_score'] ?? 0));
    $away_score   = max(0, (int)($data['away_score'] ?? 0));

    $stmt = $db->prepare("
      UPDATE Games
      SET    Home_Team_ID = ?,
             Away_Team_ID = ?,
             Game_date    = ?,
             Location     = ?,
             Home_score   = ?,
             Away_score   = ?
      WHERE  Game_ID = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param('iissiii',
      $home_team_id, $away_team_id,
      $game_date, $location,
      $home_score, $away_score,
      $id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  Game::delete($db, $id)
  //  Deletes the game row. Returns true on success.
  // =============================================================
  public static function delete(mysqli $db, int $id)
    {
    $stmt = $db->prepare("DELETE FROM Games WHERE Game_ID = ?");
    if (!$stmt) return false;

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }

  } // end class Game
?>
