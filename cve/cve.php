<?php
/**
 * CVE Watchlist
 *
 * @author gl0bal01
 */

if (!extension_loaded('curl')) {
    die('The cURL extension is not installed or enabled. Please install it to continue.');
}
class DiscordVulnerabilityNotifier {
    private $webhookUrl;
    private $checksumFile;
    private $jsonUrl;

    public function __construct($webhookUrl, $jsonUrl, $checksumFile = 'processed_cves.txt') {
        $this->webhookUrl = $webhookUrl;
        $this->jsonUrl = $jsonUrl;
        $this->checksumFile = $checksumFile;
    }

    private function getProcessedCves() {
        if (!file_exists($this->checksumFile)) {
            return [];
        }
        return array_filter(explode("\n", file_get_contents($this->checksumFile)));
    }

    private function addProcessedCve($cveId) {
        file_put_contents($this->checksumFile, $cveId . "\n", FILE_APPEND);
    }

    private function isVulnerabilityProcessed($cveId) {
        $processedCves = $this->getProcessedCves();
        return in_array($cveId, $processedCves);
    }

    private function fetchVulnerabilities() {
        $ch = curl_init($this->jsonUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
            return null;
        }

        curl_close($ch);
        $data = json_decode($response, true);
        error_log('API Response: ' . json_encode($data)); // Debug log
        return $data;
    }

    private function sendDiscordWebhook(array $embeds) {
        $payload = [
            'embeds' => $embeds
        ];

        // Send webhook
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout to 10 seconds
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode != 200) {
            error_log('Webhook send error: ' . curl_error($ch) . ' HTTP Code: ' . $httpCode);
            error_log('Response: ' . $response);
        }

        curl_close($ch);
    }

private function createEmbedFromVulnerability($vulnerability) {
    error_log('Vulnerability data: ' . json_encode($vulnerability));

    $fields = [
        ['name' => 'CVE ID', 'value' => $vulnerability['cveID'] ?? 'N/A', 'inline' => true],
        ['name' => 'Date Added', 'value' => $vulnerability['dateAdded'] ?? 'N/A', 'inline' => true],
        ['name' => 'Due Date', 'value' => $vulnerability['dueDate'] ?? 'N/A', 'inline' => true],
        ['name' => 'Vendor/Project', 'value' => $vulnerability['vendorProject'] ?? 'N/A', 'inline' => false],
        ['name' => 'Required Action', 'value' => $vulnerability['requiredAction'] ?? 'N/A', 'inline' => false]
    ];

    $color = hexdec('FF0000'); // Default red
    
    if (isset($vulnerability['nvdData']) && is_array($vulnerability['nvdData']) && !empty($vulnerability['nvdData'])) {
        $nvdData = $vulnerability['nvdData'][0] ?? [];
        $additionalFields = [
            ['name' => 'Attack Complexity', 'value' => $nvdData['attackComplexity'] ?? 'N/A', 'inline' => true],
            ['name' => 'Attack Vector', 'value' => $nvdData['attackVector'] ?? 'N/A', 'inline' => true],
            ['name' => 'Base Score', 'value' => number_format($nvdData['baseScore'], 2) ?? 'N/A', 'inline' => true],
            ['name' => 'Base Severity', 'value' => $nvdData['baseSeverity'] ?? 'N/A', 'inline' => true],
            ['name' => 'Exploitability Score', 'value' => number_format($nvdData['exploitabilityScore'], 2) ?? 'N/A', 'inline' => true],
        ];
        $fields = array_merge($fields, $additionalFields);

        $exploitabilityScore = (float)($nvdData['exploitabilityScore'] ?? 0);

        if (($nvdData['attackComplexity'] ?? '') === 'LOW' &&
            ($nvdData['attackVector'] ?? '') === 'NETWORK' &&
            ($nvdData['baseSeverity'] ?? '') === 'CRITICAL' &&
            (float)($nvdData['baseScore'] ?? 0) >= 8.0 &&
            $exploitabilityScore >= 6.9) {
            $color = hexdec('00FF00'); // Green
        } elseif (($nvdData['attackComplexity'] ?? '') === 'LOW' &&
            ($nvdData['attackVector'] ?? '') === 'NETWORK' &&
            ($nvdData['baseSeverity'] ?? '') === 'CRITICAL' &&
            (float)($nvdData['baseScore'] ?? 0) >= 8.0 &&
	    $exploitabilityScore >= 3.0 && $exploitabilityScore <= 6.8) {
            $color = hexdec('FFFF00'); // Yellow
        }
    }

    // Add GitHub PoCs
    if (isset($vulnerability['githubPocs']) && is_array($vulnerability['githubPocs'])) {
        $pocsText = implode("\n", $vulnerability['githubPocs']);
        $fields[] = ['name' => 'GitHub PoCs', 'value' => $pocsText, 'inline' => false];
    }

    // Add Notes
    if (isset($vulnerability['notes'])) {
        $fields[] = ['name' => 'Notes', 'value' => $vulnerability['notes'], 'inline' => false];
    }

    // Remove fields with 'N/A' values
    $fields = array_filter($fields, function($field) {
        return $field['value'] !== 'N/A';
    });

    return [
        'title' => $vulnerability['vulnerabilityName'] ?? 'Unknown Vulnerability',
        'description' => $vulnerability['shortDescription'] ?? 'No description available',
        'color' => $color,
        'fields' => array_values($fields),
        'footer' => [
            'text' => 'Vulnerability Notification - ' . date('Y-m-d H:i:s')
        ]
    ];
}


    public function processVulnerabilities() {
        // Fetch vulnerabilities
        $data = $this->fetchVulnerabilities();

        if (!$data || !isset($data['vulnerabilities'])) {
            error_log('No vulnerabilities found or error in fetching data');
            return;
        }

        // Process each vulnerability
        foreach ($data['vulnerabilities'] as $vulnerability) {
            $cveId = $vulnerability['cveID'] ?? '';

            // Skip if already processed
            if (!$cveId || $this->isVulnerabilityProcessed($cveId)) {
                continue;
            }

            // Create embed
            $embed = $this->createEmbedFromVulnerability($vulnerability);

            // Send webhook with single embed
            $this->sendDiscordWebhook([$embed]);

            // Mark as processed
            $this->addProcessedCve($cveId);
        }
    }
}

// Configuration
$config = require __DIR__ . '/../src/config/config.php';
$webhookUrl = $config['cve_webhook_url'];

error_log('Webhook URL: ' . $webhookUrl); // Debug log

$jsonUrl = 'https://kevin.gtfkd.com/kev';
$checksumFile = __DIR__ . '/processed_cves.txt';

// Initialize and run
$notifier = new DiscordVulnerabilityNotifier($webhookUrl, $jsonUrl, $checksumFile);
$notifier->processVulnerabilities();

