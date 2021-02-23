<?php

require_once __DIR__ . "/vendor/autoload.php";

use TuriBot\Client;


if (!isset($_GET["api"])) {
    exit();
}

$client = new Client($_GET["api"], false);
$update = $client->getUpdate();
if (!isset($update)) {
    exit('json error');
}

if (isset($update->message) or isset($update->edited_message)) {

    $chat_id = $client->easy->chat_id;
    $message_id = $client->easy->message_id;
    $text = $client->easy->text;

    if ( $text ) {

      $item = [];

      $item['url'] = 'https://aliexpress.ru/item/' . intval( $text ) . '.html';
      $item['html'] = @file_get_contents( $item['url'] );

      preg_match( '/<meta property="og:image" content="(.*)"\/>/iU', $item['html'], $match );

      if ( !empty( $match[1] ) ) {
        $item['image'] = $match[1];
      }

      preg_match( '/"totalValidNum":(.*),/iU', $item['html'], $match );

      if ( !empty( $match[1] ) ) {
        $item['reviews'] = intval( $match[1] );
      }

      preg_match( '/"formatTradeCount":"(.*)",/iU', $item['html'], $match );

      if ( !empty( $match[1] ) ) {
        $item['orders'] = intval( $match[1] );
      }

      preg_match( '/"averageStar":"(.*)",/iU', $item['html'], $match );

      if ( !empty( $match[1] ) ) {
        $item['rating'] = floatval( $match[1] );
      }

      preg_match( '/"formatedAmount":"(.*)",/iU', $item['html'], $match );

      if ( !empty( $match[1] ) ) {
        $item['price'] = $match[1];
      }

      $menu["inline_keyboard"] = [
          [
            [ "text" => "👍", "callback_data" => "like" ],
            [ "text" => "👎", "callback_data" => "dislike" ],
          ],
          [
            [ "text" => "Купить 🧨", "callback_data" => "http://www.google.com/" ],
          ],
      ];

        $text = [];

        $text[] = "[]({$item['image']}) Текст Текст Текст Текст Текст Текст Текст Текст Текст Текст Текст Текст;" . PHP_EOL;
        $text[] = "Цена - {$item['price']}";
        $text[] = "Рейтинг - {$item['rating']} оценка / {$item['orders']} заказа(ов)";
        $text[] = "Отзывов - {$item['reviews']}";

        $client->sendMessage(
          $chat_id, implode( PHP_EOL, $text ), 'markdown',
          null, 1, null, null, null,
          $menu
        );

    }

    if ($text === "/var") {
        $client->debug($chat_id, $client->easy->message_id, ["key" => "value"], "pong", 3.14);
    }

}

if ( isset( $update->callback_query ) ) {

    $id = $update->callback_query->id;
    $message_chat_id = $update->callback_query->message->chat->id;
    $message_message_id = $update->callback_query->message->message_id;

    $menu["inline_keyboard"] = [
        [
            [
                "text"          => "👍",
                "callback_data" => "like",
            ],
            [
                "text"          => "👎",
                "callback_data" => "dislike",
            ],
        ],
        [
            [
                "text"          => "Купить 🧨",
                "url" => "http://www.google.com/",
            ]
        ],
    ];

    if ($update->callback_query->data === "like") {

      $client->answerCallbackQuery($id, "👍 1");
      $client->editMessageText($message_chat_id, $message_message_id, null, "Button 1", null, null, null, $menu);

    } elseif ($update->callback_query->data === "dislike") {

      $client->answerCallbackQuery($id, "👎 1");
      $client->editMessageText($message_chat_id, $message_message_id, null, "Button 2", null, null, null, $menu);

    }
}
