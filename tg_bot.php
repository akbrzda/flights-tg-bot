<?php
$url = 'https://www.airport-surgut.ru/surgut/table/in/';
$html = file_get_contents($url);
$data = json_decode(file_get_contents('php://input'), TRUE);
$data = $data['callback_query'] ? $data['callback_query'] : $data['message'];
define('TOKEN', '6563537355:AAGzfWevcW3iMReeB9FuZEuy5BMrwvGJK1A');
$message = mb_strtolower(($data['text'] ? $data['text'] : $data['data']), 'utf-8');
if ($html !== false) {
     $html = html_entity_decode($html);
     $dom = new DOMDocument();
     $dom->loadHTML($html);
     $xpath = new DOMXPath($dom);
     $blocks = $xpath->query('//div[@class="flight_board__flight flight"]');
     $flightData = [];
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
          $flightInfo = "*Время:* $time\n
          *Город:* $city\n
          *Рейс:* $flightNumber\n
          *Авиакомпания:* $airline\n
          *Статус:* $status\n
          *Фактическое время:* $factTime";
          $flightInfo = preg_replace('/\s+/', ' ', $flightInfo);
          $flightData[] = $flightInfo;
          switch ($message) {
               case 'рейсы':
                    $method = 'sendMessage';
                    $send_data = [
                         'text' => implode("\n\n", $flightData),
                         'parse_mode' => 'markdown',
                         'reply_markup' => [
                              'resize_keyboard' => true,
                              'keyboard' => [
                                   [
                                        ['text' => 'Рейсы'],
                                        ['text' => 'О боте'],
                                   ]
                              ]
                         ]
                    ];
                    break;
               case 'о боте':
                    $method = 'sendMessage';
                    $send_data = [
                         'text' => 'Этот бот отправляет расписание рейсов аэропрта Сургута за 24 часа',
                         'parse_mode' => 'HTML'


                    ];
                    break;
               default:
                    $method = 'sendMessage';
                    $send_data = [
                         'text' => implode("\n\n", $flightData),
                         'reply_markup' => [
                              'resize_keyboard' => true,
                              'keyboard' => [
                                   [
                                        ['text' => 'Рейсы'],
                                        ['text' => 'О боте'],
                                   ]
                              ]
                         ]
                    ];
                    break;
          }

     }
} else {
     $method = 'sendMessage';
     $send_data = [
          'text' => 'Не удалось загрузить данные с сайта, повторите позже!'
     ];
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