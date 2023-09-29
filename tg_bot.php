<?php
define('TOKEN', '6563537355:AAGzfWevcW3iMReeB9FuZEuy5BMrwvGJK1A');

$data = json_decode(file_get_contents('php://input'), TRUE);
$data = $data['callback_query'] ? $data['callback_query'] : $data['message'];
$message = mb_strtolower(($data['text'] ? $data['text'] : $data['data']), 'utf-8');

$flightData = [];
$delayFlightData = [];
$flightCount = 0;
$delayFlightCount = 0;

function getSelectedDay()
{
     return file_get_contents('selected_day.txt');
}
function saveSelectedDay($day)
{
     file_put_contents('selected_day.txt', $day);
}

$selectedDay = getSelectedDay();

if ($message == 'сегодня' || $message == 'завтра' || $message == 'вчера') {
     saveSelectedDay($message);
}

if ($selectedDay == 'сегодня') {
     $url = 'https://www.airport-surgut.ru/surgut/table/in/';
} elseif ($selectedDay == 'завтра') {
     $url = 'https://www.airport-surgut.ru/surgut/table/in/?day=tomorrow';
} elseif ($selectedDay == 'вчера') {
     $url = 'https://www.airport-surgut.ru/surgut/table/in/?day=yesterday';
} else {
     $url = 'https://www.airport-surgut.ru/surgut/table/in/';
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
          $stickerFileId = 'https://cataas.com/c?cache=' . $cacheBuster;
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
