<?php

use TuriBot\Client;

require_once __DIR__ . "/vendor/autoload.php";

if ( !isset( $_GET["api"] ) ) {
  exit();
}

Predis\Autoloader::register();

$redis = new Predis\Client( $_ENV['REDIS_URL'] );

if ( !$storage = @json_decode( $redis->get( 'storage' ), 1 ) ){
  $storage = [ 'section' => '' ];
}

$client = new Client( $_GET["api"] );
$update = $client->getUpdate();

if ( isset( $update->message ) ) {

    $chat_id = $client->easy->chat_id;
    $message_id = $client->easy->message_id;
    $text = $client->easy->text;

    $client->debug( $storage );

    switch ( true ) {

      case ( $storage['section'] === 'create' AND !isset( $storage['ready'] ) ):

        if ( preg_match( '/^([0-9]+) (.*){1,500}$/iU', $text, $description ) ) {

          $item = [];

          $item['id'] = $description['1'];
          $item['description'] = $description['2'];

          $item['url'] = 'https://aliexpress.ru/item/' . $item['id'] . '.html';
          $item['html'] = @file_get_contents( $item['url'] );


          $conditions = [
            '<meta property="og:image" content="(.*)"\/>' => 'image',
            '"totalValidNum":(.*),' => 'reviews',
            '"formatTradeCount":"(.*)",' => 'orders',
            '"averageStar":"(.*)",' => 'rating',
            '"formatedAmount":"(.*)",' => 'price'
          ];


          foreach ( $conditions as $regex => $name ) {

            preg_match( "/{$regex}/iU", $item['html'], $match );
            if ( !empty( $match[1] ) ) $item[$name] = $match[1];

          }

          $text = [];

          $text[] = "[​​​​​​​​​​​]({$item['image']}){$item['description']}" . PHP_EOL;
          $text[] = "Цена - [{$item['price']}]({$item['url']})";

          $text[] = "Рейтинг - [{$item['rating']}]({$item['url']}) оценка / [{$item['orders']}]({$item['url']}) заказа(ов)";
          $text[] = "Отзывов - [{$item['reviews']}]({$item['url']})";

          $storage['ready']['text'] = implode( PHP_EOL, $text );
          $storage['ready']['buttons'] = [
            'inline_keyboard' =>
            [
              [ [ "text" => "👍", "callback_data" => "like" ], [ "text" => "👎", "callback_data" => "dislike" ] ],
              [ [ "text" => "Купить 🧨", "url" => "http://www.google.com/", ] ]
            ]
          ];

          $client->sendMessage(
            $chat_id, $storage['ready']['text'], 'markdown',
            null, null, null, null, null,
            $storage['ready']['buttons']
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

      break;

      case ( $text === '/create' ):

        $client->sendMessage(
          $chat_id,
          'Чтобы создать новый пост введите идентификатор товара на Aliexpress (https://aliexpress.ru/) и текст описания (например, 32914249002 Новое классное зарядное устройство)'
        );

        $storage['section'] = 'create';

      break;

      case ( $text === '/post' ):

        if ( empty( $storage['ready'] ) ) {

          $client->sendMessage(
            $chat_id,
            'Внимание, ваш пост еще не готов. Введите команду /create, чтобы создать новую запись'
          );

        }
        else {

          $client->sendMessage( $chat_id, 'Ваш пост был успешно опубликован!' );

          $client->sendMessage(
            '-1001432760770', $storage['ready']['text'], 'markdown',
            null, null, null, null, null,
            $storage['ready']['buttons']
          );

          $storage['posts'][] = [
            'text' => $storage['ready']['text'],
            'buttons' => $storage['ready']['buttons']
          ];

          unset( $storage['ready'] );

        }

      break;

      case ( $text === '/stop' ):

        $client->sendMessage(
          $chat_id,
          'Публикация поста была отменена, чтобы создать новый пост введите команду /create'
        );

        unset( $storage['ready'] );

      break;

      default:

        $client->sendMessage(
          $chat_id,
          'Такой команды не найдено, введите команду /help, чтобы увидеть список всех команд'
        );

      break;

    }

}

if ( isset( $update->callback_query ) ) {

    $id = $update->callback_query->id;
    $chat_id = $update->callback_query->message->chat->id;
    $message_id = $update->callback_query->message->message_id;

    $likes = intval( str_replace( '👍', '', $update->callback_query->message->reply_markup->inline_keyboard[0][0]['text'] ) );
    $dislikes = intval( str_replace( '👎', '', $update->callback_query->message->reply_markup->inline_keyboard[0][1]['text'] ) );

    if ( $update->callback_query->data == "like" ) {

      $update->callback_query->message->reply_markup->inline_keyboard[0][0]['text'] = '👍' . $likes + 1;

    } elseif ( $update->callback_query->data == "dislike" ) {

      $update->callback_query->message->reply_markup->inline_keyboard[0][1]['text'] = '👎' . $likes + 1;

    }

    $client->answerCallbackQuery( $id, "Рейтинг был успешно изменен!" );

    $client->editMessageText(
      $chat_id, $message_id, null, $update->callback_query->message->text,
      null, $update->callback_query->message->entities, null,
      $update->callback_query->message->reply_markup
    );

}

if ( $storage = @json_encode( $storage ) ) {
  $redis->set( 'storage', $storage );
}
