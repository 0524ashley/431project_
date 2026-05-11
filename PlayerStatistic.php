<?php
// =============================================================
//  PlayerStatistic — represents one row from Player_statistics,
//  which stores career totals for a single player.
//
//    PlayerStatistic::getById($db, $playerId)
//        Returns PlayerStatistic|null for the given Player_ID.
//
//    PlayerStatistic::update($db, $playerId, $data)
//        Updates an existing career-totals row.
//        Returns true on success.
//        $data keys: goals, assists, home_runs, time_mins, time_secs
//
//    PlayerStatistic::insertDefault($db, $playerId)
//        Inserts a zeroed stats row for a new player.
//        Returns true on success.
// =============================================================

class PlayerStatistic
  {
  // ------ instance attributes ------
  private $playerId      = 0;
  private $time_on_field = array('MINS' => 0, 'SECS' => 0);
  private $goals         = 0;
  private $assists       = 0;
  private $home_runs     = 0;


  function playerId()
    {
    if (func_num_args() == 0)
      return $this->playerId;
    else if (func_num_args() == 1)
      $this->playerId = (int)func_get_arg(0);
    return $this;
    }

  function time_on_field()
    {
    if (func_num_args() == 0)
      return $this->time_on_field['MINS'] . ':' . $this->time_on_field['SECS'];
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
  function __construct($playerId = 0, $time = "0:0", $goals = 0, $assists = 0, $home_runs = 0)
    {
    if (is_string($playerId) && strpos($playerId, "\t") !== false)
      list($playerId, $time, $goals, $assists, $home_runs) = explode("\t", $playerId);

    $this->playerId($playerId);
    $this->time_on_field($time);
    $this->goals($goals);
    $this->assists($assists);
    $this->home_runs($home_runs);
    }

  function __toString() { return var_export($this, true); }

  function toTSV()
    {
    return implode("\t", [
      $this->playerId(),
      $this->time_on_field(),
      $this->goals(),
      $this->assists(),
      $this->home_runs()
    ]);
    }

  function fromTSV(string $tsvString)
    {
    list($playerId, $time, $goals, $assists, $home_runs) = explode("\t", $tsvString);
    $this->playerId($playerId);
    $this->time_on_field($time);
    $this->goals($goals);
    $this->assists($assists);
    $this->home_runs($home_runs);
    }


  // =============================================================
  //  PlayerStatistic::getById($db, $playerId)
  //  Reads from Player_statistics (renamed table, Total_ columns).
  // =============================================================
  public static function getById(mysqli $db, int $playerId)
    {
    $stmt = $db->prepare("
      SELECT Player_ID,
             Total_goals,
             Total_assists,
             Total_home_runs,
             Total_time_on_field_mins,
             Total_time_on_field_secs
      FROM   Player_statistics
      WHERE  Player_ID = ?
    ");
    if (!$stmt) return null;

    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $stmt->bind_result($pid, $goals, $assists, $home_runs, $t_mins, $t_secs);
    $stat = null;
    if ($stmt->fetch())
      $stat = new PlayerStatistic($pid, $t_mins . ':' . $t_secs, $goals, $assists, $home_runs);
    $stmt->close();
    return $stat;
    }


  // =============================================================
  //  PlayerStatistic::update($db, $playerId, $data)
  //  Writes to Player_statistics using Total_ column names.
  //  $data keys: goals, assists, home_runs, time_mins, time_secs
  // =============================================================
  public static function update(mysqli $db, int $playerId, array $data)
    {
    $goals     = max(0, (int)($data['goals']     ?? 0));
    $assists   = max(0, (int)($data['assists']   ?? 0));
    $home_runs = max(0, (int)($data['home_runs'] ?? 0));
    $mins      = max(0, (int)($data['time_mins'] ?? 0));
    $secs      = min(59, max(0, (int)($data['time_secs'] ?? 0)));

    $stmt = $db->prepare("
      UPDATE Player_statistics
      SET    Total_goals              = ?,
             Total_assists            = ?,
             Total_home_runs          = ?,
             Total_time_on_field_mins = ?,
             Total_time_on_field_secs = ?
      WHERE  Player_ID = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param('iiiiii', $goals, $assists, $home_runs, $mins, $secs, $playerId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }


  // =============================================================
  //  PlayerStatistic::insertDefault($db, $playerId)
  //  Inserts a zeroed row into Player_statistics for a new player.
  // =============================================================
  public static function insertDefault(mysqli $db, int $playerId)
    {
    $stmt = $db->prepare("
      INSERT INTO Player_statistics (Player_ID) VALUES (?)
    ");
    if (!$stmt) return false;

    $stmt->bind_param('i', $playerId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }

  } // end class PlayerStatistic
?>
