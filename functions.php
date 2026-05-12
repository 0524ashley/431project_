<?php
  function Format($name, $top, $left, $font_size, $foreground, $border_size,
    $border_color, $background, $width, $height)
    {
    print("
      <style>
        
        #$name
          {
          position: absolute;
          top: $top"."%;
          left: $left"."%;
          font-size: $font_size"."%;
          color: $foreground;
          border: $border_size"."px solid $border_color;
          background: $background;
          width: $width"."%;
          height: $height"."%;
          }
      </style>
          ");
    }

  function UserCheck($bool)
    {
    if (session_status() === PHP_SESSION_NONE)
      {
      session_start();
      }
    if ($bool)
      {
      if (isset($_SESSION['db_user']))
        {
        header("Location: home_page.php");
        exit;
        }
      }
    else
      {
      if (!isset($_SESSION['db_user']))
        {
        header("Location: login_page.php");
        exit;
        }
      }
    return 0;
    }

  // MenuOpen($bool, $display_name, $team_name, $role)
  //   $bool         — true = menu is open, false = closed
  //   $display_name — logged-in user's full name
  //   $team_name    — name of their team (e.g. "Angels")
  //   $role         — role string: 'observer' | 'user' | 'manager'
  function MenuOpen($bool, $display_name = 'User', $team_name = '', $role = '')
    {
    if ($bool)
      {
      Format("textb",  0,  0, 100, "black", 2, "black", "gray", 10, 99.6);
      Format("textbb", 0, 10,  10, "black", 2, "black", "gray",  5,  4);
      $menu_action = "Close Menu";
      }
    else
      {
      Format("textb",  0, -11, 100, "black", 2, "black", "gray", 10, 99.6);
      Format("textbb", 0,   0,  10, "black", 2, "black", "gray",  5,  4);
      $menu_action = "Open Menu";
      }

    // Role badge label
    $role_label = '';
    if ($role === 'manager')       $role_label = 'Manager';
    elseif ($role === 'user')      $role_label = 'Coach';
    elseif ($role === 'observer')  $role_label = 'Player';

    // Team line — managers may be on team N/A, show nothing in that case
    $team_line = (!empty($team_name) && $team_name !== 'N/A')
                 ? htmlspecialchars($team_name)
                 : 'No Team';

    // Management link — managers only; coaches get "My Team" link
    $management_link = '';
    if ($role === 'manager')
      $management_link = "<a href='role_management_page.php'>Management</a>";
    elseif ($role === 'user')
      $management_link = "<a href='role_management_page.php'>My Team</a>";

    echo "
      <div id='textb'>
        <div class='user-info'>
          <strong>Name:</strong> 
          <strong>" . htmlspecialchars($display_name) . "</strong><br/>
          Team:
          " . $team_line . "<br/>
          <span class='role-badge'>" . htmlspecialchars($role_label) . "</span>
        </div>

        <br/>
        <br/>

        <div class='menu-label'>Menu Options:</div>
        <a href='home_page.php'>All Teams</a>
        <a href='game_page.php'>All Games</a>
        <a href='test.php'>Change Password</a>
        " . $management_link . "
        <a href='logout.php'>Sign Out</a>
      </div>

      <form method='post'>
        <div id='textbb'>
          <input type='submit' name='button' value='" . $menu_action . "' style='width: 100%; height: 100%;'>
        </div>
      </form>";
    }
?>