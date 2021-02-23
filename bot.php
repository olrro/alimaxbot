<?php

use TuriBot\Client;

require_once __DIR__ . "/vendor/autoload.php";

if ( !isset( $_GET["api"] ) ) {
  exit();
}

Predis\Autoloader::register();

$redis = new Predis\Client( $_ENV['REDIS_URL'] );
$storage = @json_decode( $redis->get( 'database' ), 1 );

if ( !$storage ){

  $storage = [];
  $storage['section'] = 'start';

}

$client = new Client( $_GET["api"] );
$update = $client->getUpdate();

if ( isset( $update->message ) ) {

    $chat_id = $client->easy->chat_id;
    $message_id = $client->easy->message_id;
    $text = $client->easy->text;

    if ( $storage['section'] == 'create' ) {

      preg_match( '/^([0-9]+) (.*){1,500}$/iU', $text, $description );

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
          $text[] = "Цена - [{$item['price']}]({$item['url']})";
          $text[] = "Рейтинг - [{$item['rating']}]({$item['url']}) оценка / [{$item['orders']}]({$item['url']}) заказа(ов)";
          $text[] = "Отзывов - [{$item['reviews']}]({$item['url']})";

          $storage['message'] = implode( PHP_EOL, $text );
          $storage['menu'] = $menu;

          $client->sendMessage(
            $chat_id, implode( PHP_EOL, $text ), 'markdown',
            null, null, null, null, null,
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

    if ( $text == '/create' ) {

      $client->sendMessage(
        $chat_id,
        'Чтобы создать новый пост введите идентификатор товара на Aliexpress (https://aliexpress.ru/) и текст описания (например, 32914249002 Новое классное зарядное устройство)'
      );

      $storage['section'] = 'create';

    }

    if ( $text == '/post' ) {

      if ( empty( $storage['message'] ) ) {

        $client->sendMessage(
          $chat_id,
          'Внимание, ваш пост еще не готов. Введите команду /create, чтобы создать новую запись'
        );

      }
      else {

        $client->sendMessage( $chat_id, 'Ваш пост был успешно опубликован!' );

        $client->sendMessage(
          '-1001432760770', $storage['message'], 'markdown',
          null, null, null, null, null,
          $storage['menu']
        );

        $storage = [];

      }

    }

    if ( $text == '/stop' ) {

      $client->sendMessage(
        $chat_id,
        'Публикация поста была отменена, чтобы создать новый пост введите команду /create'
      );

      $storage = [];

    }

}

if ( isset( $update->callback_query ) ) {

    $id = $update->callback_query->id;
    $chat_id = $update->callback_query->message->chat->id;
    $message_id = $update->callback_query->message->message_id;

    if ( $update->callback_query->data == "like" ) {

      if ( isset( $storage['likes'][$message_id] ) ) {
        $storage['likes'][$message_id] = $storage['likes'][$message_id] + 1;
      }
      else {
        $storage['likes'][$message_id] = 1;
      }

    } elseif ( $update->callback_query->data == "dislike" ) {


      if ( isset( $storage['dislikes'][$message_id] ) ) {
        $storage['dislikes'][$message_id] = $storage['dislikes'][$message_id] + 1;
      }
      else {
        $storage['dislikes'][$message_id] = 1;
      }

    }

    $menu["inline_keyboard"] = [
        [
          [ "text" => "👍 {$storage['likes'][$message_id]}", "callback_data" => "like" ],
          [ "text" => "👎 {$storage['dislikes'][$message_id]}", "callback_data" => "dislike" ],
        ],
        [
          [ "text" => "Купить 🧨", "url" => "http://www.google.com/", ],
        ],
    ];

    $client->answerCallbackQuery( $id, "Рейтинг был успешно изменен!" );
    $client->editMessageText(
      $chat_id, $message_id, null, "Button 2",
      null, null, null,
      $menu
    );

}

$storage = json_encode( $storage );
$redis->set( 'database', $storage );
