# Farm Flock Manager (PHP Edition)

A simple, self-contained record book for a small farm's sheep flock and
milking cows — sheep breeding & lambing, dairy cow calving, medicine and
withdrawal tracking, milk yield and milk testing, weights, reports, and
backup/restore — with a single-user login. This is the PHP version of
the app: same features, same look, but built to run on ordinary PHP
shared hosting instead of Node.js.

## What this is

- A plain PHP backend — **no framework, no Composer packages, nothing to
  `composer install`.** Everything it uses (JSON handling, sessions,
  password hashing) ships with a default PHP install.
- All data lives in plain **JSON files** in the `data/` folder. No
  database server, no phpMyAdmin, nothing to set up.
- A **single login** (username + password) protects the whole app. The
  first visit walks you through creating it.
- Works on essentially any PHP hosting: cheap shared hosting (cPanel/
  Plesk), a VPS, or your own computer with PHP installed.
- Styled with **Pico CSS** (green theme) and **Tabler Icons**, both
  loaded from a CDN — see "Internet connection for styling" below.

## Requirements

- **PHP 7.4 or newer** (PHP 8.x is fine too). Almost every hosting
  provider's PHP is well within this range — if you're not sure, your
  host's control panel usually shows the PHP version, or you can ask
  their support.
- **Apache with `mod_rewrite`**, and `AllowOverride All` (or at least
  `AllowOverride FileInfo`) enabled for the folder you deploy into, so
  the included `.htaccess` file can route `/api/...` requests. This is
  the default setup on virtually all shared hosting — you don't need to
  do anything special, just don't remove the `.htaccess` file. If you're
  deploying to an nginx server instead (common on VPS setups), see the
  nginx snippet in **HOSTING.md**.

That's it — no Node, no npm, no build step.

### Internet connection for styling

The visual design (Pico CSS, and the Tabler icon set used throughout the
nav and buttons) loads from a CDN (jsdelivr) rather than being bundled
in the app — this is the genuine, official CSS/font files rather than a
local approximation, and it means one less thing to update by hand if
either project ships a fix. **All data entry, records, and calculations
work completely offline** — only the visual polish depends on the
device viewing the page having internet access. If a device is ever
fully offline, the app still functions, just with unstyled HTML instead
of the usual look. If you'd rather have zero external dependencies even
for styling (e.g. for a farm with unreliable internet), let me know and
I can put together a version with the CSS/icons downloaded and served
locally instead.

## Installation (typical shared hosting / cPanel)

1. Upload the entire contents of this folder into your site's document
   root (commonly `public_html`, or a subfolder if you're using a
   subdomain like `farm.yourdomain.com`) — via the File Manager, FTP, or
   SFTP. Keep the folder structure as-is (don't flatten it).
2. Make sure `data/` is writable by PHP. Most shared hosts already allow
   this by default for anything under your account; if you get a
   "permission denied" error later, set the `data` folder's permissions
   to `755` (or `775` if `755` isn't enough — check with your host if
   unsure).
3. Visit your site in a browser. You should land on a **"First time
   setup"** screen — choose a username and password (at least 8
   characters). This is the only login for the app; there's just one
   account, for you.
4. That's it — you're in. See **HOSTING.md** for platform-specific notes
   (cPanel, a VPS, etc.) if anything above doesn't quite match your
   setup.

On every visit after that, you'll be asked to sign in. There's no
"forgot password" screen — if you ever need to reset it, delete
`data/auth.json` (via FTP/File Manager) and visit the site again; you'll
get the setup screen back, with your animal records untouched.

### Loading sample data (optional)

To explore the app with a realistic sample flock and herd already filled
in, run the seed script once:

- **If your host gives you SSH/terminal access:**
  ```
  cd /path/to/the/app
  php seed.php
  ```
- **If you only have FTP/File Manager access (no shell):** most cPanel-
  style hosts have a **Cron Jobs** feature that can run a one-off
  command. Create a cron job with the command
  `php /home/yourusername/public_html/seed.php` (adjust the path to
  match where you uploaded the app), let it run once, then delete the
  cron job again.
- **Don't have either?** That's fine — skip this step entirely. The app
  creates its data files empty on first use either way, and you can just
  start entering your own animals from the Dashboard.

Re-running `seed.php` at any time **replaces all current farm records**
with a fresh sample set (it never touches your login), so only run it
again if you deliberately want to reset to sample data.

## Folder structure

```
farm-app-php/
  api.php              Front controller — handles every /api/* request
  index.php             The app shell (gated: redirects to setup/login if needed)
  login.php              Sign-in screen
  setup.php               First-time account creation screen
  .htaccess                 Apache routing + security rules — don't delete
  config.example.php         Optional: copy to config.php to move data/ elsewhere
  lib/
    db.php                    JSON read/write with file locking + backups
    auth.php                   Login: password hashing, sessions, lockout
    helpers.php                 All calculated logic (withdrawal dates, milk
                                 averages, alerts, reports, CSV export)
    .htaccess                    Blocks direct web access to this folder
  seed.php               Generates sample data — run with `php seed.php`
  data/                 Your actual farm data lives here (created on first run)
    .htaccess              Blocks ALL direct web access to this folder
    auth.json                Your login (username + a salted, hashed
                              password — never a plain-text password)
    sessions/                  PHP's session files
    lockout.json                 Failed-login tracking (auto-cleans itself)
    animals.json                   Sheep + cow records
    breeding.json                    Tupping/service, lambing/calving records
    medicine.json                      Treatment & withdrawal records
    weights.json                         Weight records
    milk.json                              Milking session records
    milktests.json                           Milk test results
    settings.json                              App preferences (e.g. dark mode)
    backups/                                     Automatic rolling backups (last 20 per file)
  css/style.css
  js/api.js, components.js, views.js, app.js
```

`auth.json`, `sessions/`, and `lockout.json` are deliberately kept
separate from your farm records — they're never included in the
"Download Full Backup" export or touched by "Restore Backup", so
restoring an old farm-data backup can never accidentally change your
login.

**Security note:** the `data/` and `lib/` folders each have their own
`.htaccess` file that blocks all direct web access (on top of the root
`.htaccess` also blocking them) — this is what keeps your password hash
and JSON records from being downloadable by URL. Don't delete these
files. If you want an extra layer of protection, see `config.example.php`
for moving `data/` entirely outside your web root, on hosts that support
it.

## Running on a shared host or the internet

See **[HOSTING.md](HOSTING.md)** for step-by-step notes on cPanel/Plesk
hosting, a plain VPS (Apache or nginx), and how to check your data is
actually persisting across restarts.

## Backup and restore

- **Automatic**: every save first copies the affected file into
  `data/backups/` before writing, keeping the last 20 versions of each
  file.
- **Manual full backup**: *Backup & Restore* in the app → "Download Full
  Backup" downloads one JSON file with everything. Keep copies somewhere
  safe (your computer, a cloud drive) — download a fresh one every so
  often.
- **Restore**: same page, choose a previously downloaded backup file and
  click "Restore Backup". This **replaces all current data**, so use it
  to recover from a mistake or move to a new host, not casually.
- **CSV exports**: each record type (animals, breeding, medicine,
  weights, milk, milk tests) can be exported separately as CSV.

We'd still recommend downloading a copy of the whole `data/` folder via
FTP every so often, in addition to using the in-app backup — belt and
braces.

## A note on record-keeping

This program is a digital record book — it does the arithmetic
(withdrawal end dates, rolling milk averages, days-since-service) and
keeps everything organized and searchable, but it does not replace your
own judgement or any legal/regulatory record-keeping obligations for
medicine use, movements, or food safety that apply in your area. Always
double check withdrawal periods against the product label/datasheet.

## Troubleshooting

- **Blank page or "500 Internal Server Error"** — almost always a PHP
  version mismatch or a missing extension. Check your host's PHP version
  is 7.4+; the app doesn't need any extensions beyond what ships with
  PHP by default (`json`, `session`, `hash`).
- **404 errors on every page, or the app never gets past a blank screen**
  — `mod_rewrite` probably isn't enabled, or `.htaccess` files are being
  ignored (`AllowOverride None`). Ask your host to confirm both are on
  for your folder — this is standard on shared hosting, so it's usually
  a quick fix.
- **"Not found" errors specifically when saving/loading data, but pages
  load fine** — the `.htaccess` routing rule for `/api/...` isn't being
  applied; same cause as above.
- **Setup screen keeps reappearing after you create a login** — the
  `data/` folder likely isn't writable by PHP. Set its permissions to
  `755` via FTP/File Manager (or ask your host).
- **I want to wipe everything and start over** — delete everything
  inside the `data/` folder (but keep the folder and its `.htaccess`
  file) and revisit the site; you'll get the setup screen again.
