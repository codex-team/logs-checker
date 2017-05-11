# Logs checker
PHP-script for checking log-files updates.

## How it works
Script checks each log-file size. If file is not empty then script sends a message to you and moves all content to the "archive" for this file.

## Install

1. Clone this rep

2. Set up config.php

```php
# For each log-file you should add config array
array(
    'domain'       => 'CodeX dev server',
    'logFilePath'  => '/var/log/apache2/error.log',
    'codexBotLink' => 'https://bot3.ifmo.su/notifications/ABCD1234',
),
```
Get `codexBotLink` from [@codex_bot](https://t.me/codex_bot.)

3. Set up autorun

Edit cron
```bash
crontab -e
```

To run script every 5 min add this line
```bash
*/5 * * * * php -q /root/logs-checker/script.php
```
