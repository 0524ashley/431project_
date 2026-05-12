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
--
--  Each team now has 8 active players (3 original + 5 new):
--    Angels  (team 2): 100, 101, 102, 103, 104, 105, 106, 107
--    Dodgers (team 3): 108, 109, 111, 117, 118, 119, 120, 121
--    Giants  (team 4): 112, 113, 114, 122, 123, 124, 125, 126
--  Coaches / manager: 115, 116, 110
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
    -- ── Angels (team 2) ───────────────────────────────────────
    (100, 'bobross@gmail.com',          2, 'Bob',      'Ross'),
    (101, 'sarah.johnson@gmail.com',    2, 'Sarah',    'Johnson'),
    (102, 'mike.davis@gmail.com',       2, 'Mike',     'Davis'),
    (103, 'chris.turner@gmail.com',     2, 'Chris',    'Turner'),
    (104, 'amanda.white@gmail.com',     2, 'Amanda',   'White'),
    (105, 'daniel.hall@gmail.com',      2, 'Daniel',   'Hall'),
    (106, 'megan.clark@gmail.com',      2, 'Megan',    'Clark'),
    (107, 'ryan.lewis@gmail.com',       2, 'Ryan',     'Lewis'),

    -- ── Dodgers (team 3) ──────────────────────────────────────
    (108, 'robertsmith@gmail.com',      3, 'Robert',   'Smith'),
    (109, 'jessica.lee@gmail.com',      3, 'Jessica',  'Lee'),
    (111, 'david.brown@gmail.com',      3, 'David',    'Brown'),
    (117, 'kevin.walker@gmail.com',     3, 'Kevin',    'Walker'),
    (118, 'nicole.harris@gmail.com',    3, 'Nicole',   'Harris'),
    (119, 'brandon.young@gmail.com',    3, 'Brandon',  'Young'),
    (120, 'stephanie.king@gmail.com',   3, 'Stephanie','King'),
    (121, 'eric.scott@gmail.com',       3, 'Eric',     'Scott'),

    -- ── Giants (team 4) ───────────────────────────────────────
    (112, 'emily.wilson@gmail.com',     4, 'Emily',    'Wilson'),
    (113, 'james.martinez@gmail.com',   4, 'James',    'Martinez'),
    (114, 'lisa.garcia@gmail.com',      4, 'Lisa',     'Garcia'),
    (122, 'carlos.rivera@gmail.com',    4, 'Carlos',   'Rivera'),
    (123, 'jennifer.moore@gmail.com',   4, 'Jennifer', 'Moore'),
    (124, 'marcus.taylor@gmail.com',    4, 'Marcus',   'Taylor'),
    (125, 'ashley.thomas@gmail.com',    4, 'Ashley',   'Thomas'),
    (126, 'derek.jackson@gmail.com',    4, 'Derek',    'Jackson'),

    -- ── Coaches / manager ─────────────────────────────────────
    (116, 'coach.maimai@gmail.com',     3, 'Mai',      'Mai'),
    (115, 'coach.angels@gmail.com',     2, 'Tom',      'Anderson'),
    (110, 'joesmith@gmail.com',         1, 'Joe',      'Smith');


-- -------------------------------------------------------------
--  Player_statistics
--  All rows zeroed — recomputed at the end of this script.
-- -------------------------------------------------------------
CREATE TABLE Player_statistics (
    Player_ID                INT(3) UNSIGNED NOT NULL,
    Total_time_on_field_mins INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Total_time_on_field_secs INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Total_goals              INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Total_assists            INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Total_home_runs          INT(3) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (Player_ID),
    FOREIGN KEY (Player_ID) REFERENCES Users_info(ID_num) ON DELETE CASCADE
);

INSERT INTO Player_statistics (Player_ID) VALUES
    -- Angels
    (100), (101), (102), (103), (104), (105), (106), (107),
    -- Dodgers
    (108), (109), (111), (117), (118), (119), (120), (121),
    -- Giants
    (112), (113), (114), (122), (123), (124), (125), (126),
    -- Coaches / manager
    (115), (116), (110);


-- -------------------------------------------------------------
--  Roles
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
--  All 15 new players get bcrypt hash of 'Player123!'
--  (same placeholder hash pattern as existing players).
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

INSERT INTO Users_accounts (User_email, Role_type, Username, Password) VALUES
    -- coaches / manager
    ('coach.maimai@gmail.com',   2, 'maimai',       '$2y$10$u8Qn8yX5d7Tg3Lk9hP2rQeZ7sY4xF9aJ1Kz6LmN0pQrStUvWxYz12'),
    ('joesmith@gmail.com',       3, 'joesmith',     '$2y$10$75fR8ojKgHHt4FxhsNAFCO15sgUDs4TLv6IsCt800VnKnB9P8ZaVy'),
    ('coach.angels@gmail.com',   2, 'coachangels',  '$2y$08$fThT913LjhXwZAV/PKjlWO.mwUZ3Ur9gojyB/NcVquCtcVhjx502.'),

    -- ── Angels original players ────────────────────────────────
    ('bobross@gmail.com',        1, 'bobross',       '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('sarah.johnson@gmail.com',  1, 'sarahjohnson',  '$2y$08$5Qlb4khzI6MbA7IcGryY.O/mmPdHANbjRDq5GeY.K2zHBFyOU6eea'),
    ('mike.davis@gmail.com',     1, 'mikedavis',     '$2y$08$vwlGgbp06vDOpugVJLPmAeUuAbn.7XZIXrHATXOIy.Y5NxEJOQAAS'),

    -- ── Angels new players (103–107) ───────────────────────────
    ('chris.turner@gmail.com',   1, 'christurner',   '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('amanda.white@gmail.com',   1, 'amandawhite',   '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('daniel.hall@gmail.com',    1, 'danielhall',    '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('megan.clark@gmail.com',    1, 'meganclark',    '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),
    ('ryan.lewis@gmail.com',     1, 'ryanlewis',     '$2y$08$YssQagi6/7COYT.S1zL43OyEBNXD/Ahp2C8hs/Km50OOfgHEHx/xe'),

    -- ── Dodgers original players ───────────────────────────────
    ('robertsmith@gmail.com',    1, 'robertsmith',   '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('jessica.lee@gmail.com',    1, 'jessicalee',    '$2y$08$ashLoZCL5Qh80LdIrViwROzsdAMK.lLh2agX3ICMg.qPrc95Fa5za'),
    ('david.brown@gmail.com',    1, 'davidbrown',    '$2y$08$caw/kaoSTALtfV3eLZSfMO6yPcPcpdHj1WeYXiP8cDfo2V1YiAqoO'),

    -- ── Dodgers new players (117–121) ──────────────────────────
    ('kevin.walker@gmail.com',   1, 'kevinwalker',   '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('nicole.harris@gmail.com',  1, 'nicoleharris',  '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('brandon.young@gmail.com',  1, 'brandonyoung',  '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('stephanie.king@gmail.com', 1, 'stephanieking', '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),
    ('eric.scott@gmail.com',     1, 'ericscott',     '$2y$08$JvMdUBd7f9je1AfcD2PCRuTuUTRaYa4yeALwWXjpACG0D9gr0bPoS'),

    -- ── Giants original players ────────────────────────────────
    ('emily.wilson@gmail.com',   1, 'emilywilson',   '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('james.martinez@gmail.com', 1, 'jamesmartinez', '$2y$08$40TVlOq9d52KKsMSJcIje.7WlvO56wz3VK741TaqTrt/XIE4Z37di'),
    ('lisa.garcia@gmail.com',    1, 'lisagarcia',    '$2y$08$Sr3UCslG3ZcxslQlvgyNTO2oHqHgYl7rn5SocLu6vVe1fvG/xRwFq'),

    -- ── Giants new players (122–126) ───────────────────────────
    ('carlos.rivera@gmail.com',  1, 'carlosrivera',  '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('jennifer.moore@gmail.com', 1, 'jennifermoore', '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('marcus.taylor@gmail.com',  1, 'marcustaylor',  '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('ashley.thomas@gmail.com',  1, 'ashleythomas',  '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq'),
    ('derek.jackson@gmail.com',  1, 'derekjackson',  '$2y$08$/OGxsPUEJXZrAfZROh9fcepKr.VwnGy9F1NskGzEQ7gkK4C/Vs6Yq');


-- -------------------------------------------------------------
--  Games
--  5 games covering all three team pairings.
--  Scores start at 0 — recomputed at the end of this script.
--
--  Game 1: Angels  (home)  vs Dodgers (away)
--  Game 2: Dodgers (home)  vs Giants  (away)
--  Game 3: Giants  (home)  vs Angels  (away)
--  Game 4: Angels  (home)  vs Giants  (away)  rematch
--  Game 5: Dodgers (home)  vs Angels  (away)  rematch
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

INSERT INTO Games (Game_ID, Home_Team_ID, Away_Team_ID, Game_date, Location, Home_score, Away_score) VALUES
    (1, 2, 3, '2025-04-10', 'Angel Stadium, Anaheim CA',         0, 0),
    (2, 3, 4, '2025-04-24', 'Dodger Stadium, Los Angeles CA',    0, 0),
    (3, 4, 2, '2025-05-08', 'Oracle Park, San Francisco CA',     0, 0),
    (4, 2, 4, '2025-05-22', 'Angel Stadium, Anaheim CA',         0, 0),
    (5, 3, 2, '2025-06-05', 'Dodger Stadium, Los Angeles CA',    0, 0);


-- -------------------------------------------------------------
--  Games_statistics
--  80 rows total: 5 games × 16 players (8 per team).
--  Format: (Game_ID, Player_ID, mins, secs, Goals, Assists, HR)
--  Stats generated with seed=42 for reproducibility.
-- -------------------------------------------------------------
CREATE TABLE Games_statistics (
    Game_ID            INT(5) UNSIGNED NOT NULL,
    Player_ID          INT(3) UNSIGNED NOT NULL,
    Time_on_field_mins INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Time_on_field_secs INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Goals              INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Assists            INT(3) UNSIGNED NOT NULL DEFAULT 0,
    Home_runs          INT(3) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (Game_ID, Player_ID),
    CONSTRAINT fk_gs_game   FOREIGN KEY (Game_ID)   REFERENCES Games(Game_ID)     ON DELETE CASCADE,
    CONSTRAINT fk_gs_player FOREIGN KEY (Player_ID) REFERENCES Users_info(ID_num) ON DELETE CASCADE
);

INSERT INTO Games_statistics
    (Game_ID, Player_ID, Time_on_field_mins, Time_on_field_secs, Goals, Assists, Home_runs)
VALUES

-- ==============================================================
--  Game 1 — Angels (home, team 2) vs Dodgers (away, team 3)
--  2025-04-10 | Angel Stadium, Anaheim CA
--  Angels goals: 0+1+0+0+1+1+0+2 = 5
--  Dodgers goals: 1+0+2+2+1+1+1+2 = 10
-- ==============================================================
-- Angels
(1, 100, 11,  7, 0, 0, 0),   -- Bob Ross
(1, 101, 11,  6, 1, 2, 0),   -- Sarah Johnson
(1, 102,  9,  2, 0, 0, 1),   -- Mike Davis
(1, 103,  6, 35, 0, 1, 1),   -- Chris Turner
(1, 104,  7, 28, 1, 2, 0),   -- Amanda White
(1, 105,  7, 44, 1, 0, 0),   -- Daniel Hall
(1, 106,  8,  6, 0, 0, 2),   -- Megan Clark
(1, 107, 10, 16, 2, 2, 1),   -- Ryan Lewis
-- Dodgers
(1, 108,  9,  5, 1, 2, 1),   -- Robert Smith
(1, 109,  8, 36, 0, 0, 1),   -- Jessica Lee
(1, 111,  8,  5, 2, 2, 0),   -- David Brown
(1, 117,  9, 40, 2, 0, 0),   -- Kevin Walker
(1, 118, 11, 17, 1, 1, 0),   -- Nicole Harris
(1, 119, 11, 10, 1, 0, 0),   -- Brandon Young
(1, 120,  8, 59, 1, 1, 1),   -- Stephanie King
(1, 121,  6, 14, 2, 2, 0),   -- Eric Scott

-- ==============================================================
--  Game 2 — Dodgers (home, team 3) vs Giants (away, team 4)
--  2025-04-24 | Dodger Stadium, Los Angeles CA
--  Dodgers goals: 2+1+0+1+1+0+1+3 = 9
--  Giants goals:  0+1+2+2+0+0+0+1 = 6
-- ==============================================================
-- Dodgers
(2, 108,  6, 13, 2, 1, 1),   -- Robert Smith
(2, 109,  7, 41, 1, 2, 1),   -- Jessica Lee
(2, 111,  7, 16, 0, 2, 1),   -- David Brown
(2, 117, 11, 37, 1, 1, 0),   -- Kevin Walker
(2, 118,  7, 32, 1, 2, 2),   -- Nicole Harris
(2, 119,  7, 40, 0, 1, 1),   -- Brandon Young
(2, 120,  9, 24, 1, 1, 0),   -- Stephanie King
(2, 121, 10, 55, 3, 1, 0),   -- Eric Scott
-- Giants
(2, 112, 10, 48, 0, 1, 0),   -- Emily Wilson
(2, 113,  9, 10, 1, 3, 2),   -- James Martinez
(2, 114,  8, 32, 2, 1, 0),   -- Lisa Garcia
(2, 122, 11, 19, 2, 1, 0),   -- Carlos Rivera
(2, 123,  8, 48, 0, 3, 2),   -- Jennifer Moore
(2, 124,  6, 38, 0, 0, 2),   -- Marcus Taylor
(2, 125,  8, 15, 0, 2, 2),   -- Ashley Thomas
(2, 126,  6, 46, 1, 0, 1),   -- Derek Jackson

-- ==============================================================
--  Game 3 — Giants (home, team 4) vs Angels (away, team 2)
--  2025-05-08 | Oracle Park, San Francisco CA
--  Giants goals:  1+1+0+1+0+0+0+1 = 4
--  Angels goals:  0+1+1+0+1+2+0+2 = 7
-- ==============================================================
-- Giants
(3, 112,  7,  8, 1, 3, 0),   -- Emily Wilson
(3, 113, 10, 55, 1, 3, 2),   -- James Martinez
(3, 114, 11, 44, 0, 0, 3),   -- Lisa Garcia
(3, 122, 11, 23, 1, 1, 0),   -- Carlos Rivera
(3, 123,  7,  4, 0, 1, 0),   -- Jennifer Moore
(3, 124,  7,  0, 0, 1, 0),   -- Marcus Taylor
(3, 125,  6, 55, 0, 1, 0),   -- Ashley Thomas
(3, 126,  9, 13, 1, 2, 2),   -- Derek Jackson
-- Angels
(3, 100, 10, 30, 0, 1, 0),   -- Bob Ross
(3, 101,  6,  6, 1, 1, 0),   -- Sarah Johnson
(3, 102, 11,  3, 1, 3, 0),   -- Mike Davis
(3, 103,  9, 46, 0, 2, 0),   -- Chris Turner
(3, 104,  7, 34, 1, 1, 0),   -- Amanda White
(3, 105,  7, 55, 2, 1, 2),   -- Daniel Hall
(3, 106, 10,  6, 0, 3, 2),   -- Megan Clark
(3, 107,  6, 59, 2, 0, 0),   -- Ryan Lewis

-- ==============================================================
--  Game 4 — Angels (home, team 2) vs Giants (away, team 4)
--  2025-05-22 | Angel Stadium, Anaheim CA
--  Angels goals: 2+0+3+0+1+1+2+0 = 9
--  Giants goals: 1+3+0+2+0+0+1+0 = 7
-- ==============================================================
-- Angels
(4, 100,  9, 13, 2, 2, 0),   -- Bob Ross
(4, 101,  6, 24, 0, 2, 0),   -- Sarah Johnson
(4, 102,  9, 44, 3, 3, 1),   -- Mike Davis
(4, 103, 11, 31, 0, 0, 3),   -- Chris Turner
(4, 104, 10, 47, 1, 2, 0),   -- Amanda White
(4, 105, 10, 30, 1, 2, 0),   -- Daniel Hall
(4, 106, 10,  5, 2, 0, 0),   -- Megan Clark
(4, 107,  7, 25, 0, 2, 0),   -- Ryan Lewis
-- Giants
(4, 112, 10,  2, 1, 1, 1),   -- Emily Wilson
(4, 113, 10, 20, 3, 0, 1),   -- James Martinez
(4, 114,  7, 16, 0, 1, 0),   -- Lisa Garcia
(4, 122,  8, 59, 2, 0, 0),   -- Carlos Rivera
(4, 123, 10,  6, 0, 0, 0),   -- Jennifer Moore
(4, 124,  8, 56, 0, 0, 0),   -- Marcus Taylor
(4, 125,  9, 53, 1, 0, 3),   -- Ashley Thomas
(4, 126, 11, 33, 0, 2, 0),   -- Derek Jackson

-- ==============================================================
--  Game 5 — Dodgers (home, team 3) vs Angels (away, team 2)
--  2025-06-05 | Dodger Stadium, Los Angeles CA
--  Dodgers goals: 3+1+0+2+0+0+0+0 = 6
--  Angels goals:  0+0+1+2+1+1+1+0 = 6
-- ==============================================================
-- Dodgers
(5, 108, 11,  6, 3, 0, 0),   -- Robert Smith
(5, 109,  6, 47, 1, 0, 1),   -- Jessica Lee
(5, 111, 11, 21, 0, 1, 0),   -- David Brown
(5, 117,  9, 16, 2, 2, 0),   -- Kevin Walker
(5, 118,  9, 53, 0, 0, 1),   -- Nicole Harris
(5, 119, 11, 16, 0, 1, 1),   -- Brandon Young
(5, 120, 10,  0, 0, 3, 1),   -- Stephanie King
(5, 121,  7, 34, 0, 1, 1),   -- Eric Scott
-- Angels
(5, 100,  9,  8, 0, 1, 2),   -- Bob Ross
(5, 101,  6, 57, 0, 1, 1),   -- Sarah Johnson
(5, 102,  8, 49, 1, 2, 3),   -- Mike Davis
(5, 103, 11,  9, 2, 0, 0),   -- Chris Turner
(5, 104,  7, 56, 1, 0, 2),   -- Amanda White
(5, 105,  9, 51, 1, 2, 0),   -- Daniel Hall
(5, 106,  7, 50, 1, 1, 0),   -- Megan Clark
(5, 107,  9, 14, 0, 2, 0);   -- Ryan Lewis

-- Total rows: 5 games × 16 players = 80


-- =============================================================
--  Recompute Games.Home_score / Away_score from Games_statistics
--
--  Home_score = SUM(Goals) for players whose Team_num = Home_Team_ID
--  Away_score = SUM(Goals) for players whose Team_num = Away_Team_ID
-- =============================================================
UPDATE Games G
SET
    Home_score = (
        SELECT COALESCE(SUM(GS.Goals), 0)
        FROM   Games_statistics AS GS
        JOIN   Users_info       AS UI ON UI.ID_num = GS.Player_ID
        WHERE  GS.Game_ID  = G.Game_ID
          AND  UI.Team_num = G.Home_Team_ID
    ),
    Away_score = (
        SELECT COALESCE(SUM(GS.Goals), 0)
        FROM   Games_statistics AS GS
        JOIN   Users_info       AS UI ON UI.ID_num = GS.Player_ID
        WHERE  GS.Game_ID  = G.Game_ID
          AND  UI.Team_num = G.Away_Team_ID
    );


-- =============================================================
--  Recompute Player_statistics totals from Games_statistics
--
--  Coaches / manager (110, 115, 116) have no game rows —
--  their totals correctly stay at 0.
--
--  Time normalisation:
--    raw_secs = SUM(mins*60 + secs)
--    Total_time_on_field_mins = FLOOR(raw_secs / 60)
--    Total_time_on_field_secs = MOD(raw_secs, 60)
-- =============================================================
UPDATE Player_statistics PS
SET
    Total_goals = (
        SELECT COALESCE(SUM(GS.Goals), 0)
        FROM   Games_statistics GS
        WHERE  GS.Player_ID = PS.Player_ID
    ),
    Total_assists = (
        SELECT COALESCE(SUM(GS.Assists), 0)
        FROM   Games_statistics GS
        WHERE  GS.Player_ID = PS.Player_ID
    ),
    Total_home_runs = (
        SELECT COALESCE(SUM(GS.Home_runs), 0)
        FROM   Games_statistics GS
        WHERE  GS.Player_ID = PS.Player_ID
    ),
    Total_time_on_field_mins = (
        SELECT FLOOR(COALESCE(SUM(GS.Time_on_field_mins * 60 + GS.Time_on_field_secs), 0) / 60)
        FROM   Games_statistics GS
        WHERE  GS.Player_ID = PS.Player_ID
    ),
    Total_time_on_field_secs = (
        SELECT MOD(COALESCE(SUM(GS.Time_on_field_mins * 60 + GS.Time_on_field_secs), 0), 60)
        FROM   Games_statistics GS
        WHERE  GS.Player_ID = PS.Player_ID
    );


-- =============================================================
--  MySQL Database Users and Privileges
-- =============================================================

-- -------------------------------------------------------------
--  DB_identity — read-only, login use only
-- -------------------------------------------------------------
DROP USER IF EXISTS 'DB_identity'@'localhost';
CREATE USER 'DB_identity'@'localhost' IDENTIFIED BY 'IdentitySecret';
GRANT SELECT (User_email, Username, Password, Role_type) ON Baseball.Users_accounts TO 'DB_identity'@'localhost';
GRANT SELECT (ID, Role_name)                             ON Baseball.Roles           TO 'DB_identity'@'localhost';
GRANT SELECT (Email, Team_num)                           ON Baseball.Users_info      TO 'DB_identity'@'localhost';

-- -------------------------------------------------------------
--  App_register — used by register.php only
-- -------------------------------------------------------------
DROP USER IF EXISTS 'App_register'@'localhost';
CREATE USER 'App_register'@'localhost' IDENTIFIED BY 'RegisterSecret';
GRANT SELECT         ON Baseball.Teams             TO 'App_register'@'localhost';
GRANT SELECT         ON Baseball.Roles             TO 'App_register'@'localhost';
GRANT SELECT, INSERT ON Baseball.Users_info        TO 'App_register'@'localhost';
GRANT SELECT, INSERT ON Baseball.Users_accounts    TO 'App_register'@'localhost';
GRANT INSERT         ON Baseball.Player_statistics TO 'App_register'@'localhost';

-- -------------------------------------------------------------
--  Observer  (Role_type = 1) — read-only + own password change
-- -------------------------------------------------------------
DROP USER IF EXISTS 'Observer'@'localhost';
CREATE USER 'Observer'@'localhost' IDENTIFIED BY 'ObserverSecret';
GRANT SELECT ON Baseball.Teams             TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Users_info        TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Player_statistics TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Games_statistics  TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Games             TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Roles             TO 'Observer'@'localhost';
GRANT SELECT ON Baseball.Users_accounts    TO 'Observer'@'localhost';
GRANT UPDATE (Password) ON Baseball.Users_accounts TO 'Observer'@'localhost';

-- -------------------------------------------------------------
--  User  (Role_type = 2)
-- -------------------------------------------------------------
DROP USER IF EXISTS 'User'@'localhost';
CREATE USER 'User'@'localhost' IDENTIFIED BY 'UserSecret';
GRANT SELECT ON Baseball.Teams             TO 'User'@'localhost';
GRANT SELECT ON Baseball.Users_info        TO 'User'@'localhost';
GRANT SELECT ON Baseball.Player_statistics TO 'User'@'localhost';
GRANT SELECT ON Baseball.Games_statistics  TO 'User'@'localhost';
GRANT SELECT ON Baseball.Games             TO 'User'@'localhost';
GRANT SELECT ON Baseball.Roles             TO 'User'@'localhost';
GRANT SELECT ON Baseball.Users_accounts    TO 'User'@'localhost';
GRANT UPDATE                         ON Baseball.Player_statistics TO 'User'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Games_statistics  TO 'User'@'localhost';
GRANT UPDATE (Password)              ON Baseball.Users_accounts    TO 'User'@'localhost';

-- -------------------------------------------------------------
--  Manager  (Role_type = 3) — full access, no DDL
-- -------------------------------------------------------------
DROP USER IF EXISTS 'Manager'@'localhost';
CREATE USER 'Manager'@'localhost' IDENTIFIED BY 'ManagerSecret';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Teams             TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Users_info        TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Player_statistics TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Games_statistics  TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Games             TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Roles             TO 'Manager'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Baseball.Users_accounts    TO 'Manager'@'localhost';


FLUSH PRIVILEGES;
