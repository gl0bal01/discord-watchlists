<?php
/**
 * Europol Watchlist
 *
 * @author gl0bal01
 */

if (!extension_loaded('curl')) {
    die('The cURL extension is not installed or enabled. Please install it to continue.');
}

class EuropolMostWantedNotifier {
    private $webhookUrl;
    private $checksumFile;
    private $jsonFile;

    public function __construct($webhookUrl, $checksumFile = 'processed_europol_wanted.txt') {
        $this->webhookUrl = $webhookUrl;
        $this->checksumFile = $checksumFile;

        // Generate current date and construct URL
        $currentDate = date('Ymd');
        $jsonUrl = "https://data.opensanctions.org/datasets/{$currentDate}/eu_europol_wanted/entities.ftm.json";

        // Download and save the file
        $jsonContent = file_get_contents($jsonUrl);
        $this->jsonFile = __DIR__ . '/entities.ftm.json';
        file_put_contents($this->jsonFile, $jsonContent);
    }

    private function getProcessedWanted() {
        if (!file_exists($this->checksumFile)) {
            return [];
        }
        return array_filter(explode("\n", file_get_contents($this->checksumFile)));
    }

    private function addProcessedWanted($id) {
        file_put_contents($this->checksumFile, $id . "\n", FILE_APPEND);
    }

    private function isWantedProcessed($id) {
        $processedWanted = $this->getProcessedWanted();
        return in_array($id, $processedWanted);
    }

    private function fetchWantedPersons() {
        $jsonContent = file_get_contents($this->jsonFile);
        return array_filter(array_map('json_decode', explode("\n", $jsonContent)), function($item) {
            return $item !== null && isset($item->schema) && $item->schema === 'Person';
        });
    }

    private function sendDiscordWebhook(array $embeds) {
        $payload = ['embeds' => $embeds];
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Accept both 200 (OK) and 204 (No Content) as successful responses
        if (curl_errno($ch) || ($httpCode != 200 && $httpCode != 204)) {
            error_log('Webhook send error: ' . curl_error($ch) . ' HTTP Code: ' . $httpCode);
            error_log('Response: ' . $response);
        } 
        curl_close($ch);
    }

    private function createEmbedFromWanted($wanted) {
        $properties = (array)$wanted->properties;

        // Helper function to safely get property values
        $getProperty = function($key, $default = ['N/A']) use ($properties) {
            return isset($properties[$key]) && !empty($properties[$key]) ? (array)$properties[$key] : $default;
        };

        $fields = [
            ['name' => 'Name', 'value' => implode(", ", $getProperty('name')), 'inline' => true],
            ['name' => 'Birth Date', 'value' => implode(", ", $getProperty('birthDate')), 'inline' => true],
            ['name' => 'Nationality', 'value' => strtoupper(implode(", ", $getProperty('nationality'))), 'inline' => true],
            ['name' => 'Ethnicity', 'value' => implode(", ", $getProperty('ethnicity')), 'inline' => true],
            ['name' => 'Height', 'value' => implode(", ", $getProperty('height')), 'inline' => true],
            ['name' => 'Eye Color', 'value' => implode(", ", $getProperty('eyeColor')), 'inline' => true],
            ['name' => 'Appearance', 'value' => implode(", ", $getProperty('appearance')), 'inline' => false],
            ['name' => 'Notes', 'value' => implode("\n", $getProperty('notes')), 'inline' => false],
            ['name' => 'Photos & More', 'value' => implode(", ", $getProperty('sourceUrl')), 'inline' => false]
        ];

        $color = hexdec('FF0000'); // Default red
        $notes = implode(", ", $getProperty('notes', []));

        if (stripos($notes, "murder") !== false) {
            $color = $color; //hexdec('00FF00'); Green
        } elseif (stripos($notes, "fraud") !== false || stripos($notes, "trafficking") !== false) {
            $color = hexdec('FFA500'); // Orange for medium severity crimes
        }

        return [
            'title' => implode(", ", $getProperty('name', ['Unknown Person'])),
            'description' => "",
            'color' => $color,
            'fields' => array_values(array_filter($fields, function($field) {
                return !empty(trim($field['value'])) && trim($field['value']) !== 'N/A';
            })),
            'footer' => [
                'text' => "Notification - " . date('Y-m-d H:i:s')
            ],
            'thumbnail' => [
                'url' => implode(", ", $getProperty('sourceUrl'))
            ]
        ];
    }

    public function processWantedPersons() {
        $wantedPersons = $this->fetchWantedPersons();

        foreach ($wantedPersons as $person) {
            if (!$this->isWantedProcessed($person->id)) {
                $embed = $this->createEmbedFromWanted($person);
                $this->sendDiscordWebhook([$embed]);
                $this->addProcessedWanted($person->id);
            }
        }
    }
}

// Configuration
$config = require __DIR__ . '/../src/config/config.php';
$webhookUrl = $config['europol_webhook_url'];
$checksumFile = __DIR__ . '/processed_europol_wanted.txt';

// Initialize and run the notifier
$notifier = new EuropolMostWantedNotifier($webhookUrl, $checksumFile);
$notifier->processWantedPersons();

