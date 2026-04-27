
-- =============================================================
--  Baseball League Database
--  DDL Script — drops and rebuilds from scratch on every run
-- =============================================================

DROP DATABASE IF EXISTS Baseball;
CREATE DATABASE Baseball;
USE Baseball;


-- -------------------------------------------------------------
--  Teams
-- -------------------------------------------------------------
CREATE TABLE Teams (
    ID   INT(3) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(30) NOT NULL
);

INSERT INTO Teams (ID, Name) VALUES
    (1, 'N/A'),
    (2, 'Angels'),
    (3, 'Dodgers'),
    (4, 'Giants');

-- -------------------------------------------------------------
--  Users_info
--  Email is the natural PK and FK target for Users_accounts.
--  ID_num is the surrogate PK and FK target for Users_statistics.
--  Team_num is set at registration (user picks their team).
--  Defaults to 1 (N/A) so the column is never null.
--  A manager can reassign Team_num later.
-- -------------------------------------------------------------
CREATE TABLE Users_info (
    ID_num     INT(3) UNSIGNED AUTO_INCREMENT,
    Email      VARCHAR(50)  NOT NULL,
    Team_num   INT(3) UNSIGNED NOT NULL DEFAULT 1,
    First_name VARCHAR(30)  NOT NULL,
    Last_name  VARCHAR(30)  NOT NULL,
    PRIMARY KEY (Email),
    UNIQUE  KEY (ID_num),
    FOREIGN KEY (Team_num) REFERENCES Teams(ID) ON DELETE RESTRICT
);

INSERT INTO Users_info (ID_num, Email, Team_num, First_name, Last_name) VALUES
        -- Team 2: Angels players
    (100, 'bobross@gmail.com',        2, 'Bob',      'Ross'),
    (101, 'sarah.johnson@gmail.com',  2, 'Sarah',    'Johnson'),
    (102, 'mike.davis@gmail.com',     2, 'Mike',     'Davis'),

    -- Team 3: Dodgers players
    (108, 'robertsmith@gmail.com',    3, 'Robert',   'Smith'),
    (109, 'jessica.lee@gmail.com',    3, 'Jessica',  'Lee'),
    (111, 'david.brown@gmail.com',    3, 'David',    'Brown'),

    -- Team 4: Giants players
    (112, 'emily.wilson@gmail.com',   4, 'Emily',    'Wilson'),
    (113, 'james.martinez@gmail.com', 4, 'James',    'Martinez'),
    (114, 'lisa.garcia@gmail.com',    4, 'Lisa',     'Garcia'),

    -- Manager / coaches
    (116, 'coach.maimai@gmail.com', 3, 'Mai', 'Mai'),
    (115, 'coach.angels@gmail.com',   2, 'Tom',      'Anderson'),
    (110, 'joesmith@gmail.com',    1, 'Joe',    'Smith');


-- -------------------------------------------------------------
--  Users_statistics
--  Player_ID is a FK to Users_info.ID_num (the surrogate key).
--  One stats row per player, inserted at registration.
--  "user" role can update stats for players on their own team
--  only — this is enforced at the application layer via a
--  Team_num check before any UPDATE is issued.
-- -------------------------------------------------------------
CREATE TABLE Users_statistics (
    Player_ID          INT(3) UNSIGNED NOT NULL,
    Time_on_field_mins INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Time_on_field_secs INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Goals              INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Assists            INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Home_runs          INT(3) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (Player_ID),
    FOREIGN KEY (Player_ID) REFERENCES Users_info(ID_num) ON DELETE CASCADE
);

INSERT INTO Users_statistics (Player_ID, 
    Time_on_field_mins,
    Time_on_field_secs,
    Goals,
    Assists,
    Home_runs) VALUES
    (100, 45, 30, 3, 2, 5),
    (101, 38, 20, 2, 4, 3),
    (102, 50, 10, 4, 1, 6),
    (108, 42, 15, 5, 3, 7),
    (109, 35, 40, 1, 5, 2),
    (111, 47,  5, 3, 3, 4),
    (112, 39, 25, 2, 2, 3),
    (113, 44, 50, 4, 2, 5),
    (114, 41, 35, 3, 4, 4);
INSERT INTO Users_statistics (Player_ID) VALUES
    (115),
    (116),
    (110);

-- -------------------------------------------------------------
--  Roles
--  Three application roles only. Role-to-DB-credential mapping
--  lives in PHP — no passwords stored here.
--    1 = observer  ->  read-only + own password change
--    2 = user      ->  read + update own team's statistics
--    3 = manager   ->  full access (no schema changes)
-- -------------------------------------------------------------
CREATE TABLE Roles (
    ID        INT(3) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Role_name VARCHAR(30) NOT NULL UNIQUE
);

INSERT INTO Roles (ID, Role_name) VALUES
    (1, 'observer'),
    (2, 'user'),
    (3, 'manager');


-- -------------------------------------------------------------
--  Users_accounts
--  User_email is PK and FK back to Users_info.
--  Password stores bcrypt hash — VARCHAR(255) is required
--  since bcrypt output is 60 chars and future algorithms may
--  be longer. Do NOT use VARCHAR(100) — it will truncate hashes.
--  Password uniqueness is NOT enforced at the DB level; bcrypt
--  random salts already guarantee unique hashes in practice,
--  and a UNIQUE constraint here would cause false failures.
-- -------------------------------------------------------------
CREATE TABLE Users_accounts (
    User_email VARCHAR(50)  NOT NULL,
    Role_type  INT(3) UNSIGNED NOT NULL DEFAULT 1,
    Username   VARCHAR(50)  NOT NULL UNIQUE,
    Password   VARCHAR(255) NOT NULL,
    PRIMARY KEY (User_email),
    FOREIGN KEY (User_email) REFERENCES Users_info(Email)  ON DELETE CASCADE,
    FOREIGN KEY (Role_type)  REFERENCES Roles(ID)
);

-- NOTE: Replace the placeholder hashes below before deploying.
-- Generate real hashes with: php -r "echo password_hash('TempPass1!', PASSWORD_DEFAULT);"

-- bobross      = observer, Angels  (team 2)  pwd: Password123!
-- other player = pwd: Player123!
-- maimai       = user,     Dodgers (team 3)  pwd: Baseball2024
-- joesmith     = manager,  N/A     (team 1)  pwd: TeamManager#1

INSERT INTO Users_accounts (User_email, Role_type, Username, Password) VALUES
    ('coach.maimai@gmail.com', 2, 'maimai', '$2y$10$u8Qn8yX5d7Tg3Lk9hP2rQeZ7sY4xF9aJ1Kz6LmN0pQrStUvWxYz12'),
    ('joesmith@gmail.com',    3, 'joesmith',    '$2y$10$75fR8ojKgHHt4FxhsNAFCO15sgUDs4TLv6IsCt800VnKnB9P8ZaVy'),

    -- Angels players / observers
    ('bobross@gmail.com',        1, 'bobross',       '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('sarah.johnson@gmail.com',  1, 'sarahjohnson',  '$2y$08$5Qlb4khzI6MbA7IcGryY.O/mmPdHANbjRDq5GeY.K2zHBFyOU6eea'),
    ('mike.davis@gmail.com',     1, 'mikedavis',     '$2y$08$vwlGgbp06vDOpugVJLPmAeUuAbn.7XZIXrHATXOIy.Y5NxEJOQAAS'),

    -- Dodgers players / observers, except robertsmith is existing coach/user
    ('robertsmith@gmail.com',    1, 'robertsmith',   '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('jessica.lee@gmail.com',    1, 'jessicalee',    '$2y$08$ashLoZCL5Qh80LdIrViwROzsdAMK.lLh2agX3ICMg.qPrc95Fa5za'),
    ('david.brown@gmail.com',    1, 'davidbrown',    '$2y$08$caw/kaoSTALtfV3eLZSfMO6yPcPcpdHj1WeYXiP8cDfo2V1YiAqoO'),

    -- Giants players / observers; Team 4 has no coach yet
    ('emily.wilson@gmail.com',   1, 'emilywilson',   '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('james.martinez@gmail.com', 1, 'jamesmartinez', '$2y$08$40TVlOq9d52KKsMSJcIje.7WlvO56wz3VK741TaqTrt/XIE4Z37di'),
    ('lisa.garcia@gmail.com',    1, 'lisagarcia',    '$2y$08$Sr3UCslG3ZcxslQlvgyNTO2oHqHgYl7rn5SocLu6vVe1fvG/xRwFq'),

    ('coach.angels@gmail.com',   2, 'coachangels',   '$2y$08$fThT913LjhXwZAV/PKjlWO.mwUZ3Ur9gojyB/NcVquCtcVhjx502.');


-- -------------------------------------------------------------
--  Games
--  Both Home_Team_ID and Away_Team_ID reference Teams.ID.
--  Constraints are named explicitly to avoid MySQL errors when
--  two FKs point to the same parent table.
-- -------------------------------------------------------------
CREATE TABLE Games (
    Game_ID      INT(5) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Home_Team_ID INT(3) UNSIGNED NOT NULL,
    Away_Team_ID INT(3) UNSIGNED NOT NULL,
    Game_date    DATE,
    Location     VARCHAR(100),
    Home_score   INT(3) UNSIGNED DEFAULT 0,
    Away_score   INT(3) UNSIGNED DEFAULT 0,
    CONSTRAINT fk_home_team FOREIGN KEY (Home_Team_ID) REFERENCES Teams(ID),
    CONSTRAINT fk_away_team FOREIGN KEY (Away_Team_ID) REFERENCES Teams(ID)
);

INSERT INTO Games (Home_Team_ID, Away_Team_ID, Game_date, Location, Home_score, Away_score) VALUES
    (2, 3, '2025-04-10', 'Angel Stadium, Anaheim CA',      5, 3),
    (3, 2, '2025-04-17', 'Dodger Stadium, Los Angeles CA', 2, 4),
    (2, 4, '2025-04-24', 'Angel Stadium, Anaheim CA',      7, 2),
    (4, 3, '2025-05-01', 'Oracle Park, San Francisco CA',  4, 6),
    (3, 4, '2025-05-08', 'Dodger Stadium, Los Angeles CA', 8, 1),
    (4, 2, '2025-05-15', 'Oracle Park, San Francisco CA',  3, 5),
    (2, 3, '2025-05-22', 'Angel Stadium, Anaheim CA',      6, 4),
    (3, 2, '2025-05-29', 'Dodger Stadium, Los Angeles CA', 9, 0),
    (4, 3, '2025-06-05', 'Oracle Park, San Francisco CA',  2, 7),
    (2, 4, '2025-06-12', 'Angel Stadium, Anaheim CA',      1, 8),
    (3, 4, '2025-06-19', 'Dodger Stadium, Los Angeles CA', 5, 3),
    (4, 2, '2025-06-26', 'Oracle Park, San Francisco CA',  6, 4);


-- =============================================================
--  MySQL Database Users and Privileges
--
--  Auth flow summary:
--    1. PHP always connects as DB_identity first (login only)
--    2. DB_identity returns the bcrypt hash + role name
--    3. PHP runs password_verify() — plaintext never hits DB
--    4. On success, PHP opens a second connection using the
--       role's DB credentials and stores them in $_SESSION
--    5. Every page after login uses $_SESSION['db_user'] and
--       $_SESSION['db_pass'] to open the right DB connection
-- =============================================================


-- -------------------------------------------------------------
--  DB_identity
--  The ONLY hardcoded credential in the PHP application.
--  Used solely during login to fetch the hash and role.
--  Has no write access anywhere.
-- -------------------------------------------------------------
DROP USER IF EXISTS 'DB_identity'@'localhost';
CREATE USER 'DB_identity'@'localhost' IDENTIFIED BY 'IdentitySecret';
GRANT SELECT (User_email, Username, Password, Role_type) ON Baseball.Users_accounts TO 'DB_identity'@'localhost';
GRANT SELECT (ID, Role_name)                 ON Baseball.Roles           TO 'DB_identity'@'localhost';
GRANT SELECT (Email, Team_num)                           ON Baseball.Users_info      TO 'DB_identity'@'localhost';

-- -------------------------------------------------------------
--  App_register
--  Used by register.php only (unauthenticated users).
--  Needs SELECT to check duplicate email/username and to
--  populate the team dropdown. Needs INSERT to create new rows.
--  No UPDATE or DELETE — a visitor cannot modify existing data.
-- -------------------------------------------------------------
DROP USER IF EXISTS 'App_register'@'localhost';
CREATE USER 'App_register'@'localhost' IDENTIFIED BY 'RegisterSecret';
GRANT SELECT          ON Baseball.Teams          TO 'App_register'@'localhost';
GRANT SELECT          ON Baseball.Roles          TO 'App_register'@'localhost';
GRANT SELECT, INSERT  ON Baseball.Users_info     TO 'App_register'@'localhost';
GRANT SELECT, INSERT  ON Baseball.Users_accounts TO 'App_register'@'localhost';


-- -------------------------------------------------------------
--  Observer  (Role_type = 1)
--  Read-only across all tables.
--  Can UPDATE only the Password column on Users_accounts.
--  PHP enforces "own row only" by filtering:
--    WHERE User_email = $_SESSION['email']
--  before issuing any UPDATE — the DB grants column access,
--  the application enforces the row-level restriction.
-- -------------------------------------------------------------
DROP USER IF EXISTS 'Observer'@'localhost';
CREATE USER 'Observer'@'localhost' IDENTIFIED BY 'ObserverSecret';
GRANT SELECT ON Baseball.Teams            TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Users_info       TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Users_statistics TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Games            TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Roles            TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Users_accounts   TO 'Observer'@'localhost';
GRANT UPDATE (Password) ON Baseball.Users_accounts TO 'Observer'@'localhost';


-- -------------------------------------------------------------
--  User  (Role_type = 2)
--  Same read access as Observer.
--  Can UPDATE and INSERT statistics rows — but only for players
--  on their own team. PHP enforces this by:
--    1. Storing $_SESSION['team_num'] at login (fetched via JOIN
--       Users_accounts -> Users_info at the time of auth)
--    2. Before any stats write, confirming the target player's
--       Team_num matches $_SESSION['team_num']
--  The DB grants the column access; row-level restriction is
--  in the application query logic.
--  Can also change their own password (same pattern as Observer).
-- -------------------------------------------------------------
DROP USER IF EXISTS 'User'@'localhost';
CREATE USER 'User'@'localhost' IDENTIFIED BY 'UserSecret';
GRANT SELECT ON Baseball.Teams            TO 'User'@'localhost';
GRANT SELECT ON Baseball.Users_info       TO 'User'@'localhost';
GRANT SELECT ON Baseball.Users_statistics TO 'User'@'localhost';
GRANT SELECT ON Baseball.Games            TO 'User'@'localhost';
GRANT SELECT ON Baseball.Roles            TO 'User'@'localhost';
GRANT SELECT ON Baseball.Users_accounts   TO 'User'@'localhost';
GRANT UPDATE, INSERT ON Baseball.Users_statistics TO 'User'@'localhost';
GRANT UPDATE (Password) ON Baseball.Users_accounts TO 'User'@'localhost';


-- -------------------------------------------------------------
--  Manager  (Role_type = 3)
--  Full INSERT / UPDATE / DELETE on all tables.
--  Can reset any user's password, reassign teams, change roles.
--  Cannot ALTER or DROP tables — no DDL privileges granted.
-- -------------------------------------------------------------
DROP USER IF EXISTS 'Manager'@'localhost';
CREATE USER 'Manager'@'localhost' IDENTIFIED BY 'ManagerSecret';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Teams            TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Users_info       TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Users_statistics TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Games            TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Roles            TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Users_accounts   TO 'Manager'@'localhost';


FLUSH PRIVILEGES;