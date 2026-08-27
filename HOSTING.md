# Hosting the PHP Edition Somewhere Other Than Your Own Computer

The whole point of the PHP version is that it's easy to host: no process
to keep running, no build step, and PHP itself is supported almost
everywhere. Still, a few things are worth checking before you trust it
with real farm records.

**Read this first, whichever host you use:**

1. **Log in is required from the first visit.** Do the "First time
   setup" step immediately after deploying, before telling anyone the
   URL.
2. **`data/` must be on real, persistent disk storage.** On ordinary
   shared/VPS hosting this is automatic — your files just sit on disk.
   The one place this can bite you is container-based "deploy from Git"
   platforms that don't normally run PHP shared hosts this way; if
   you're using one of those, make sure a persistent volume is mounted
   at `data/`, or every deploy will wipe your farm records (see the
   note in the Node.js edition's HOSTING.md if you're evaluating both —
   the same warning applies).
3. **Use HTTPS.** The session cookie is marked `Secure` automatically
   whenever PHP can tell the connection is HTTPS (directly, or via an
   `X-Forwarded-Proto: https` header from a reverse proxy). Nearly every
   host below offers free HTTPS (Let's Encrypt/AutoSSL) — turn it on.
4. **One dedicated (sub)domain, not a sub-path.** The app expects to own
   the whole domain/subdomain it's served from (e.g.
   `farm.yourdomain.com`), not to be mounted under a path like
   `yourdomain.com/farm/`. Every host below supports a free subdomain.

---

## Option A: cPanel / Plesk shared hosting (the common case)

This is what the PHP version was built for — the cheapest, most widely
available kind of hosting.

1. In cPanel, check **Select PHP Version** (sometimes called "MultiPHP
   Manager") and set it to 7.4 or newer for the domain/subdomain you're
   using.
2. Create the subdomain you want (e.g. `farm.yourdomain.com`) if you
   haven't already, via **Subdomains** in cPanel. Note the document root
   it creates (often `public_html/farm` or similar).
3. Upload the contents of this folder into that document root — File
   Manager's upload+extract works well for a zip file, or use FTP/SFTP.
4. Make sure **AutoSSL** (or your host's equivalent) has issued a
   certificate for the subdomain, and that "Force HTTPS Redirect" is on.
5. Visit the subdomain. You should land on the "First time setup"
   screen. Create your login right away.
6. **Persistence check**: cPanel/Plesk shared hosting stores your files
   on normal, persistent disk — one of the more reliable options for
   keeping your JSON data safe. Still, download a full backup from the
   app's *Backup & Restore* page every so often, and consider whether
   your host offers automatic account backups (many do, as a paid add-on
   or included feature).

If mod_rewrite ever seems not to be working (see Troubleshooting in
README.md), it's almost always `AllowOverride` being restricted for your
account — a quick message to your host's support usually sorts it out,
since shared-hosting `.htaccess` support is expected to just work.

## Option B: A VPS with Apache (DigitalOcean, Linode, Hetzner, etc.)

1. Provision a small Linux VPS and install PHP 7.4+ and Apache with
   `mod_rewrite` (e.g. on Ubuntu/Debian: `apt install apache2 php
   libapache2-mod-php` then `a2enmod rewrite`).
2. Copy this folder into a directory Apache serves, e.g.
   `/var/www/farm-app/`.
3. Point a virtual host at it with `AllowOverride All` so `.htaccess`
   is respected:
   ```apache
   <VirtualHost *:80>
       ServerName farm.yourdomain.com
       DocumentRoot /var/www/farm-app
       <Directory /var/www/farm-app>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
4. Get a free certificate with `certbot --apache -d farm.yourdomain.com`
   (Let's Encrypt) and let it set up the HTTPS redirect for you.
5. Make sure the `data/` folder is writable by the user Apache runs as
   (often `www-data`): `chown -R www-data:www-data /var/www/farm-app/data`.
6. Visit the domain and create your login.

## Option C: A VPS with nginx + PHP-FPM

nginx doesn't read `.htaccess` files, so the routing rule needs to be
written directly into the server block instead:

```nginx
server {
    listen 443 ssl http2;
    server_name farm.yourdomain.com;
    root /var/www/farm-app;
    index index.php;

    # Block direct access to data/ and lib/ — mirrors the .htaccess
    # rules in the Apache version.
    location ~ ^/(data|lib)/ {
        deny all;
        return 403;
    }

    # Route /api/<anything> to api.php with the remainder as __path,
    # same as the Apache RewriteRule.
    location /api/ {
        rewrite ^/api/(.*)$ /api.php?__path=$1 last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # match your PHP-FPM version
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

Adjust the `fastcgi_pass` socket path to match your PHP-FPM version
(check with `php -v` and look in `/var/run/php/`). As with Option B, get
HTTPS via `certbot --nginx -d farm.yourdomain.com` and make sure
`data/` is writable by the PHP-FPM user (often `www-data`).

## Checking it worked

However you deploy it, a quick sanity check:

- Visiting the site for the very first time shows the **setup** screen,
  not the dashboard.
- After creating your login, refreshing the page keeps you signed in.
- Try fetching `https://yourdomain/data/auth.json` directly in a
  browser — it **must** return a 403 Forbidden (never the file
  contents). If it doesn't, your `.htaccess` files aren't being applied
  (Apache) or your nginx `location` block for `data/` is missing —
  fix this before entering any real data. This is the single most
  important check on this whole page.
- Restarting PHP-FPM/Apache (or just waiting — shared hosting often
  recycles PHP processes on its own) and then refreshing the page
  should still show the dashboard without asking you to log in again,
  confirming sessions are persisting correctly.

## Resetting your login

Delete `data/auth.json` (via FTP/File Manager or SSH) — the next visit
will show the setup screen so you can create a fresh login. This never
touches your animal records.
