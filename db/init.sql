CREATE DATABASE datascraper-test;
use datascraper-test;

CREATE TABLE darkpools (
  pid INT,
  last_dir VARCHAR(20),
  last FLOAT
);

INSERT INTO darkpools
  (pid, last_dir, last)
VALUES
  (1, greenBg, 1.1934),
  (1, redBG, 1.1935);

