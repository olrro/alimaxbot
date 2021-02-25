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

        if ( filter_var( $text, FILTER_VALIDATE_URL ) ) {

          $storage['ready']['url'] = $text;

          $client->sendMessage(
            $chat_id,
            'Ссылка успешно установлена'
          );

          $client->sendMessage(
            $chat_id,
            'Чтобы продолжить - введите идентификатор товара на Aliexpress и текст описания (например, 32914249002 Новое классное зарядное устройство)'
          );

        }
        else {

          $client->sendMessage(
            $chat_id,
            'Введите партнерскую ссылку на товар (с которой будет начислен процент)'
          );

        }

      break;

      case ( isset( $storage['ready'] ) AND $text != '/post' AND $text != '/stop' ):

        if ( preg_match( '/^([0-9]{5,20}) (.{1,500})$/sU', $text, $description ) ) {

          $item = [];

          $item['id'] = $description['1'];
          $item['description'] = $description['2'];

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
          $text[] = "Цена - [{$item['price']} ₽]({$storage['ready']['url']})";

          if ( isset( $item['discount'] ) )
          $text[] = "Скидка - имеется ([-{$item['discount']}%]({$storage['ready']['url']}))";

          $text[] = "Рейтинг - [{$item['rating']}]({$storage['ready']['url']}) оценка / [{$item['orders']}]({$storage['ready']['url']}) заказы";
          $text[] = "Отзывов - [{$item['reviews']}]({$storage['ready']['url']})";

          $storage['ready']['text'] = implode( PHP_EOL, $text );

          $client->sendMessage( $chat_id, $storage['ready']['text'], 'markdown' );
          $client->sendMessage( $chat_id, 'Так будет выглядеть пост, который будет отправлен на канал' );
          $client->sendMessage( $chat_id, 'Чтобы отправить пост введите команду /post, для отмены - /stop' );

        }
        else {

          $client->sendMessage(
            $chat_id,
            'Введите идентификатор товара на Aliexpress и текст описания (например, 32914249002 Новое классное зарядное устройство)'
          );

        }

      break;

      case ( $text === '/create' ):

        $client->sendMessage(
          $chat_id,
          'Введите партнерскую ссылку на товар (с которой будет начислен процент)'
        );

        $storage['section'] = 'create';

      break;

      case ( $text === '/post' ):

        if ( isset( $storage['ready']['text'] ) ) {

          $client->sendMessage( $chat_id, 'Ваш пост был успешно опубликован!' );

          $client->sendMessage(
            '-1001432760770', $storage['ready']['text'], 'markdown',
            null, null, null, null, null,
            [
              'inline_keyboard' =>
              [
                [ [ "text" => "👍", "callback_data" => "finger" ], [ "text" => "😜", "callback_data" => "emoji" ] ],
                [ [ "text" => "Купить 🧨", "url" => $storage['ready']['url'], ] ]
              ]
            ]
          );

          unset( $storage['section'] );
          unset( $storage['ready'] );

        }
        else {

          $client->sendMessage(
            $chat_id,
            'Вы еще не закончили создание поста. Пожалуйста, выполните все шаги'
          );

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
