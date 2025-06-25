[![DOI](https://zenodo.org/badge/1002155255.svg)](https://doi.org/10.5281/zenodo.15722652)

# Discord Watchlists

This project is a collection of PHP scripts that monitor various sources for new information and send notifications to a Discord channel via a webhook.

## [CVE Watchlist](/cve)
Monitors a CVE feed for new vulnerabilities and sends a notification to a Discord channel.
  
![CVE Watchlist](/assets/cve.png)

## [Europol Watchlist](/europol)
Monitors the Europol's Most Wanted list for new additions and sends a notification to a Discord channel.

![Europole Watchlist](/assets/europole.png)

## [FBI Watchlist](/fbi)
Monitors the FBI's Most Wanted list for new additions and sends a notification to a Discord channel.

![FBI Watchlist](/assets/fbi.png)

## [Ransomware Watchlist](/ransomware)
Monitors a ransomware feed for new victims and sends a notification to a Discord channel.

![Ransomware Watchlist](/assets/Ransome.png)

## [Daily Fun](/fun)
Sends a daily message with a random joke, fact, or quote to a Discord channel.

![Fun](/assets/fun.png)

## Installation

1. **Clone the repository:**

   ```bash
   git clone https://github.com/gl0bal01/discord-watchlists.git
   ```

2. **Install dependencies:**

   This project requires PHP and the `php-curl` extension. You can install them on a Debian-based system with the following command:

   ```bash
   sudo apt-get install php php-curl
   ```

3. **Configure the scripts:**

   - Navigate to the `src/config` directory and rename `config.example.php` to `config.php`.
   - Open `config.php` and replace the placeholder webhook URLs with your actual Discord webhook URLs.

## How to get a Discord webhook URL

1. **Open your Discord server and go to "Server Settings" > "Integrations".**
2. **Click on "Webhooks" and then "New Webhook".**
3. **Give your webhook a name and choose the channel you want it to post to.**
4. **Copy the webhook URL and paste it into the `config.php` file.**

## Running the scripts

You can run each script individually from the command line:

```bash
php cve/cve.php
php europol/europol.php
php fbi/fbi.php
php fun/fun.php
php ransomware/ransomware.php
```

You can also set up a cron job to run the scripts automatically at a specified interval. For example, to run the CVE script every hour, you would add the following line to your crontab:

```bash
0 * * * * php /path/to/your/project/cve/cve.php
```

**⭐ Star this repo if you find it helpful!**
