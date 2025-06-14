# Europol Watchlist

This script monitors the Europol's Most Wanted list for new additions and sends a notification to a Discord channel via a webhook.

![Europole Watchlist](/assets/europole.png)

## What it does

The script fetches a list of wanted persons from the Europol's Most Wanted list, checks if each person has already been processed, and if not, sends a notification to a Discord channel with the person's details.

## Installation

1. **Configure the webhook URL:**

   - Navigate to the `src/config` directory and rename `config.example.php` to `config.php`.
   - Open `config.php` and replace `YOUR_EUROPOL_WEBHOOK_URL` with your actual Discord webhook URL.

2. **Install dependencies:**

   This project requires PHP and the `php-curl` extension. You can install them on a Debian-based system with the following command:

   ```bash
   sudo apt-get install php php-curl
   ```

## Running the script

You can run the script from the command line:

```bash
php europol.php
```

You can also set up a cron job to run the script automatically at a specified interval. For example, to run the script every hour, you would add the following line to your crontab:

```bash
0 * * * * php /path/to/your/project/europol/europol.php
```


**⭐ Star this repo if you find it helpful!**