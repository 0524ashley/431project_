<?php
// =============================================================
//  GameStatistic — represents one row from Games_statistics,
//  which stores per-game stats for a single player in a game.
//  Composite PK: (Game_ID, Player_ID).
//
//  Static query methods:
//    GameStatistic::getByPlayer($db, $playerId)
//        Returns GameStatistic[] for all games for the player.
//
//    GameStatistic::getByGame($db, $gameId)
//        Returns GameStatistic[] for all players in one game,
//        each object enriched with the player's first/last name
//        and team_id via a JOIN on Users_info.
//
//    GameStatistic::getByGameAndPlayer($db, $gameId, $playerId)
//        Returns GameStatistic|null for one specific game-player combo.
//
//  Static write methods:
//    GameStatistic::upsert($db, $gameId, $playerId, $data)
//        Insert or update one game-stat row.
//        Returns true on success.
//        $data keys: time_mins, time_secs, goals, assists, home_runs
//
//    GameStatistic::delete($db, $gameId, $playerId)
//        Delete one game-stat row.
//        Returns true on success.
//
//    GameStatistic::recomputePlayerTotals($db, $playerId)
//        Recomputes Player_statistics career totals for the given
//        player by running SUM() across all their Games_statistics rows.
//        Call after every upsert or delete.
//        Returns true on success.
//
//    GameStatistic::recomputeGameScore($db, $gameId)
//        Recomputes Games.Home_score and Games.Away_score by summing
//        Goals from Games_statistics for each team's players.
//        Home score  = SUM of Goals for players whose team = Home_Team_ID.
//        Away score  = SUM of Goals for players whose team = Away_Team_ID.
//        Call after every upsert or delete on a game-stat row.
//        Returns true on success.
//
//  Instance getters:
//    game_id() / gameId()
//    player_id() / playerId()
//    goals() / assists() / home_runs() / homeRuns()
//    time_on_field()   — "MM:SS"
//    timeMins() / timeSecs() / timeFormatted()
//    playerName()      — full name (only set by getByGame())
//    playerTeamId()    — team id  (only set by getByGame())
// =============================================================

class GameStatistic
  {
  // ------ instance attributes ------
  private $game_id       = 0;
  private $player_id     = 0;
  private $time_on_field = array('MINS' => 0, 'SECS' => 0);
  private $goals         = 0;
  private $assists       = 0;
  private $home_runs     = 0;

  // Enriched by getByGame() only — empty string / 0 otherwise
  private $player_name    = '';
  private $player_team_id = 0;


  // ------ getter/setter combos (match existing style) ------
  function game_id()
    {
    if (func_num_args() == 0)   return $this->game_id;
    else if (func_num_args() == 1) $this->game_id = (int)func_get_arg(0);
    return $this;
    }

  function player_id()
    {
    if (func_num_args() == 0)   return $this->player_id;
    else if (func_num_args() == 1) $this->player_id = (int)func_get_arg(0);
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

  // camelCase aliases
  function gameId()        { return $this->game_id();    }
  function playerId()      { return $this->player_id();  }
  function homeRuns()      { return $this->home_runs();  }
  function timeMins()      { return $this->time_on_field['MINS']; }
  function timeSecs()      { return $this->time_on_field['SECS']; }
  function timeFormatted()
    {
    return str_pad($this->time_on_field['MINS'], 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($this->time_on_field['SECS'], 2, '0', STR_PAD_LEFT);
    }

  // Enrichment getters (populated only by getByGame())
  function playerName()   { return $this->player_name;    }
  function playerTeamId() { return $this->player_team_id; }


  // =============================================================
  //  Constructor
  // =============================================================
  function __construct(
    $game_id   = 0,
    $player_id = 0,
    $time      = "0:0",
    $goals     = 0,
    $assists   = 0,
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
  //  Returns GameStatistic[] for all games for the given player.
  // =============================================================
  public static function getByPlayer(mysqli $db, $playerId)
    {
    $stats    = [];
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
    if (!$stmt) return $stats;

    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
      {
      $stats[] = new GameStatistic(
        $row['Game_ID'],
        $row['Player_ID'],
        $row['Time_on_field_mins'] . ':' . $row['Time_on_field_secs'],
        $row['Goals'],
        $row['Assists'],
        $row['Home_runs']
      );
      }

    $stmt->close();
    return $stats;
    }


  // =============================================================
  //  GameStatistic::getByGame($db, $gameId)
  //  Returns GameStatistic[] for all players in a given game.
  //  Each object is enriched with playerName() and playerTeamId()
  //  via a JOIN on Users_info so callers never need raw SQL.
  //  Ordered by team ID then player last name.
  // =============================================================
  public static function getByGame(mysqli $db, $gameId)
    {
    $stats  = [];
    $gameId = (int)$gameId;

    $stmt = $db->prepare("
      SELECT  GS.Game_ID,
              GS.Player_ID,
              GS.Time_on_field_mins,
              GS.Time_on_field_secs,
              GS.Goals,
              GS.Assists,
              GS.Home_runs,
              UI.First_name,
              UI.Last_name,
              UI.Team_num
      FROM    Games_statistics AS GS
      JOIN    Users_info       AS UI ON UI.ID_num = GS.Player_ID
      WHERE   GS.Game_ID = ?
      ORDER BY UI.Team_num ASC, UI.Last_name ASC, UI.First_name ASC
    ");
    if (!$stmt) return $stats;

    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
      {
      $gs = new GameStatistic(
        $row['Game_ID'],
        $row['Player_ID'],
        $row['Time_on_field_mins'] . ':' . $row['Time_on_field_secs'],
        $row['Goals'],
        $row['Assists'],
        $row['Home_runs']
      );
      $gs->player_name    = trim($row['First_name'] . ' ' . $row['Last_name']);
      $gs->player_team_id = (int)$row['Team_num'];
      $stats[] = $gs;
      }

    $stmt->close();
    return $stats;
    }


  // =============================================================
  //  GameStatistic::getByGameAndPlayer($db, $gameId, $playerId)
  //  Returns a single GameStatistic or null if not found.
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
    if (!$stmt) return null;

    $stmt->bind_param('ii', $gameId, $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0)
      {
      $stmt->close();
      return null;
      }

    $row = $result->fetch_assoc();
    $stmt->close();

    return new GameStatistic(
      $row['Game_ID'],
      $row['Player_ID'],
      $row['Time_on_field_mins'] . ':' . $row['Time_on_field_secs'],
      $row['Goals'],
      $row['Assists'],
      $row['Home_runs']
    );
    }


  // =============================================================
  //  GameStatistic::upsert($db, $gameId, $playerId, $data)
  //  Insert or update one game-stat row.
  //  $data keys: time_mins, time_secs, goals, assists, home_runs
  //  Returns true on success.
  // =============================================================
  public static function upsert(mysqli $db, $gameId, $playerId, $data)
    {
    $gameId    = (int)$gameId;
    $playerId  = (int)$playerId;
    $time_mins = max(0, (int)($data['time_mins'] ?? 0));
    $time_secs = min(59, max(0, (int)($data['time_secs'] ?? 0)));
    $goals     = max(0, (int)($data['goals']     ?? 0));
    $assists   = max(0, (int)($data['assists']   ?? 0));
    $home_runs = max(0, (int)($data['home_runs'] ?? 0));

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
    if (!$stmt) return false;

    $stmt->bind_param('iiiiiii',
      $gameId, $playerId, $time_mins, $time_secs,
      $goals, $assists, $home_runs
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  GameStatistic::delete($db, $gameId, $playerId)
  //  Delete one game-stat row by composite PK.
  //  Returns true on success.
  // =============================================================
  public static function delete(mysqli $db, $gameId, $playerId)
    {
    $gameId   = (int)$gameId;
    $playerId = (int)$playerId;

    $stmt = $db->prepare("
      DELETE FROM Games_statistics WHERE Game_ID = ? AND Player_ID = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param('ii', $gameId, $playerId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  GameStatistic::recomputePlayerTotals($db, $playerId)
  //
  //  Recomputes Player_statistics career totals for one player
  //  from SUM() across all their Games_statistics rows.
  //  COALESCE ensures totals become 0 (not NULL) when no rows remain.
  //  Time is normalised: raw seconds → FLOOR/60 mins + MOD 60 secs.
  //  Returns true on success.
  // =============================================================
  public static function recomputePlayerTotals(mysqli $db, $playerId)
    {
    $playerId = (int)$playerId;

    $stmt = $db->prepare("
      UPDATE Player_statistics
      SET    Total_goals              = (
               SELECT COALESCE(SUM(Goals), 0)
               FROM   Games_statistics WHERE Player_ID = ?
             ),
             Total_assists            = (
               SELECT COALESCE(SUM(Assists), 0)
               FROM   Games_statistics WHERE Player_ID = ?
             ),
             Total_home_runs          = (
               SELECT COALESCE(SUM(Home_runs), 0)
               FROM   Games_statistics WHERE Player_ID = ?
             ),
             Total_time_on_field_mins = (
               SELECT FLOOR(
                 COALESCE(SUM(Time_on_field_mins * 60 + Time_on_field_secs), 0) / 60
               )
               FROM   Games_statistics WHERE Player_ID = ?
             ),
             Total_time_on_field_secs = (
               SELECT MOD(
                 COALESCE(SUM(Time_on_field_mins * 60 + Time_on_field_secs), 0), 60
               )
               FROM   Games_statistics WHERE Player_ID = ?
             )
      WHERE  Player_ID = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param('iiiiii',
      $playerId, $playerId, $playerId,
      $playerId, $playerId, $playerId
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  GameStatistic::recomputeGameScore($db, $gameId)
  //
  //  Recalculates Games.Home_score and Games.Away_score by summing
  //  Goals from Games_statistics for each participating team.
  //
  //  Home score = SUM(Goals) for players whose Team_num = Home_Team_ID
  //  Away score = SUM(Goals) for players whose Team_num = Away_Team_ID
  //
  //  COALESCE handles the case where a team has no stat rows yet (→ 0).
  //  Call this after every upsert() or delete() on a game-stat row.
  //  Returns true on success.
  // =============================================================
  public static function recomputeGameScore(mysqli $db, $gameId)
    {
    $gameId = (int)$gameId;

    $stmt = $db->prepare("
      UPDATE Games
      SET    Home_score = (
               SELECT COALESCE(SUM(GS.Goals), 0)
               FROM   Games_statistics AS GS
               JOIN   Users_info       AS UI ON UI.ID_num = GS.Player_ID
               JOIN   Games            AS G2 ON G2.Game_ID = GS.Game_ID
               WHERE  GS.Game_ID  = ?
                 AND  UI.Team_num = G2.Home_Team_ID
             ),
             Away_score = (
               SELECT COALESCE(SUM(GS.Goals), 0)
               FROM   Games_statistics AS GS
               JOIN   Users_info       AS UI ON UI.ID_num = GS.Player_ID
               JOIN   Games            AS G2 ON G2.Game_ID = GS.Game_ID
               WHERE  GS.Game_ID  = ?
                 AND  UI.Team_num = G2.Away_Team_ID
             )
      WHERE  Game_ID = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param('iii', $gameId, $gameId, $gameId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }

  } // end class GameStatistic
?>
