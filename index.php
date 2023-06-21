<?php
$token = '6201967915:AAFOVZU5sYKt-bXvjyKwAFn2xkh5Ds_1OFw';
$apiUrl = 'https://api.telegram.org/bot' . $token . '/';

function getUpdates($offset = null) {
    global $apiUrl;  
    $url = $apiUrl . 'getUpdates';
    if ($offset) {
        $url .= '?offset=' . $offset;
    }
    $response = file_get_contents($url);
    return json_decode($response, true);
}

function sendMessage($chatId, $text, $keyboard = null) {
    global $apiUrl;
    
    $url = $apiUrl . 'sendMessage?chat_id=' . $chatId . '&text=' . urlencode($text);
    if ($keyboard) { 
        $keyboard = json_encode($keyboard);
        $url .= '&reply_markup=' . urlencode($keyboard);
    }  
    file_get_contents($url);
}

function createOrderReport($order) {
    $report = "Номер заказу: {$order['id']}\n";
    $report .= "Місто заказу: {$order['city']}\n";
    $report .= "Компанія заказу: {$order['company']}\n";
    $report .= "Страви:\n";
    foreach ($order['items'] as $item) {
        $report .= "- {$item}\n";
    }
    return $report;
}

    $offset = null;
    $cart = []; // Змінна для зберігання корзини
    $selectedCity = null; // Змінна для зберігання вибраного міста
    $selectedCompany = null; // Змінна для зберігання вибраної компанії

while (true) {
    $updates = getUpdates($offset);
    if (isset($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $offset = $update['update_id'] + 1;
            if (isset($update['message']['text'])) {
                $message = $update['message']['text'];
                $chatId = $update['message']['chat']['id'];
                
                if (strpos($message, '/start') !== false) {
                    $cityKeyboard = [
                        'keyboard' => [
                            [['text' => 'Київ']],
                            [['text' => 'Чернігів']],
                            [['text' => 'Харків']],
                        ],
                        'resize_keyboard' => true
                    ];
                    sendMessage($chatId, 'Ласкаво просимо! Будь ласка, виберіть місто:', $cityKeyboard);
                } elseif (in_array($message, ['Київ', 'Чернігів', 'Харків'])) {
                    $selectedCity = $message; // Збереження вибраного міста
                    $companyKeyboard = [
                        'keyboard' => [
                            [['text' => 'Pizza Bit']],
                            [['text' => 'La Pizza']],
                            [['text' => 'IQ Pizza']],
                            [['text' => 'Редагувати місто']],
                        ],
                        'resize_keyboard' => true
                    ];
                    sendMessage($chatId, 'Ви вибрали: ' . $selectedCity . '. Будь ласка, виберіть компанію:', $companyKeyboard);
                } elseif (in_array($message, ['Pizza Bit', 'La Pizza', 'IQ Pizza'])) {
                    $selectedCompany = $message; // Збереження вибраної компанії
                    $menuKeyboard = [
                        'keyboard' => [
                            [['text' => 'Піца']],
                            [['text' => 'Бургер']],
                            [['text' => 'Картопля фрі']],
                            [['text' => 'Редагувати компанію']],
                        ],
                        'resize_keyboard' => true
                    ];
                    sendMessage($chatId, 'Ви вибрали: ' . $selectedCompany . '. Будь ласка, виберіть страву:', $menuKeyboard);
                } elseif (in_array($message, ['Піца', 'Бургер', 'Картопля фрі'])) {
                    if ($selectedCity === null || $selectedCompany === null) {
                        sendMessage($chatId, 'Будь ласка, спочатку виберіть місто та компанію.');
                    } else {
                        array_push($cart, [
                                'id' => $chatId, // Збереження ID користувача телеграм у корзині
                                'city' => $selectedCity,
                                'company' => $selectedCompany,
                                'item' => $message
                             ]);
                             
                        sendMessage($chatId, 'Ви додали ' . $message . ' від ' . $selectedCompany . ' у місто ' . $selectedCity . ' до вашої корзини. Бажаєте ще щось?');
                    }
                } elseif ($message === 'Перевірити корзину') {
                    $userCart = array_filter($cart, function ($item) use ($chatId) {
                        return $item['id'] === $chatId;
                    });
                    if (empty($userCart)) {
                        sendMessage($chatId, 'Ваша корзина порожня.');
                    } else {
                        $cartText = 'Ваша корзина:';
                        foreach ($userCart as $item) {
                            $cartText .= "\n" . $item['item'] . ' від ' . $item['company'] . ' у місто ' . $item['city'];
                        }
                        sendMessage($chatId, $cartText);
                    }
                } elseif ($message === 'Очистити корзину') {
                    $cart = array_filter($cart, function ($item) use ($chatId) {
                        return $item['id'] !== $chatId;
                    });
                    sendMessage($chatId, 'Ваша корзина була очищена.');
                } elseif ($message === 'Редагувати місто') {
                    $selectedCity = null;
                    $cityKeyboard = [
                        'keyboard' => [
                            [['text' => 'Київ']],
                            [['text' => 'Чернігів']],
                            [['text' => 'Харків']],
                            [['text' => 'Редагувати місто']],
                        ],
                        'resize_keyboard' => true
                    ];
                    sendMessage($chatId, 'Будь ласка, виберіть нове місто:', $cityKeyboard);
                } elseif ($message === 'Редагувати компанію') {
                    $selectedCompany = null;
                    $companyKeyboard = [
                        'keyboard' => [
                            [['text' => 'Pizza Bit']],
                            [['text' => 'La Pizza']],
                            [['text' => 'IQ Pizza']],
                            [['text' => 'Редагувати компанію']],
                        ],
                        'resize_keyboard' => true
                    ];
                    sendMessage($chatId, 'Будь ласка, виберіть нову компанію:', $companyKeyboard);
                } elseif (in_array($message, ['так', 'ні'])) {
                    if ($message === 'ні') {
                        // Створення звіту
                        $orderId = uniqid(); // Унікальний ідентифікатор замовлення
                        $userCart = array_filter($cart, function ($item) use ($chatId) {
                            return $item['id'] === $chatId;
                        });
                        $order = [
                            'id' => $orderId,
                            'city' => $selectedCity,
                            'company' => $selectedCompany,
                            'items' => array_column($userCart, 'item')
                        ];
                        $orderReport = createOrderReport($order);
                        sendMessage($chatId, "Ваше замовлення розміщено. Дякуємо!☺️\n\nЗвіт замовлення:\n{$orderReport}");
                        // Очищення корзини
                        $cart = array_filter($cart, function ($item) use ($chatId) {
                            return $item['id'] !== $chatId;
                        });
                        // Відправлення звіту адміністратору
                        $adminChatId = '123456789'; // ТРЕБА замінити на фактичний chat_id адміністратора чи ще чогось
                        sendMessage($adminChatId, "Нове замовлення отримано:\n\n{$orderReport}");
                    } else {
                        sendMessage($chatId, 'Ваше замовлення скасовано.');
                    }
                }
            }
        }
    }
}
?>
