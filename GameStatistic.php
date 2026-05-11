<?php
// =============================================================
//  GameStatistic — represents one row from Games_statistics,
//  which stores per-game stats for a single player in a game.
//  Composite PK: (Game_ID, Player_ID).
//
//    GameStatistic::getByPlayer($db, $playerId)
//        Returns GameStatistic[] array for all games for the player.
//
//    GameStatistic::getByGameAndPlayer($db, $gameId, $playerId)
//        Returns GameStatistic|null for one specific game-player combo.
//
//    GameStatistic::upsert($db, $gameId, $playerId, $data)
//        Insert or update one game-player stat row.
//        Returns true on success.
//        $data keys: time_mins, time_secs, goals, assists, home_runs
//
//    GameStatistic::delete($db, $gameId, $playerId)
//        Delete one game-player stat row.
//        Returns true on success.
// =============================================================

class GameStatistic
  {
  // ------ instance attributes ------
  private $game_id      = 0;
  private $player_id    = 0;
  private $time_on_field = array('MINS' => 0, 'SECS' => 0);
  private $goals         = 0;
  private $assists       = 0;
  private $home_runs     = 0;


  function game_id()
    {
    if (func_num_args() == 0)
      return $this->game_id;
    else if (func_num_args() == 1)
      $this->game_id = (int)func_get_arg(0);
    return $this;
    }

  function player_id()
    {
    if (func_num_args() == 0)
      return $this->player_id;
    else if (func_num_args() == 1)
      $this->player_id = (int)func_get_arg(0);
    return $this;
    }

  function time_on_field()
    {
    if (func_num_args() == 0)
      return $this->time_on_field['MINS'] . ':' . str_pad($this->time_on_field['SECS'], 2, '0', STR_PAD_LEFT);
    else if (func_num_args() == 1)
      {
      $value = func_get_arg(0);
      if (is_string($value)) $value = explode(':', $value);
      if (is_array($value))
        {
        if (count($value) >= 2) $this->time_on_field['SECS'] = (int)$value[1];
        else                    $this->time_on_field['SECS'] = 0;
        $this->time_on_field['MINS'] = (int)$value[0];
        }
      }
    else if (func_num_args() == 2)
      {
      $this->time_on_field['MINS'] = (int)func_get_arg(0);
      $this->time_on_field['SECS'] = (int)func_get_arg(1);
      }
    return $this;
    }

  function goals()
    {
    if (func_num_args() == 0)   return $this->goals;
    else if (func_num_args() == 1) $this->goals = (int)func_get_arg(0);
    return $this;
    }

  function assists()
    {
    if (func_num_args() == 0)   return $this->assists;
    else if (func_num_args() == 1) $this->assists = (int)func_get_arg(0);
    return $this;
    }

  function home_runs()
    {
    if (func_num_args() == 0)   return $this->home_runs;
    else if (func_num_args() == 1) $this->home_runs = (int)func_get_arg(0);
    return $this;
    }

  // Helper aliases for camelCase access
  function gameId()     { return $this->game_id(); }
  function playerId()   { return $this->player_id(); }
  function homeRuns()   { return $this->home_runs(); }
  function timeMins()   { return $this->time_on_field['MINS']; }
  function timeSecs()   { return $this->time_on_field['SECS']; }
  function timeFormatted()
    {
    return str_pad($this->time_on_field['MINS'], 2, '0', STR_PAD_LEFT) . ':' . 
           str_pad($this->time_on_field['SECS'], 2, '0', STR_PAD_LEFT);
    }


  // =============================================================
  //  Constructor
  // =============================================================
  function __construct(
    $game_id = 0,
    $player_id = 0,
    $time = "0:0",
    $goals = 0,
    $assists = 0,
    $home_runs = 0
    )
    {
    $this->game_id($game_id);
    $this->player_id($player_id);
    $this->time_on_field($time);
    $this->goals($goals);
    $this->assists($assists);
    $this->home_runs($home_runs);
    }

  function __toString() { return var_export($this, true); }


  // =============================================================
  //  GameStatistic::getByPlayer($db, $playerId)
  //  Returns array of GameStatistic objects for all games
  //  for the given player.
  // =============================================================
  public static function getByPlayer(mysqli $db, $playerId)
    {
    $stats = [];
    $playerId = (int)$playerId;

    $stmt = $db->prepare("
      SELECT  Game_ID,
              Player_ID,
              Time_on_field_mins,
              Time_on_field_secs,
              Goals,
              Assists,
              Home_runs
      FROM    Games_statistics
      WHERE   Player_ID = ?
      ORDER BY Game_ID
    ");

    if (!$stmt)
      return $stats;

    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
      {
      $stat = new GameStatistic(
        $row['Game_ID'],
        $row['Player_ID'],
        $row['Time_on_field_mins'] . ':' . $row['Time_on_field_secs'],
        $row['Goals'],
        $row['Assists'],
        $row['Home_runs']
      );
      $stats[] = $stat;
      }

    $stmt->close();
    return $stats;
    }


  // =============================================================
  //  GameStatistic::getByGameAndPlayer($db, $gameId, $playerId)
  //  Returns a single GameStatistic object or null if not found.
  // =============================================================
  public static function getByGameAndPlayer(mysqli $db, $gameId, $playerId)
    {
    $gameId   = (int)$gameId;
    $playerId = (int)$playerId;

    $stmt = $db->prepare("
      SELECT  Game_ID,
              Player_ID,
              Time_on_field_mins,
              Time_on_field_secs,
              Goals,
              Assists,
              Home_runs
      FROM    Games_statistics
      WHERE   Game_ID = ? AND Player_ID = ?
    ");

    if (!$stmt)
      return null;

    $stmt->bind_param('ii', $gameId, $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0)
      {
      $stmt->close();
      return null;
      }

    $row = $result->fetch_assoc();
    $stat = new GameStatistic(
      $row['Game_ID'],
      $row['Player_ID'],
      $row['Time_on_field_mins'] . ':' . $row['Time_on_field_secs'],
      $row['Goals'],
      $row['Assists'],
      $row['Home_runs']
    );

    $stmt->close();
    return $stat;
    }


  // =============================================================
  //  GameStatistic::upsert($db, $gameId, $playerId, $data)
  //  Insert a new game-stat row or update existing one.
  //  Return true on success, false on failure.
  //  $data keys: time_mins, time_secs, goals, assists, home_runs
  // =============================================================
  public static function upsert(mysqli $db, $gameId, $playerId, $data)
    {
    $gameId    = (int)$gameId;
    $playerId  = (int)$playerId;
    $time_mins = (int)($data['time_mins'] ?? 0);
    $time_secs = (int)($data['time_secs'] ?? 0);
    $goals     = (int)($data['goals'] ?? 0);
    $assists   = (int)($data['assists'] ?? 0);
    $home_runs = (int)($data['home_runs'] ?? 0);

    $stmt = $db->prepare("
      INSERT INTO Games_statistics
        (Game_ID, Player_ID, Time_on_field_mins, Time_on_field_secs,
         Goals, Assists, Home_runs)
      VALUES (?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        Time_on_field_mins = VALUES(Time_on_field_mins),
        Time_on_field_secs = VALUES(Time_on_field_secs),
        Goals              = VALUES(Goals),
        Assists            = VALUES(Assists),
        Home_runs          = VALUES(Home_runs)
    ");

    if (!$stmt)
      return false;

    $stmt->bind_param(
      'iiiiiii',
      $gameId, $playerId, $time_mins, $time_secs,
      $goals, $assists, $home_runs
    );

    $success = $stmt->execute();
    $stmt->close();
    return $success;
    }


  // =============================================================
  //  GameStatistic::delete($db, $gameId, $playerId)
  //  Delete one game-stat row by composite PK.
  //  Return true on success, false on failure.
  // =============================================================
  public static function delete(mysqli $db, $gameId, $playerId)
    {
    $gameId   = (int)$gameId;
    $playerId = (int)$playerId;

    $stmt = $db->prepare("
      DELETE FROM Games_statistics
      WHERE Game_ID = ? AND Player_ID = ?
    ");

    if (!$stmt)
      return false;

    $stmt->bind_param('ii', $gameId, $playerId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
    }
  }
?>
