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
$update = json_decode( json_encode( $client->getUpdate() ), 1 );

if ( isset( $update['message'] ) ) {

    $chat_id = $client->easy->chat_id;
    $message_id = $client->easy->message_id;
    $text = $client->easy->text;

    switch ( 1 ) {

      case ( $storage['section'] == 'create' AND !isset( $storage['ready'] ) ):

        if ( preg_match( '/^([0-9]{5,20}) ([a-z0-9\/\-.:]{3,255}) (.{1,500})$/s', $text, $description ) ) {

          $item = [];

          $item['id'] = $description['1'];
          $item['url'] = $description['2'];
          $item['description'] = $description['3'];

          $item['html'] = @file_get_contents( 'https://aliexpress.ru/item/' . $item['id'] . '.html' );


          $conditions = [
            '<meta property="og:image" content="(.*)"\/>' => 'image',
            '"totalValidNum":(.*),' => 'reviews',
            '"formatTradeCount":"(.*)",' => 'orders',
            '"averageStar":"(.*)",' => 'rating',
            '"actSkuMultiCurrencyDisplayPrice":"(.*)",' => 'price',
            '"discount":(.*),' => 'discount',
          ];


          foreach ( $conditions as $regex => $name ) {

            preg_match( "/{$regex}/iU", $item['html'], $match );
            if ( !empty( $match[1] ) ) $item[$name] = ( $name == 'price' ) ? intval( $match[1] ) : $match[1];

          }

          $text = [];

          $text[] = "[​​​​​​​​​​​]({$item['image']}){$item['description']}" . PHP_EOL;
          $text[] = "Цена - [{$item['price']} ₽]({$item['url']})";

          if ( isset( $item['discount'] ) )
          $text[] = "Скидка - имеется ([-{$item['discount']}%]({$item['url']}))";

          $text[] = "Рейтинг - [{$item['rating']}]({$item['url']}) оценка / [{$item['orders']}]({$item['url']}) заказы";
          $text[] = "Отзывов - [{$item['reviews']}]({$item['url']})";

          $storage['ready']['text'] = implode( PHP_EOL, $text );
          $storage['ready']['buttons'] = [
            'inline_keyboard' =>
            [
              [ [ "text" => "👍", "callback_data" => "finger" ], [ "text" => "😜", "callback_data" => "emoji" ] ],
              [ [ "text" => "Купить 🧨", "url" => $item['url'], ] ]
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
            'Чтобы создать новый пост введите идентификатор товара на Aliexpress, партнерскую ссылку и текст описания (например, 32914249002 http://alii.pub/5kqwi5 Новое классное зарядное устройство)'
          );

        }

      break;

      case ( $text === '/create' ):

      $client->sendMessage(
        $chat_id,
        'Чтобы создать новый пост введите идентификатор товара на Aliexpress, партнерскую ссылку и текст описания (например, 32914249002 http://alii.pub/5kqwi5 Новое классное зарядное устройство)'
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

          unset( $storage['section'] );
          unset( $storage['ready'] );

        }

      break;

      case ( $text === '/stop' ):

        $client->sendMessage(
          $chat_id,
          'Публикация поста была отменена, чтобы создать новый пост введите команду /create'
        );

        unset( $storage['section'] );
        unset( $storage['ready'] );

      break;

      case ( $text === '/start' ):

        $client->sendMessage(
          $chat_id,
          'Привет, я бот который может публиковать пост на канале! Если ты администратор, то можешь вопспользоваться мной'
        );

      break;

      default:

        $client->sendMessage(
          $chat_id,
          'Такой команды не найдено, введите команду /help, чтобы увидеть список всех команд'
        );

      break;

    }

}

if ( isset( $update['callback_query'] ) ) {

    $update = $update['callback_query'];
    $message = $update['message'];

    $id = $update['id'];
    $chat_id = $message['chat']['id'];
    $message_id = $message['message_id'];
    $buttons = $message['reply_markup']['inline_keyboard'];

    if ( $update['data'] == "finger" ) {

      $buttons[0][0]['text'] = '👍 ' . ( intval( ltrim( $buttons[0][0]['text'], '👍' ) ) + 1 );

    } elseif ( $update['data'] == "emoji" ) {

      $buttons[0][1]['text'] = '😜 ' . ( intval( ltrim( $buttons[0][1]['text'], '😜' ) ) + 1 );

    }

    $client->answerCallbackQuery( $id, "Рейтинг был успешно изменен!" );

    $client->editMessageText(
      $chat_id, $message_id, null, $message['text'],
      null, $update['message']['entities'], null,
      [ 'inline_keyboard' => $buttons ]
    );

}

if ( $storage = @json_encode( $storage ) ) {
  $redis->set( 'storage', $storage );
}
