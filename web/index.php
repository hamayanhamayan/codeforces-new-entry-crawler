<?php

require('../vendor/autoload.php');

$app = new Silex\Application();

$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Database
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
               array(
                   'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"] . ';port=' . $dbopts["port"],
                   'pdo.username' => $dbopts["user"],
                   'pdo.password' => $dbopts["pass"]
               )
);

// list 一覧
$app->get('/list/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT * FROM entry_table ORDER BY id DESC LIMIT 100');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $row['title'] = htmlspecialchars_decode($row['title']);
    $names[] = $row;
  }

  return $app['twig']->render('list.twig', array('names' => $names));
});

// rss RSS取得
$app->get('/rss/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT * FROM entry_table ORDER BY id DESC LIMIT 30');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $row['title'] = htmlspecialchars_decode($row['title']);
    $names[] = $row;
  }

  return $app['twig']->render('rss.twig', array('items' => $names));
});

// update クロールのジョブを走らせるアドレス
$app->get('/update/', function() use($app) {
  // get json
  $url = "http://codeforces.com/api/recentActions?maxCount=30";
  $json = file_get_contents($url);
  $obj = json_decode($json, true);
   
  // パースに失敗した時は処理終了
  if ($obj === NULL) return;

  foreach($obj['result'] as $query) {
    $entry = $query['blogEntry'];

    $id = $entry['id'];
    $creationTime = date('Y-m-d h:i:s',$entry['creationTimeSeconds']);
    $authorHandle = $entry['authorHandle'];
    $title = strip_tags($entry['title']);

    $st = $app['pdo']->prepare("INSERT INTO entry_table values ($id, '$creationTime', '$authorHandle', '$title')");
    $st->execute();
  }

  return $app['twig']->render('update.twig');
});

// (root) メインページ
$app->get('/', function() use($app) {
  return $app['twig']->render('index.twig');
});

$app->run();
