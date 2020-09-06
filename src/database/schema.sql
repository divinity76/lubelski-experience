DROP TABLE IF EXISTS `players`;
CREATE TABLE `players`
(
   `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
   `name` TEXT NOT NULL UNIQUE,
   `notes` TEXT NULL DEFAULT NULL
);
DROP TABLE IF EXISTS `experience`;
CREATE TABLE `experience`
(
   `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
   `timestamp` INTEGER NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `player_id` INTEGER NOT NULL,
   `level_rank` INTEGER NOT NULL,
   `online` INTEGER NOT NULL,
   `level` INTEGER NOT NULL,
   `experience` INTEGER NOT NULL
);
CREATE VIEW `experience_pretty1` AS
SELECT
   players.id AS player_id,
   players.name AS player_name,
   experience.level AS player_level,
   datetime

   (
      experience.`timestamp`,
      'unixepoch',
      'UTC'
   ) AS `date`,
   experience.`timestamp` AS `date_raw`,
   experience.experience AS player_experience
FROM
   experience
INNER
JOIN players ON players.id = experience.player_id;
-- Test Data
INSERT INTO `players`
(
   `id`,
   `name`,
   `notes`
)
VALUES
(
   1,
   'test player',
   'test record'
);
INSERT INTO `experience`
(
   `id`,
   `timestamp`,
   `player_id`,
   `level_rank`,
   `online`,
   `level`,
   `experience`
)
VALUES
(
   1,
   0,
   1,
   123456789,
   1,
   1,
   123
);
INSERT INTO `experience`
(
   `id`,
   `timestamp`,
   `player_id`,
   `level_rank`,
   `online`,
   `level`,
   `experience`
)
VALUES
(
   2,
   1,
   1,
   123456789,
   0,
   1,
   125
);