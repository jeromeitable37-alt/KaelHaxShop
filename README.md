# KAELHAX Project Market — PHP Version

This is the PHP version of the Project Market storefront, rebuilt from the supplied screenshots with a mobile-first layout.

## Included
- `index.php` — main PHP router and storefront
- `includes/config.php` — Telegram configuration + starter admin config
- `assets/kaelhax-logo.png` — supplied KAELHAX logo
- `assets/injector.jpg` — KAELHAX Injector artwork
- `assets/codmgr-mod.jpg` — KAELHAX VIP / CODMGR artwork

## Routes
- `index.php?page=shop`
- `index.php?page=product&slug=injector`
- `index.php?page=product&slug=codmgr-vip`
- `index.php?page=preview`
- `index.php?page=promos`
- `index.php?page=concerns`
- `index.php?page=terms`
- `index.php?page=account`
- `index.php?page=admin`

## Product pricing
### KAELHAX INJECTOR | CODMGR
- 7 Days Access — PHP 100
- 15 Days Access — PHP 150
- 30 Days Access — PHP 200
- Lifetime Access — PHP 250

### KAELHAX VIP ACCESS | CODMGR
- 7 Days Access — PHP 100 (2/5)
- 15 Days Access — PHP 150 (0/5)
- 30 Days Access — PHP 200 (0/5)
- Lifetime Access — PHP 350 (1/5)

## Telegram automatic order posting
Edit `includes/config.php`:

```php
const TELEGRAM_BOT_TOKEN = 'YOUR_BOT_TOKEN';
const TELEGRAM_CHAT_ID   = 'YOUR_CHANNEL_CHAT_ID';
const TELEGRAM_PUBLIC_CHANNEL = 'YOUR_CHANNEL_USERNAME';
```

Your PHP hosting must allow outbound HTTPS/cURL requests. The Telegram bot must be able to post in the target channel.

Keep the bot token only in `includes/config.php`. Do not put it into JavaScript or HTML.

## Uploading to PHP hosting
Upload the contents of this folder to the public web directory, e.g. `public_html/`.
Then open `index.php`.
