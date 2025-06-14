<?php
/**
 * Fun
 *
 * @author gl0bal01
 */

if (!extension_loaded('curl')) {
    die('The cURL extension is not installed or enabled. Please install it to continue.');
}

$config = require __DIR__ . '/../src/config/config.php';
$webhookUrl = $config['fun_webhook_url'];


function sendDailyMessage() {
    global $webhookUrl;

    $apis = [
        'https://api.chucknorris.io/jokes/random' => 'value',
        'https://uselessfacts.jsph.pl/api/v2/facts/random' => 'text',
        'https://v2.jokeapi.dev/joke/Any?blacklistFlags=racist' => 'joke',
        'https://icanhazdadjoke.com/' => 'joke',
        'https://buddha-api.com/api/random' => 'buddha',
        'https://zenquotes.io/api/random' => 'zen'
    ];

    $dailyMessage = "**Your Daily Dose of Fun and Wisdom:**\n\n";

    foreach ($apis as $url => $key) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: My Discord Bot (https://github.com/yourusername/yourrepo)'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($url === 'https://v2.jokeapi.dev/joke/Any?blacklistFlags=racist') {
            if ($data['type'] === 'single') {
                $dailyMessage .= "**Joke of the Day:** " . $data[$key] . "\n\n";
            } elseif ($data['type'] === 'twopart') {
                $dailyMessage .= "**Joke of the Day:**\n" . $data['setup'] . "\n" . $data['delivery'] . "\n\n";
            }
        } elseif ($url === 'https://icanhazdadjoke.com/') {
            $dailyMessage .= "**Dad Joke of the Day:** " . $data[$key] . "\n\n";
        } elseif ($url === 'https://buddha-api.com/api/random') {
            $dailyMessage .= "**Buddha Quote of the Day:**\n" . $data['text'] . " - " . $data['byName'] . "\n\n";
        } elseif ($url === 'https://zenquotes.io/api/random') {
            $zenQuote = $data[0];
    	    $dailyMessage .= "**Zen Quote of the Day:**\n" . $zenQuote['q'] . " - " . $zenQuote['a'] . "\n\n";
        } else {
            $dailyMessage .= "**" . ($key === 'value' ? 'Chuck Norris Fact' : 'Random Fact') . ":** " . $data[$key] . "\n\n";
        }
    }

    $message = ['content' => $dailyMessage];

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}


sendDailyMessage();


