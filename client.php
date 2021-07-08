<?php
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

$address = '127.0.0.1';

$options = getopt('p:', ['path:']);
if (empty($options)) {
  die('Укажите обязательные параметры');
}

$path = ($options['path']) ?? ($options['p']);
$configs = \Symfony\Component\Yaml\Yaml::parseFile(trim($path));

$port = filter_var($configs['port'], FILTER_VALIDATE_INT);
if ($port === false) {
  echo "Номер порта должен быть целым числом \n";
  die();
};

echo "<h2>Соединение TCP/IP</h2>\n";
/* Создаём сокет TCP/IP. */
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
  echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
  die();
} else {
  echo "OK.\n";
}

echo "Пытаемся соединиться с '$address' на порту '$port'...";
$result = socket_connect($socket, $address, $port);
if ($result === false) {
  echo "Не удалось выполнить socket_connect().\nПричина: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
  die();
} else {
  echo "OK.\n";
  $out = socket_read($socket, 2048);
  echo $out . PHP_EOL;
}

$out = '';

do {
  $in = (fgets(STDIN));
  echo "Отправляем запрос...";
  socket_write($socket, $in, strlen($in));
  echo "OK.\n";

  echo "Читаем ответ:\n";
  $out = socket_read($socket, 2048);
  echo $out . PHP_EOL;
} while ($out);

echo "Закрываем сокет...";
socket_close($socket);
echo "OK.\n\n";
?>
