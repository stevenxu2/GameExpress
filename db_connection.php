<?php
define('DB_DSN', 'mysql:host=10.0.0.42:3306;dbname=game_express;charset=utf8');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
  $db_conn = new PDO(DB_DSN, DB_USER, DB_PASS);
} catch (PDOException $e) {
  print 'Error: ' . $e->getMessage();
  die();
}
