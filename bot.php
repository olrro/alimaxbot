<?php

use TuriBot\Client;

require_once __DIR__ . "/vendor/autoload.php";

if ( !isset( $_GET["api"] ) ) {
    exit();
}

$storage = @file_get_contents( __DIR__ . '/storage.db' );

if ( !$storage = @json_decode( $storage, 1 ) ){

  $storage = [];
  $storage['section'] = 'start';

}

$client = new Client( $_GET["api"] );
$update = $client->getUpdate();

if ( isset( $update->message ) ) {

    $chat_id = $client->easy->chat_id;
    $message_id = $client->easy->message_id;
    $text = $client->easy->text;

    if ( $text == '/create' ) {

      $storage['section'] = 'create';

      $client->sendMessage(
        $chat_id,
        'Чтобы создать новый пост введите идентификатор товара на Aliexpress (https://aliexpress.ru/) и текст описания (например, 32914249002 Новое классное зарядное устройство)'
      );

    }

    if ( $text == '/post' AND $storage['section'] == 'create' ) {
      // code...
    }

    if ( $text == '/stop' ) {
      $storage = [];
    }

    if ( $storage['section'] == 'create' ) {

      preg_match( '/^([0-9]+) ([A-Za-zА-Яа-яёЁ0-9\s]+)$/iU', $text, $description );

      if ( isset( $description['1'] ) AND isset( $description['2'] ) ) {

        $item = [];

        $item['id'] = $description['1'];
        $item['description'] = $description['2'];

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
              [ "text" => "Купить 🧨", "url" => "http://www.google.com/", ],
            ],
        ];

          $text = [];

          $text[] = "[​​​​​​​​​​​]({$item['image']}) {$item['description']}" . PHP_EOL;
          $text[] = "Цена - {$item['price']}";
          $text[] = "Рейтинг - {$item['rating']} оценка / {$item['orders']} заказа(ов)";
          $text[] = "Отзывов - {$item['reviews']}";

          $client->sendMessage(
            $chat_id, implode( PHP_EOL, $text ),
            null, null, null, null, null, null,
            $menu
          );

          $client->sendMessage(
            $chat_id,
            'Так будет выглядеть пост, который будет отправлен на канал, чтобы продолжить введите команду /post, для отмены - /stop'
          );

      }
      else {

        $client->sendMessage(
          $chat_id,
          'Данные для поста отправлены в неправильной форме'
        );

        $client->sendMessage(
          $chat_id,
          'Чтобы создать новый пост введите идентификатор товара на Aliexpress (https://aliexpress.ru/) и текст описания (например, 32914249002 Новое классное зарядное устройство)'
        );

      }

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

$storage = json_encode( $storage );
@file_put_contents( __DIR__ . '/storage.db', $storage );
