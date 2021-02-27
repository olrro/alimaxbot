<?php


$config = [];

$config['token'] = '1653915444:AAHJonDEBA8e6IUP4DREHnvU80Plz86w7Oo';

$config['params'] = [
  '<meta property="og:image" content="(.*)"\/>' => 'image',
  '"totalValidNum":(.*),' => 'reviews',
  '"formatTradeCount":"(.*)",' => 'orders',
  '"averageStar":"(.*)",' => 'rating',
  '"totalAvailQuantity":(.*)},' => 'quantity',
  '"discount":(.*),' => 'discount',
];

$config['react'] = [ '👍', '😜' ];
