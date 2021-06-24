#!/usr/local/bin/php -q
<?php

error_reporting(E_ALL);

/* Позволяет скрипту ожидать соединения бесконечно. */
set_time_limit(0);

/* Включает скрытое очищение вывода так, что мы видим данные
 * как только они появляются. */
ob_implicit_flush();

require_once 'vendor/autoload.php';

$address = '127.0.0.1';

echo "Hello, please type port: ";

$port = filter_var(trim(fgets(STDIN)), FILTER_VALIDATE_INT);
if ($port === false) {
  echo "Номер порта должен быть целым числом \n";
  die();
};

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
  echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
  die();
}

if (socket_bind($sock, $address, $port) === false) {
  echo "Не удалось выполнить socket_bind(): причина: " . socket_strerror(socket_last_error($sock)) . "\n";
  die();
}

if (socket_listen($sock, 5) === false) {
  echo "Не удалось выполнить socket_listen(): причина: " . socket_strerror(socket_last_error($sock)) . "\n";
  die();
}

$clients = array();
do {
  $read = array();
  $read[] = $sock;

  $read = array_merge($read, $clients);

  $write = NULL;
  $except = NULL;
  $tv_sec = 5;

  if(socket_select($read,$write, $except, $tv_sec) < 1)
  {
    echo "Нет новых состояний, подожем 1 сек \n";
    sleep(1);
    continue;
  }

  //регистрируем новый коннект
  if (in_array($sock, $read)) {
    if (($msgsock = socket_accept($sock)) === false) {
      echo "Не удалось выполнить socket_accept(): причина: " . socket_strerror(socket_last_error($sock)) . "\n";
      break;
    }
    $clients[] = $msgsock;
    $key = array_keys($clients, $msgsock);

    /* Отправляем инструкции. */
    $msg = "\nДобро пожаловать на тестовый сервер PHP. Клиент № {$key[0]}\n" .
      "Чтобы отключиться, наберите 'exit'. Чтобы выключить сервер, наберите 'turnoff'.\n";
    socket_write($msgsock, $msg, strlen($msg));
  }

  foreach ($clients as $key => $client) { // for each client
    if (in_array($client, $read)) {
      if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
        echo "Не удалось выполнить socket_read(): причина: " . socket_strerror(socket_last_error($client)) . "\n";
        break 2;
      }
      if (trim($buf) === 'exit') {
        unset($clients[$key]);
        socket_close($client);
        break;
      }
      if (trim($buf) === 'turnoff') {
        socket_close($client);
        break 2;
      }
      try {
        $talkback = (OTUS_backend_lesson_2::analyzeStr($buf) ? 'correct' : 'incorrect') . PHP_EOL;
      } catch (InvalidArgumentException $e) {
        $talkback = $e->getMessage();
      }
      socket_write($client, $talkback, strlen($talkback));
      echo $buf;
    }
  }

} while (true);

socket_close($sock);