<?php

include('config.php');
define('TOKEN', '6563537355:AAGzfWevcW3iMReeB9FuZEuy5BMrwvGJK1A');

$data = json_decode(file_get_contents('php://input'), TRUE);
$data = $data['callback_query'] ? $data['callback_query'] : $data['message'];
$message = mb_strtolower(($data['text'] ? $data['text'] : $data['data']), 'utf-8');
$user_id = $data['chat']['id'];

try {
     $conn = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", $username, $password, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_EMULATE_PREPARES => false,
     ]);
     echo "Connection successfulled";
} catch (PDOException $e) {
     echo "Connection failed: " . $e->getMessage();
     exit;
}

$flightData = [];
$delayFlightData = [];
$flightCount = 0;
$delayFlightCount = 0;
function getSelectedDay($user_id)
{
     global $conn;
     try {
          $query = "SELECT selday FROM selected_day WHERE user_id = :user_id";
          $stmt = $conn->prepare($query);
          $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
          $stmt->execute();

          $result = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($result) {
               return $result['selday'];
          }

          return false;
     } catch (PDOException $e) {
          echo "Error: " . $e->getMessage();
          return false;
     }
}

function saveSelectedDay($user_id, $selday)
{
     global $conn;
     try {
          $existingDay = getSelectedDay($user_id);

          if ($existingDay !== false) {
               $query = "UPDATE selected_day SET selday = :selday WHERE user_id = :user_id";
          } else {
               $query = "INSERT INTO selected_day (user_id, selday) VALUES (:user_id, :selday)";
          }

          $stmt = $conn->prepare($query);
          $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
          $stmt->bindParam(':selday', $selday, PDO::PARAM_STR);

          if (!$stmt->execute()) {
               throw new PDOException("Не удалось выполнить операцию.");
          }
     } catch (PDOException $e) {
          echo "Ошибка: " . $e->getMessage();
     }
}
$selectedDay = getSelectedDay($user_id);


if ($message == 'сегодня' || $message == 'завтра' || $message == 'вчера') {
     saveSelectedDay($user_id, $message);
}

if ($selectedDay == 'сегодня') {
     $url = 'https://www.airport-surgut.ru/surgut/table/';
} elseif ($selectedDay == 'завтра') {
     $url = 'https://www.airport-surgut.ru/surgut/table/?day=tomorrow';
} elseif ($selectedDay == 'вчера') {
     $url = 'https://www.airport-surgut.ru/surgut/table/?day=yesterday';
} else {
     $url = 'https://www.airport-surgut.ru/surgut/table/';
}
$html = file_get_contents($url);
if ($html !== false) {
     $html = html_entity_decode($html);
     $dom = new DOMDocument();
     $dom->loadHTML($html);
     $xpath = new DOMXPath($dom);
     $blocks = $xpath->query('//div[@class="flight_board__flight flight"]');
     foreach ($blocks as $block) {
          $time = $xpath->query('.//div[@class="flight__time"]', $block)->item(0)->textContent;
          $city = $xpath->query('.//div[@class="flight__city"]', $block)->item(0)->textContent;
          $flightNumParent = $xpath->query('.//div[@class="flight__num"]', $block)->item(0);
          if ($flightNumParent) {
               $flightNumber = $xpath->query('.//div[@class="flight__value"][1]', $flightNumParent)->item(0)->textContent;
          }
          $flightCompanyParent = $xpath->query('.//div[@class="flight__company"]', $block)->item(0);
          if ($flightCompanyParent) {
               $airline = $xpath->query('.//div[@class="flight__value"][1]', $flightCompanyParent)->item(0)->textContent;
          }
		  $flightDeskParent = $xpath->query('.//div[@class="flight__desk"]', $block)->item(0);
          if ($flightDeskParent) {
               $desk = $xpath->query('.//div[@class="flight__value"][1]', $flightDeskParent)->item(0)->textContent;
          }
          $flightStatusParent = $xpath->query('.//div[@class="flight__status"]', $block)->item(0);
          if ($flightStatusParent) {
               $status = $xpath->query('.//div[@class="flight__value"][1]', $flightStatusParent)->item(0)->textContent;
          }
          $flightFactTimeParent = $xpath->query('.//div[@class="flight__fact_time"]', $block)->item(0);
          if ($flightFactTimeParent) {
               $factTime = $xpath->query('.//div[@class="flight__value"][1]', $flightFactTimeParent)->item(0)->textContent;
          }

          $flightInfo = "*Время: $time
          *Город: $city
          *Рейс: $flightNumber
          *Авиакомпания: $airline
		  *Стойка: $desk
          *Статус: $status";
          $factTime = trim($factTime);
          if (!empty($factTime)) {
               $flightInfo .= "*Фактическое время: $factTime";
          }

          $flightCount++;

          $flightInfo = preg_replace('/\s+/', " ", $flightInfo);
          $flightInfo = str_replace("*", "\n", $flightInfo);

          $flightData[] = $flightInfo;

          $delayPattern = '/Задержка/';
          $delayPattern2 = '/ЗАДЕРЖАН/';

          if (preg_match($delayPattern, $status) || preg_match($delayPattern2, $status)) {
               $delayFlightInfo = "*Время: $time\n
               *Город: $city
               *Рейс: $flightNumber
               *Авиакомпания: $airline
			   *Стойка: $desk
               *Статус: $status";
               if (!empty($factTime)) {
                    $flightInfo .= "*Фактическое время: $factTime";
               }

               $delayFlightCount++;

               $delayFlightInfo = preg_replace('/\s+/', ' ', $delayFlightInfo);
               $delayFlightInfo = str_replace("*", "\n", $delayFlightInfo);

               $delayFlightData[] = $delayFlightInfo;
               echo $delayFlightInfo;
          }

     }
} else {
     $method = 'sendMessage';
     $send_data = [
          'text' => 'Не удалось загрузить данные с сайта, повторите позже!'
     ];
}
$cacheBuster = time();
switch ($message) {
     case 'задержанные рейсы':
          $method = 'sendMessage';
          $send_data = [
               'text' => "*Задержанные рейсы (на $selectedDay)*\n" . implode("\n", $delayFlightData) . "\n\n*Общее количество задержанных рейсов:* $delayFlightCount",
               'parse_mode' => 'markdown',
          ];
          break;
     case 'все рейсы':
          $method = 'sendMessage';
          $send_data = [
               'text' => "*Все рейсы (на $selectedDay)*\n" . implode("\n", $flightData) . "\n\n*Общее количество рейсов:* $flightCount",
               'parse_mode' => 'markdown',
          ];
          break;
     case 'пасхалка':
          $method = 'sendPhoto';
          $stickerFileId = 'https://cataas.com/cat?cache=' . $cacheBuster;
          $send_data = [
               'photo' => $stickerFileId,
          ];
          break;
     case 'выбрать день':
          $method = 'sendMessage';
          $send_data = [
               'text' => "Выберите день, за который хотите посмотреть рейсы",
               'reply_markup' => [
                    'resize_keyboard' => true,
                    'keyboard' => [
                         [
                              ['text' => 'Вчера'],
                              ['text' => 'Сегодня'],

                         ],
                         [
                              ['text' => 'Завтра'],
                              ['text' => 'Главное меню'],
                         ]
                    ]
               ]
          ];
          break;
     case 'вчера':
          $method = 'sendMessage';
          $send_data = [
               'text' => "Вы выбрали рейсы за вчерашний день",
               'reply_markup' => [
                    'resize_keyboard' => true,
                    'keyboard' => [
                         [
                              ['text' => 'Все рейсы'],
                              ['text' => 'Задержанные рейсы'],

                         ],
                         [
                              ['text' => 'Пасхалка'],
                              ['text' => 'Выбрать день'],
                         ]
                    ]
               ]
          ];
          break;
     case 'сегодня':
          $method = 'sendMessage';
          $send_data = [
               'text' => "Вы выбрали рейсы за сегодняшний день",
               'reply_markup' => [
                    'resize_keyboard' => true,
                    'keyboard' => [
                         [
                              ['text' => 'Все рейсы'],
                              ['text' => 'Задержанные рейсы'],

                         ],
                         [
                              ['text' => 'Пасхалка'],
                              ['text' => 'Выбрать день'],
                         ]
                    ]
               ]
          ];
          break;
     case 'завтра':
          $method = 'sendMessage';
          $send_data = [
               'text' => "Вы выбрали рейсы на завтравшний день",
               'reply_markup' => [
                    'resize_keyboard' => true,
                    'keyboard' => [
                         [
                              ['text' => 'Все рейсы'],
                              ['text' => 'Задержанные рейсы'],

                         ],
                         [
                              ['text' => 'Пасхалка'],
                              ['text' => 'Выбрать день'],
                         ]
                    ]
               ]
          ];
          break;
     default:
          $method = 'sendMessage';
          $send_data = [
               'text' => "Нажмите, пожалуйста, на нужную кнопку!",
               'reply_markup' => [
                    'resize_keyboard' => true,
                    'keyboard' => [
                         [
                              ['text' => 'Все рейсы'],
                              ['text' => 'Задержанные рейсы'],

                         ],
                         [
                              ['text' => 'Пасхалка'],
                              ['text' => 'Выбрать день'],
                         ]
                    ]
               ]
          ];
          break;
}

$send_data['chat_id'] = $data['chat']['id'];
$res = sendTelegram($method, $send_data);

function sendTelegram($method, $data, $headers = [])
{
     $curl = curl_init();
     curl_setopt_array($curl, [
          CURLOPT_POST => 1,
          CURLOPT_HEADER => 0,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL => 'https://api.telegram.org/bot' . TOKEN . '/' . $method,
          CURLOPT_POSTFIELDS => json_encode($data),
          CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"])
     ]);
     $result = curl_exec($curl);
     curl_close($curl);
     return (json_decode($result, 1) ? json_decode($result, 1) : $result);
}
