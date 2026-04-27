<?php
// =============================================================
//  PlayerStatistic — represents one row from Users_statistics,
//  modelled after the course-provided example class.
//
//    PlayerStatistic::getById($db, $playerId)
//        Returns PlayerStatistic|null for the given Player_ID.
//
//    PlayerStatistic::update($db, $playerId, $data)
//        Updates an existing stats row. Returns true on success.
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


  // =============================================================
  //  playerId() prototypes:
  //    int    playerId()            returns the player's DB ID.
  //    void   playerId(int $value)  sets the player's DB ID.
  // =============================================================
  function playerId()
    {
    // int playerId()
    if (func_num_args() == 0)
      {
      return $this->playerId;
      }

    // void playerId($value)
    else if (func_num_args() == 1)
      {
      $this->playerId = (int)func_get_arg(0);
      }

    return $this;
    }


  // =============================================================
  //  time_on_field() prototypes:
  //    string time_on_field()
  //        Returns time in "minutes:seconds" format.
  //
  //    void time_on_field(string $value)
  //        Sets time in "minutes:seconds" format.
  //
  //    void time_on_field(array $value)
  //        Sets time in [minutes, seconds] format.
  //
  //    void time_on_field(int $minutes, int $seconds)
  //        Sets time using two separate values.
  // =============================================================
  function time_on_field()
    {
    // string time_on_field()
    if (func_num_args() == 0)
      {
      return $this->time_on_field['MINS'] . ':' . $this->time_on_field['SECS'];
      }

    // void time_on_field($value)
    else if (func_num_args() == 1)
      {
      $value = func_get_arg(0);

      if (is_string($value)) $value = explode(':', $value); // convert string to array
      if (is_array($value))
        {
        if (count($value) >= 2) $this->time_on_field['SECS'] = (int)$value[1];
        else                    $this->time_on_field['SECS'] = 0;
        $this->time_on_field['MINS'] = (int)$value[0];
        }
      }

    // void time_on_field($mins, $secs)
    else if (func_num_args() == 2)
      {
      $this->time_on_field['MINS'] = (int)func_get_arg(0);
      $this->time_on_field['SECS'] = (int)func_get_arg(1);
      }

    return $this;
    }


  // =============================================================
  //  goals() prototypes:
  //    int  goals()            returns the number of goals scored.
  //    void goals(int $value)  sets the number of goals scored.
  // =============================================================
  function goals()
    {
    // int goals()
    if (func_num_args() == 0)
      {
      return $this->goals;
      }

    // void goals($value)
    else if (func_num_args() == 1)
      {
      $this->goals = (int)func_get_arg(0);
      }

    return $this;
    }


  // =============================================================
  //  assists() prototypes:
  //    int  assists()            returns the number of assists.
  //    void assists(int $value)  sets the number of assists.
  // =============================================================
  function assists()
    {
    // int assists()
    if (func_num_args() == 0)
      {
      return $this->assists;
      }

    // void assists($value)
    else if (func_num_args() == 1)
      {
      $this->assists = (int)func_get_arg(0);
      }

    return $this;
    }


  // =============================================================
  //  home_runs() prototypes:
  //    int  home_runs()            returns the number of home runs.
  //    void home_runs(int $value)  sets the number of home runs.
  // =============================================================
  function home_runs()
    {
    // int home_runs()
    if (func_num_args() == 0)
      {
      return $this->home_runs;
      }

    // void home_runs($value)
    else if (func_num_args() == 1)
      {
      $this->home_runs = (int)func_get_arg(0);
      }

    return $this;
    }


  // =============================================================
  //  Constructor
  //  Accepts individual positional arguments, or a single
  //  tab-separated string containing all values in order:
  //    playerId, time_on_field, goals, assists, home_runs
  // =============================================================
  function __construct($playerId = 0, $time = "0:0", $goals = 0, $assists = 0, $home_runs = 0)
    {
    // If $playerId contains a tab character, all attributes are
    // packed into a single tab-separated string.
    if (is_string($playerId) && strpos($playerId, "\t") !== false)
      {
      list($playerId, $time, $goals, $assists, $home_runs) = explode("\t", $playerId);
      }

    // Delegate to setter methods so validation logic is applied.
    $this->playerId($playerId);
    $this->time_on_field($time);
    $this->goals($goals);
    $this->assists($assists);
    $this->home_runs($home_runs);
    }


  // =============================================================
  //  __toString()
  // =============================================================
  function __toString()
    {
    return var_export($this, true);
    }


  // =============================================================
  //  toTSV()
  //  Returns a tab-separated string of all instance attributes.
  // =============================================================
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


  // =============================================================
  //  fromTSV(string $tsvString)
  //  Sets instance attributes from a tab-separated string
  //  in the same order as toTSV().
  // =============================================================
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
  //  Fetches the stats row for the given Player_ID.
  //  Returns PlayerStatistic|null.
  // =============================================================
  public static function getById(mysqli $db, int $playerId)
    {
    $stmt = $db->prepare("
      SELECT Player_ID,
             Goals,
             Assists,
             Home_runs,
             Time_on_field_mins,
             Time_on_field_secs
      FROM   Users_statistics
      WHERE  Player_ID = ?
    ");
    if (!$stmt) return null;

    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $stmt->bind_result($pid, $goals, $assists, $home_runs, $t_mins, $t_secs);
    $stat = null;
    if ($stmt->fetch())
      {
      $stat = new PlayerStatistic($pid, $t_mins . ':' . $t_secs, $goals, $assists, $home_runs);
      }
    $stmt->close();
    return $stat;
    }


  // =============================================================
  //  PlayerStatistic::update($db, $playerId, $data)
  //  Updates the stats row for the given Player_ID.
  //  $data keys: goals, assists, home_runs, time_mins, time_secs
  //  Returns true on success.
  // =============================================================
  public static function update(mysqli $db, int $playerId, array $data)
    {
    $goals     = max(0, (int)($data['goals']     ?? 0));
    $assists   = max(0, (int)($data['assists']   ?? 0));
    $home_runs = max(0, (int)($data['home_runs'] ?? 0));
    $mins      = max(0, (int)($data['time_mins'] ?? 0));
    $secs      = min(59, max(0, (int)($data['time_secs'] ?? 0)));

    $stmt = $db->prepare("
      UPDATE Users_statistics
      SET    Goals              = ?,
             Assists            = ?,
             Home_runs          = ?,
             Time_on_field_mins = ?,
             Time_on_field_secs = ?
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
  //  Inserts a zeroed stats row for a newly registered player.
  //  Called by register.php after inserting into Users_info.
  //  Returns true on success.
  // =============================================================
  public static function insertDefault(mysqli $db, int $playerId)
    {
    $stmt = $db->prepare("
      INSERT INTO Users_statistics (Player_ID) VALUES (?)
    ");
    if (!$stmt) return false;

    $stmt->bind_param('i', $playerId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
    }

  } // end class PlayerStatistic
?>
