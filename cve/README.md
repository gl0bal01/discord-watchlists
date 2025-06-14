# CVE Watchlist

This script monitors a CVE feed for new vulnerabilities and sends a notification to a Discord channel via a webhook.

![CVE Watchlist](/assets/cve2.png)

## What it does

The script fetches a list of CVEs from a JSON feed, checks if each CVE has already been processed, and if not, sends a notification to a Discord channel with the CVE details.

## Installation

1. **Configure the webhook URL:**

   - Navigate to the `src/config` directory and rename `config.example.php` to `config.php`.
   - Open `config.php` and replace `YOUR_CVE_WEBHOOK_URL` with your actual Discord webhook URL.

2. **Install dependencies:**

   This project requires PHP and the `php-curl` extension. You can install them on a Debian-based system with the following command:

   ```bash
   sudo apt-get install php php-curl
   ```

## Running the script

You can run the script from the command line:

```bash
php cve.php
```

You can also set up a cron job to run the script automatically at a specified interval. For example, to run the script every hour, you would add the following line to your crontab:

```bash
0 * * * * php /path/to/your/project/cve/cve.php
```

## Criteria Customization

You can easily customize the color of the Discord embed based on the CVE's severity. Open the `cve.php` file and navigate to the `createEmbedFromVulnerability` function. You will find the following code:

```php
$color = hexdec('FF0000'); // Default red

if (isset($vulnerability['nvdData']) && is_array($vulnerability['nvdData']) && !empty($vulnerability['nvdData'])) {
    // ...
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
```

**You can change the default color and the conditions for changing the color to suit your needs.**

⭐ Star this repo if you find it helpful!