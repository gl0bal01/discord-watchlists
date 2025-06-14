<?php
/**
 * Ransomware Watchlist
 *
 * @author gl0bal01
 */

if (!extension_loaded('curl')) {
    die('The cURL extension is not installed or enabled. Please install it to continue.');
}

class RansomwareVictimNotifier {
    private $webhookUrl;
    private $checksumFile;
    private $jsonFile;

    public function __construct($webhookUrl, $checksumFile) {
        $this->webhookUrl = $webhookUrl;
        $this->checksumFile = $checksumFile;
        $this->jsonFile = __DIR__ . '/recentvictims.json';
    }

    public function fetchAndSaveVictims() {
        $jsonUrl = "https://api.ransomware.live/recentvictims";
        $jsonContent = file_get_contents($jsonUrl);
        file_put_contents($this->jsonFile, $jsonContent);
    }

    private function getProcessedVictims() {
        if (!file_exists($this->checksumFile)) {
            return [];
        }
        return array_filter(explode("\n", file_get_contents($this->checksumFile)));
    }

    private function generateChecksum($victim) {
        return md5($victim['post_title'] . $victim['published']);
    }

    private function addProcessedVictim($checksum) {
        file_put_contents($this->checksumFile, $checksum . "\n", FILE_APPEND);
    }

    private function isVictimProcessed($checksum) {
        $processedVictims = $this->getProcessedVictims();
        return in_array($checksum, $processedVictims);
    }

    private function fetchVictims() {
        $jsonContent = file_get_contents($this->jsonFile);
        return json_decode($jsonContent, true);
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
        if (curl_errno($ch) || $httpCode != 200) {
            error_log('Webhook send error: ' . curl_error($ch) . ' HTTP Code: ' . $httpCode);
            error_log('Response: ' . $response);
        }
        curl_close($ch);
    }

    private function createEmbedFromVictim($victim) {
        $fields = [
            ['name' => 'Website', 'value' => $victim['website'], 'inline' => true],
            ['name' => 'Group Name', 'value' => $victim['group_name'], 'inline' => true],
            ['name' => 'Country', 'value' => $victim['country'], 'inline' => true],
            ['name' => 'Activity', 'value' => $victim['activity'], 'inline' => true],
            ['name' => 'Discovered', 'value' => $victim['discovered'], 'inline' => true],
            ['name' => 'Published', 'value' => $victim['published'], 'inline' => true],
            ['name' => 'Description', 'value' => substr($victim['description'], 0, 1024), 'inline' => false],
            ['name' => 'Post URL', 'value' => $victim['post_url'], 'inline' => false],
        ];

        if (isset($victim['infostealer'])) {
            $infostealerInfo = "Employees: {$victim['infostealer']['employees']}\n" .
                               "Third Parties: {$victim['infostealer']['thirdparties']}\n" .
                               "Users: {$victim['infostealer']['users']}\n" .
                               "Last Update: {$victim['infostealer']['update']}";
            $fields[] = ['name' => 'Infostealer Data', 'value' => $infostealerInfo, 'inline' => false];
        }

        $color = $victim['country'] === 'FR' ? hexdec('00FF00') : hexdec('FF0000');

        return [
            'title' => $victim['post_title'],
            'description' => "",
            'color' => $color,
            'fields' => $fields,
            'footer' => [
                'text' => "Notification - " . date('Y-m-d H:i:s')
            ],
            'thumbnail' => [
                'url' => $victim['screenshot']
            ]
        ];
    }

    public function processVictims() {
        $this->fetchAndSaveVictims();
        $victims = $this->fetchVictims();
        foreach ($victims as $victim) {
            $checksum = $this->generateChecksum($victim);
            if (!$this->isVictimProcessed($checksum)) {
                $embed = $this->createEmbedFromVictim($victim);
                $this->sendDiscordWebhook([$embed]);
                $this->addProcessedVictim($checksum);
            }
        }
    }
}

// Configuration
$config = require __DIR__ . '/../src/config/config.php';
$webhookUrl = $config['ransomware_webhook_url'];
$checksumFile = __DIR__ . '/processed_ransomware_victims.txt';

// Initialize and run the notifier
$notifier = new RansomwareVictimNotifier($webhookUrl, $checksumFile);
$notifier->processVictims();
