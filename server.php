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

$options = getopt('p:', ['path:']);
if (empty($options)) {
  die('Укажите обязательные параметры');
}

pcntl_async_signals(true);
function sig_hup($signo)
{
  echo 'We caught signal' . PHP_EOL;
  global $new_connect;
  $new_connect = true;
  echo 'sig_hup is finnished' . PHP_EOL;
}
pcntl_signal(SIGHUP, 'sig_hup');

$path = ($options['path']) ?? ($options['p']);

do {
  $configs = \Symfony\Component\Yaml\Yaml::parseFile(trim($path));
  $port    = filter_var($configs['port'], FILTER_VALIDATE_INT);
  if ($port === false) {
    echo "Номер порта должен быть целым числом \n";
    die();
  };
  echo 'Создаем сокет порт: ' . $port . PHP_EOL;
  if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
    die();
  }
  // без этой опции при переподключении будет ошибка, что порт занят
  // причина: socket_close не закрывает соединение моментально, так как может быть не все данные переданны
  // можно использовать так же опцию SO_LINGER
  if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEPORT, 1)) {
    echo 'Не могу установить опцию на сокете: '. socket_strerror(socket_last_error()) . PHP_EOL;
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
  $new_connect = false;
  do {
    $read   = array();
    $read[] = $sock;

    $read = array_merge($read, $clients);

    $write  = NULL;
    $except = NULL;
    $tv_sec = 5;

    if ($new_connect) {
      foreach ($clients as $client) {
        socket_close($client);
      }
      socket_close($sock);
      unset($sock, $configs, $clients);
      break;
    }
    if (socket_select($read, $write, $except, $tv_sec) < 1) {
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
      $key       = array_keys($clients, $msgsock);

      /* Отправляем инструкции. */
      $msg = "\nДобро пожаловать на тестовый сервер PHP. Клиент № {$key[0]}\n" .
        "Чтобы отключиться, наберите 'exit'. Чтобы выключить сервер, наберите 'turnoff'.\n";
      socket_write($msgsock, $msg, strlen($msg));
    }

    foreach ($clients as $key => $client) { // for each client
      if (in_array($client, $read)) {
        if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
          echo "Не удалось выполнить socket_read(): причина: " . socket_strerror(socket_last_error($client)) . "\n";
          break 3;
        }
        if (trim($buf) === 'exit') {
          unset($clients[$key]);
          socket_close($client);
          break;
        }
        if (trim($buf) === 'turnoff') {
          socket_close($client);
          socket_close($sock);
          break 3;
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
} while (true);

echo 'Server is stopped!';