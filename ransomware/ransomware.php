<?php
/**
 * Ransomware Victim Watchlist Notifier
 * 
 * Monitors the ransomware.live API for new ransomware victims and sends 
 * real-time notifications to Discord via webhooks. Tracks processed victims
 * using MD5 checksums to prevent duplicate notifications and provides
 * detailed victim information including company data, attack timeline,
 * and infostealer compromises when available.
 *
 * @package     DiscordWatchlists
 * @subpackage  Ransomware
 * @author      gl0bal01
 * @version     1.0.0
 * @since       2024-11-16
 * @license     MIT License
 * 
 * @link        https://github.com/gl0bal01/discord-watchlists
 * @link        https://api.ransomware.live/recentvictims (Data Source)
 * @link        https://ransomware.live (Project Website)
 * 
 * @requires    PHP 7.4+
 * @requires    cURL extension
 * @requires    JSON extension
 * @requires    file_get_contents() with URL support
 * @features
 * - Real-time ransomware victim monitoring
 * - Discord webhook notifications with rich embeds
 * - Duplicate prevention using MD5 checksums
 * - Color-coded alerts (green for FR, red for others)
 * - Infostealer data integration when available
 * - Screenshot thumbnails for visual identification
 * - Comprehensive victim metadata (country, activity, timeline)
 * 
 * @api_endpoints
 * - GET https://api.ransomware.live/recentvictims - Recent ransomware victims
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
        if (curl_errno($ch) || ($httpCode != 200 && $httpCode != 204)) {
            error_log('Webhook send error: ' . curl_error($ch) . ' HTTP Code: ' . $httpCode);
            error_log('Response: ' . $response);
        }
        curl_close($ch);
    }

    private function createEmbedFromVictim($victim) {
        // Helper function to safely get field values
        $getValue = function($key) use ($victim) {
            return isset($victim[$key]) && !empty($victim[$key]) ? $victim[$key] : 'N/A';
        };

        $fields = [
            ['name' => 'Website', 'value' => $getValue('website'), 'inline' => true],
            ['name' => 'Group Name', 'value' => $getValue('group_name'), 'inline' => true],
            ['name' => 'Country', 'value' => $getValue('country'), 'inline' => true],
            ['name' => 'Activity', 'value' => $getValue('activity'), 'inline' => true],
            ['name' => 'Discovered', 'value' => $getValue('discovered'), 'inline' => true],
            ['name' => 'Published', 'value' => $getValue('published'), 'inline' => true],
            ['name' => 'Description', 'value' => substr($getValue('description'), 0, 1024), 'inline' => false],
            ['name' => 'Post URL', 'value' => $getValue('post_url'), 'inline' => false],
        ];

        // Handle infostealer data safely
        if (isset($victim['infostealer']) && is_array($victim['infostealer'])) {
            $infostealer = $victim['infostealer'];
            $infostealerInfo = "Employees: " . ($infostealer['employees'] ?? 'N/A') . "\n" .
                            "Third Parties: " . ($infostealer['thirdparties'] ?? 'N/A') . "\n" .
                            "Users: " . ($infostealer['users'] ?? 'N/A') . "\n" .
                            "Last Update: " . ($infostealer['update'] ?? 'N/A');
            $fields[] = ['name' => 'Infostealer Data', 'value' => $infostealerInfo, 'inline' => false];
        } elseif (isset($victim['infostealer']) && is_string($victim['infostealer'])) {
            // Handle case where infostealer is a string
            $fields[] = ['name' => 'Infostealer Data', 'value' => $victim['infostealer'], 'inline' => false];
        }

        // Remove fields with 'N/A' values
        $fields = array_filter($fields, function($field) {
            return !empty(trim($field['value'])) && trim($field['value']) !== 'N/A';
        });

        $color = ($getValue('country') === 'FR') ? hexdec('00FF00') : hexdec('FF0000');

        return [
            'title' => $getValue('post_title'),
            'description' => "",
            'color' => $color,
            'fields' => array_values($fields), // Reindex array after filtering
            'footer' => [
                'text' => "Notification - " . date('Y-m-d H:i:s')
            ],
            'thumbnail' => [
                'url' => $getValue('screenshot')
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
                sleep(1);
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
