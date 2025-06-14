<?php
/**
 * FBI Watchlist
 *
 * @author gl0bal01
 */

if (!extension_loaded('curl')) {
    die('The cURL extension is not installed or enabled. Please install it to continue.');
}
// Discord webhook URL
$config = require __DIR__ . '/../src/config/config.php';
$webhookUrl = $config['fbi_webhook_url'];

// File to track processed UIDs
$uidLogFile = 'uids';

// FBI API URL with sorting parameter
$fbiApiUrl = 'https://api.fbi.gov/@wanted?pageSize=15&page=1&sort_order=desc&sort_on=publication';

// Set User-Agent and other headers
$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko/20100101 Firefox/68.0\r\n" .
                    "Accept: application/json\r\n" .
                    "Accept-Language: en-US,en;q=0.5\r\n"
    ]
]);

// Fetch data with error handling
$response = @file_get_contents($fbiApiUrl, false, $context);
if ($response === FALSE) {
    $error = error_get_last();
    error_log('Error fetching FBI API data: ' . $error['message']);
    die('Error fetching FBI API data. Please check the error log.');
}

$data = json_decode($response, true);
if (!isset($data['items']) || !is_array($data['items'])) {
    error_log('Invalid API response structure.');
    die('Invalid API response structure.');
}

// Load processed UIDs from file
$processedUids = file_exists($uidLogFile) ? file($uidLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$newUids = [];

// Loop through the items and send new ones to Discord
foreach ($data['items'] as $item) {
    $uid = $item['uid'] ?? '';
    if (empty($uid) || in_array($uid, $processedUids)) {
        continue;
    }

    // Calculate age if date of birth is available
    $age = 'Unknown';
    if (!empty($item['dates_of_birth_used'])) {
        $dob = $item['dates_of_birth_used'][0];
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

    // Prepare the message
    $message  = "==============================\n";
    $message .= "# " . htmlspecialchars($item['title'] ?? 'Unknown') . "\n";
    $message .= "==============================\n\n";

    if (!empty($item['publication'])) {
        $message .= "__**Publication Date**__: " . htmlspecialchars($item['publication']) . "\n";
    }
    if (!empty($item['status']) && strtolower($item['status']) !== 'na') {
        $message .= "**Status**: " . htmlspecialchars($item['status']) . "\n";
    }
    if (!empty($item['sex'])) {
        $message .= "**Sex**: " . htmlspecialchars($item['sex']) . "\n";
    }
    if (!empty($item['race_raw'])) {
        $message .= "**Race**: " . htmlspecialchars($item['race_raw']) . "\n";
    }
    if (!empty($item['nationality'])) {
        $message .= "**Nationality**: " . htmlspecialchars($item['nationality']) . "\n";
    }
    if (!empty($item['place_of_birth'])) {
        $message .= "**Place of Birth**: " . htmlspecialchars($item['place_of_birth']) . "\n";
    }
    if (!empty($item['dates_of_birth_used'])) {
        $message .= "**Date(s) of Birth Used**: " . htmlspecialchars(implode(', ', $item['dates_of_birth_used'])) . "\n";
    }
    if($age != 'Unknown'){
       $message .= "**Age**: " . $age . "\n";
    }
    if (!empty($item['age_range'])) {
        $message .= "**Age Range**: " . htmlspecialchars($item['age_range']) . "\n";
    }
    if (!empty($item['height_min']) && !empty($item['height_max'])) {
        $message .= "**Height**: " . htmlspecialchars($item['height_min']) . " - " . htmlspecialchars($item['height_max']) . " inches\n";
    }
    if (!empty($item['weight_min']) && !empty($item['weight_max'])) {
        $message .= "**Weight**: " . htmlspecialchars($item['weight_min']) . " - " . htmlspecialchars($item['weight_max']) . " pounds\n";
    }
    if (!empty($item['hair_raw'])) {
        $message .= "**Hair**: " . htmlspecialchars($item['hair_raw']) . "\n";
    }
    if (!empty($item['eyes'])) {
        $message .= "**Eyes**: " . htmlspecialchars($item['eyes']) . "\n";
    }
    if (!empty($item['scars_and_marks'])) {
        $message .= "**Scars and Marks**: " . htmlspecialchars($item['scars_and_marks']) . "\n";
    }
    if (!empty($item['ncic'])) {
        $message .= "**NCIC**: " . htmlspecialchars($item['ncic']) . "\n";
    }
    if (!empty($item['occupations'])) {
        $message .= "**Occupations**: " . htmlspecialchars(implode(', ', $item['occupations'])) . "\n";
    }
    if (!empty($item['possible_countries'])) {
        $message .= "**Possible Countries**: " . htmlspecialchars(implode(', ', $item['possible_countries'])) . "\n";
    }
    if (!empty($item['possible_states'])) {
        $message .= "**Possible States**: " . htmlspecialchars(implode(', ', $item['possible_states'])) . "\n";
    }
    if (!empty($item['locations'])) {
        $message .= "**Locations**: " . htmlspecialchars(implode(', ', $item['locations'])) . "\n";
    }
if (!empty($item['field_offices'])) {
    $message .= "**Field Offices**: " . htmlspecialchars(implode(', ', $item['field_offices'])) . "\n";
}
    if (!empty($item['person_classification'])) {
        $message .= "**Person Classification**: " . htmlspecialchars($item['person_classification']) . "\n";
    }
if (!empty($item['poster_classification'])) {
    $message .= "**Poster Classification**: " . htmlspecialchars($item['poster_classification']) . "\n";
}
    if (!empty($item['subjects'])) {
        $message .= "**Subjects**: " . htmlspecialchars(implode(', ', $item['subjects'])) . "\n";
    }
    if (!empty($item['aliases'])) {
        $message .= "**Aliases**: " . htmlspecialchars(implode(', ', $item['aliases'])) . "\n";
    }
    if (!empty($item['reward_text'])) {
        $message .= "\n**Reward**: " . htmlspecialchars($item['reward_text']) . "\n";
    } elseif (!empty($item['reward_min']) && $item['reward_min'] > 0) {
        $message .= "**Reward**: $" . number_format($item['reward_min'], 2) . "\n";
    }
    if (!empty($item['description'])) {
        $message .= "\n**Description**: " . htmlspecialchars(strip_tags($item['description'])) . "\n";
    }
    if (!empty($item['caution'])) {
        $message .= "\n**Caution**: " . htmlspecialchars(strip_tags($item['caution'])) . "\n";
    }
    if (!empty($item['remarks'])) {
        $message .= "\n**Remarks**: " . htmlspecialchars(strip_tags($item['remarks'])) . "\n";
    }
    if (!empty($item['details'])) {
        $message .= "\n**Details**: " . htmlspecialchars(strip_tags($item['details'])) . "\n";
    }
    if (!empty($item['warning_message'])) {
        $message .= "\n**Warning**: " . htmlspecialchars($item['warning_message']) . "\n";
    }
    if (!empty($item['publication_remarks'])) {
        $message .= "**Publication Remarks**: " . htmlspecialchars($item['publication_remarks']) . "\n";
    }
    if (!empty($item['url'])) {
        $message .= "**More Info**: <" . $item['url'] . ">\n";
    }
    if (!empty($item['files']) && is_array($item['files'])) {
        $message .= "\n**File(s)**:\n";
        foreach ($item['files'] as $file) {
            if (!empty($file['url'])) {
                $message .= "- <" . $file['url'] . ">\n";
            }
        }
    }

    // Prepare the embed with image
    $embed = [
        'title' => $item['title'] ?? 'FBI Wanted',
        'description' => substr($item['description'] ?? '', 0, 200) . '...',
        'url' => $item['url'] ?? '',
        'color' => 15158332, // Red color
        'thumbnail' => [
            'url' => $item['images'][0]['thumb'] ?? ''
        ],
        'fields' => [
            [
            'name' => "More information",
            'value' => '[All details](https://fbi.gov' . $item['path'] . ')'
            ]
        ]
    ];

    // Send to Discord
    $discordPayload = json_encode([
        'content' => $message,
        'embeds' => [$embed]
    ]);

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $discordPayload,
        ],
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($webhookUrl, false, $context);

    if ($result === FALSE) {
        error_log("Failed to send message for UID: $uid");
        continue;
    }

    // Add UID to the new UIDs array
    $newUids[] = $uid;

    // Respect Discord rate limits
    sleep(2);
}

// Append new UIDs to the log file
if (!empty($newUids)) {
    file_put_contents($uidLogFile, implode("\n", $newUids) . "\n", FILE_APPEND | LOCK_EX);
}

