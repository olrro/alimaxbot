<?php

use TuriBot\Client;

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/config.php";

Predis\Autoloader::register();
$redis = new Predis\Client( $_ENV['REDIS_URL'] );

if ( !$storage = @json_decode( $redis->get( 'storage' ), 1 ) )
$storage = [ 'section' => '' ];

$client = new Client( $config['token'] );
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

          $item['html'] = @file_get_contents( 'https://aliexpress.com/item/' . $item['id'] . '.html' );

          foreach ( $config['params'] as $regex => $name ) {

            preg_match( "/{$regex}/iU", $item['html'], $match );

            if ( empty( $match[1] ) ){
              $client->sendMessage( $chat_id, 'Товара по ссылке не существует, либо отсутствуют необходимые параметры' );
              exit();
            }

            $item[$name] = $match[1];

          }

          $text = [];

          $text[] = "[​​​​​​​​​​​]({$item['image']}){$item['description']}" . PHP_EOL;

          $text[] = "Рейтинг - [{$item['rating']}]({$storage['ready']['url']}) оценка / [{$item['orders']}]({$storage['ready']['url']}) заказы";
          $text[] = "В наличии - [{$item['quantity']} шт.]({$storage['ready']['url']})";
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

      case ( $storage['section'] == 'search' AND $text != '/stop' ):

        if ( filter_var( $text, FILTER_VALIDATE_URL ) ) {

          if ( isset( $storage['posts'][$text] ) ) {

            foreach ( $storage['posts'][$text] as $id ) {

              $answer = $client->forwardMessage(
                $chat_id,
                '-1001432760770', null,
                $id
              );

              if ( !$answer->ok ) unset( $storage['posts'][$text] );
              else $found = 1;

            }

            if ( !isset( $found ) ) {

              $client->sendMessage(
                $chat_id,
                'Поста с такой ссылкой не найдено, проверьте, что вы правильно указали адрес (например, протокол или / на конце)'
              );

            }

          }
          else {

            $client->sendMessage(
              $chat_id,
              'Поста с такой ссылкой не найдено, проверьте, что вы правильно указали адрес (например, протокол или / на конце)'
            );

          }

        }
        else {

          $client->sendMessage(
            $chat_id,
            'Пожалуйста, отправьте партнерскую ссылку для поиска'
          );

        }

      break;

      case ( $text === '/search' ):

        $client->sendMessage(
          $chat_id,
          'Введите партнерскую ссылку, чтобы найти связанную с ней публикацию на канале'
        );

        $storage['section'] = 'search';

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

          $answer = $client->sendMessage(
            '-1001432760770', $storage['ready']['text'], 'markdown',
            null, null, null, null, null,
            [
              'inline_keyboard' =>
              [
                [ [ "text" => "Купить 🧨", "url" => $storage['ready']['url'], ] ],
                [ [ "text" => $config['react'][0], "callback_data" => "finger" ], [ "text" => $config['react'][1], "callback_data" => "emoji" ] ]
              ]
            ]
          );

          $storage['posts'][$storage['ready']['url']][] = $answer->result->message_id;

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
          'Действие было отменено'
        );

        unset( $storage['section'] );
        unset( $storage['ready'] );

      break;

      case ( $text === '/start' ):

        $client->sendMessage(
          $chat_id,
          'Привет, я бот который может публиковать посты на канале! Если ты администратор, то можешь воспользоваться мной'
        );

      break;

      default:

        $client->sendMessage(
          $chat_id,
          'Такой команды не найдено'
        );

      break;

    }

}

if ( isset( $update['callback_query'] ) ) {

    $update = $update['callback_query'];
    $message = $update['message'];

    $id = $update['id'];
    $user_id = $update['from']['id'];

    $chat_id = $message['chat']['id'];
    $message_id = $message['message_id'];
    $buttons = $message['reply_markup']['inline_keyboard'];

    if ( $update['data'] == "finger" ) {

      if ( !isset( $storage['rating'][$user_id][$message_id] ) ) {

        $buttons[1][0]['text'] = $config['react'][0] . ' ' . ( intval( ltrim( $buttons[1][0]['text'], $config['react'][0] ) ) + 1 );
        $storage['rating'][$user_id][$message_id] = time();

        $voted = 1;

      }

    } elseif ( $update['data'] == "emoji" ) {

      if ( !isset( $storage['rating'][$user_id][$message_id] ) ) {

        $buttons[1][1]['text'] = $config['react'][1] . ' ' . ( intval( ltrim( $buttons[1][1]['text'], $config['react'][1] ) ) + 1 );
        $storage['rating'][$user_id][$message_id] = time();

        $voted = 1;

      }

    }

    if ( isset( $voted ) ) {

      $client->answerCallbackQuery( $id, 'Спасибо! Вы изменили рейтинг' );

      $client->editMessageText(
        $chat_id, $message_id, null, $message['text'],
        null, $update['message']['entities'], null,
        [ 'inline_keyboard' => $buttons ]
      );

    }
    else {

      $client->answerCallbackQuery( $id, 'Вы уже голосовали' );

    }

}

if ( $storage = @json_encode( $storage ) ) {
  $redis->set( 'storage', $storage );
}
