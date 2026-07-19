# Server Setup — Raspberry Pi (self-hosted runner)

These are **one-time manual steps** to prepare a fresh Raspberry Pi before the
automated deploy workflow (`.github/workflows/deploy.yml`) can run successfully.

---

## 1. System prerequisites

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl wget nginx mariadb-server php8.2-fpm \
  php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl \
  php8.2-bcmath php8.2-zip unzip python3 python3-pip python3-venv \
  npm composer
```

---

## 2. Clone the repository

```bash
sudo mkdir -p /var/www/layrate
sudo chown layratepi:www-data /var/www/layrate
git clone https://github.com/anomalyco/LayRate-Main.git /var/www/layrate
```

> Replace `layratepi` with the actual Pi username if different. The user must
> own the deploy directory and be a member of the `www-data` group.

---

## 3. Environment configuration

```bash
cd /var/www/layrate
cp .env.example .env
php artisan key:generate
```

Edit `.env` with the correct database credentials, app URL, and any other
settings. Minimum required changes:

| Variable | Typical value |
|---|---|
| `APP_URL` | `http://<pi-ip>` |
| `DB_DATABASE` | `layrate` |
| `DB_USERNAME` | `layratepi` |
| `DB_PASSWORD` | (your chosen password) |

**Database socket note**: If MariaDB is configured to use a non-default socket
(e.g. `/tmp/mariadb.sock`), set `DB_SOCKET=/tmp/mariadb.sock` and comment out
`DB_HOST` / `DB_PORT` so Laravel connects via the socket.

---

## 4. Create the database

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS layrate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'layratepi'@'localhost' IDENTIFIED BY 'your-password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON layrate.* TO 'layratepi'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

---

## 5. Initial permissions

Before the first deploy, set ownership and permissions on writable directories:

```bash
cd /var/www/layrate
sudo chown -R layratepi:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 6. Laravel scheduler cron entry

The application registers two scheduled Artisan commands:

| Command | When | Registered in |
|---|---|---|
| `layrate:reconcile-occupancy --apply` | Daily | `bootstrap/app.php` |
| `forecast:sync-input-records` | Daily at 02:00 | `routes/console.php` |

Both require `php artisan schedule:run` to be called every minute via cron.
Add this entry (run as the `layratepi` user):

```bash
echo "* * * * * cd /var/www/layrate && php artisan schedule:run >> /dev/null 2>&1" \
  | sudo crontab -u layratepi -
```

Verify it was added:

```bash
sudo crontab -u layratepi -l
```

> **Do not** add this to the automated deploy workflow — the cron entry should
> be set once manually. Re-running `crontab` on every deploy could overwrite
> any local customizations.

---

## 7. mobile-api systemd unit

The deploy workflow automatically installs/updates the systemd unit from
`mobile-api/layrate-api.service`. On a **fresh Pi**, you have two options:

### Option A — Let the first deploy install it

Run a deploy (push to `main`). The workflow will:
1. Detect that `/etc/systemd/system/layrate-api.service` does not exist
2. Copy it from the repo
3. Run `systemctl daemon-reload`
4. Run `systemctl enable --now layrate-api`

### Option B — Install it manually before the first deploy

```bash
sudo cp /var/www/layrate/mobile-api/layrate-api.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now layrate-api
```

### Verify the service

```bash
systemctl status layrate-api
curl http://127.0.0.1:5000/api/health
```

Expected response: `{"status":"ok"}`

### Database credentials for mobile-api

The Flask app (`mobile-api/app.py`) reads these environment variables:

| Variable | Default | Purpose |
|---|---|---|
| `MYSQL_HOST` | `127.0.0.1` | MySQL/MariaDB host |
| `MYSQL_PORT` | `3307` | TCP port |
| `MYSQL_DATABASE` | `layrate` | Database name |
| `MYSQL_USER` | `root` | Username |
| `MYSQL_PASSWORD` | `root` | Password |

If the Pi uses different values than the defaults, override them via a systemd
drop-in (which persists across deploy updates to the unit file):

```bash
sudo systemctl edit layrate-api
```

Add lines like:

```
[Service]
Environment=MYSQL_HOST=127.0.0.1
Environment=MYSQL_PORT=3306
Environment=MYSQL_USER=layratepi
Environment=MYSQL_PASSWORD=your-password
```

Then restart:

```bash
sudo systemctl restart layrate-api
```

---

## 8. Web server (nginx)

Example nginx site config (`/etc/nginx/sites-available/layrate`):

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/layrate/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable and restart:

```bash
sudo ln -s /etc/nginx/sites-available/layrate /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default   # if present
sudo nginx -t && sudo systemctl restart nginx
```

---

## 9. GitHub Actions self-hosted runner

Install and register the runner on the Pi following the
[GitHub docs](https://docs.github.com/en/actions/hosting-your-own-runners/managing-self-hosted-runners/adding-self-hosted-runners).
The deploy workflow targets the `self-hosted` label.

---

## 10. Verify the automated deploy

Push to `main` and watch the deploy action in the GitHub UI. After a successful
run, visit `http://<pi-ip>` in a browser and confirm the dashboard loads.
