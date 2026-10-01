# Installing on Hostinger (File Manager only — no Composer, no SSH)

The package already contains everything (`vendor/` included). You only upload, extract and click through the
setup wizard. Allow about 15 minutes.

## What you need first (in hPanel)

1. **PHP 8.3 or newer** — Websites → *Manage* → **PHP Configuration** → choose PHP **8.3** (or newer).
   Leave the standard extensions ticked (`mbstring`, `pdo_mysql`, `gd`, `fileinfo`, `openssl`, `xml`, `zip`).
2. **A MySQL database** — Databases → **MySQL Databases** → create a database, a user and a password.
   Write down all three. Hostinger adds a prefix, e.g. `u123456789_foundation` and `u123456789_fduser`.
   The database host is **`localhost`**.
   Use a brand-new, empty database dedicated to this system — never one that another app uses.
3. **SSL on** — Security → SSL, and turn on *Force HTTPS*.

## Use a subdomain if your domain already hosts another site

If `yourdomain.com` already runs another application (for example MuslimEdu), **do not install Foundation into
that site's `public_html`** and do not change that site's PHP version. Create a subdomain such as
`foundation.yourdomain.com` in hPanel, set **PHP 8.3 on that subdomain only**, and give it its own empty database.
Note the folder Hostinger assigns to the subdomain (often `public_html/foundation`).

- Put the **contents of the package's `public_html` folder** into the subdomain's folder.
- Put `foundation_app` **outside every web folder** — in the domain folder that contains `public_html`.
- If the subdomain's folder is `public_html/foundation`, open its `index.php` and change `../foundation_app` to
  `../../foundation_app` (3 places). If the folder sits directly beside `foundation_app`, change nothing.

### No subdomain: install in a folder of the existing site (e.g. `yourdomain.com/acr/`)

This works, with one rule: **`foundation_app` stays outside `public_html`; only the public files go in the folder.**

```
domains/yourdomain.com/
├── foundation_app/          ← the private app (NOT inside public_html)
└── public_html/
    ├── ...your other site...
    └── acr/                 ← contents of the package's public_html folder
```

1. In File Manager tap the home icon so you can see the folder that **contains** `public_html`. Put `foundation_app` there.
2. Create the folder `acr` inside `public_html`, and put the package's public files in it (`index.php`,
   `.htaccess`, `css/`, `favicon.ico`, `robots.txt`).
3. Edit `public_html/acr/index.php`: change `../foundation_app` to `../../foundation_app` (3 places).
4. Open `https://yourdomain.com/acr/`. In the wizard, set **Application URL** to `https://yourdomain.com/acr`.

Things to know about sharing a domain: PHP version is set **per website, not per folder**, so the whole domain
(including your other application) runs on the PHP version you choose, and Foundation needs 8.3 or newer — make
sure the other application works on it. The installer scopes Foundation's cookies to its own folder so the two
apps do not interfere. Use a separate database.

The installer checks this for you: if `foundation_app` is somewhere the web server can serve, the
requirements step fails with "Application folder is outside the public web folder" and nothing is installed.

## Upload

1. hPanel → **File Manager**. Open the folder that **contains** `public_html`
   (usually `domains/yourdomain.com/`). You should see `public_html` in it.
2. Click **Upload** and choose `foundation-hostinger.zip`. When it finishes, right-click it → **Extract** here.
3. You should now see **two** folders side by side:
   ```
   domains/yourdomain.com/
   ├── foundation_app/   ← the application (private)
   └── public_html/      ← the website files
   ```
   `foundation_app` must sit **next to** `public_html`, never inside it.
4. If `public_html` contains Hostinger's placeholder `default.php`, delete it.
5. In File Manager settings, turn on **Show hidden files** (you need to see `.htaccess`).
6. Delete the uploaded zip.

> Using a subdomain or an add-on domain? The same rule applies: `foundation_app` next to that site's
> `public_html`. If the folders have other names, tell me and I will adjust the package.

## Run the setup wizard

1. Open `https://yourdomain.com` — you are sent to the installer.
2. It asks for a **setup token**. In File Manager open
   `foundation_app/storage/app/install/token`, copy its contents, paste it in the browser.
   (This proves you control the server; the file is deleted when setup finishes.)
3. Follow the steps: Requirements → Database → System → Foundation → Administrator → Device → Install.
   - **Database host:** `localhost` · **port:** `3306` · the database name, user and password from hPanel.
   - Press **Test Database Connection** first; it must say successful before you can continue.
   - **Administrator:** choose your own strong password (there is no default account).
4. When you see **Installation Complete**, the installer is locked for good.

## Check it is safe

- `https://yourdomain.com/foundation_app/.env` must show **404/Not Found**. If it shows anything else,
  `foundation_app` is in the wrong place — move it next to `public_html`.
- `https://yourdomain.com/install` must say *"This system is already installed."*
- Keep a copy of `foundation_app/.env` and `foundation_app/storage/app/install/installed.lock` somewhere safe.
  If either is lost the system stops and shows a recovery screen (it never reinstalls over your data).

## Updating to a new version

Two small zips, no renaming and no copying. They contain only code folders (never `storage`, `.env` or `index.php`),
so your installation, settings and data are not touched.

1. `update-foundation_app.zip` → open the `foundation_app` folder, upload it there, **Extract** here, answer yes to overwrite.
2. `update-web.zip` → open your web folder (e.g. `public_html/acr`), upload it there, **Extract** here, answer yes to overwrite.
3. Refresh the site (pull down on a phone, or Ctrl+F5).

Only when a release says the PHP libraries changed will it also ship a new `vendor` folder.

## Forgot the Super Admin password (or email)

No command line needed. `scripts/reset-admin.template.php` is a one-time recovery page: replace `__KEY__` with a long random
string, save it as `reset-admin.php` in the web folder (e.g. `public_html/acr`), and open
`https://yourdomain/acr/reset-admin.php?k=<that string>`. It lists the Super Admin accounts, lets you choose a new password,
signs out other sessions, and deletes itself. Without the key it answers 404. Delete it manually if it is still there afterwards.

## Troubleshooting

| You see | Do this |
|---|---|
| 500 error immediately | PHP version is below 8.3 — change it in hPanel. |
| Requirements page shows a red ✕ | It says what is missing; enable the extension in hPanel → PHP Configuration, or set folders to permission **775**. |
| "Cannot write the .env file" | Set `foundation_app` folder permission to **755/775** (right-click → Permissions). |
| Database test fails | Re-check name/user/password (with Hostinger's prefix); host must be `localhost`. |
| Installer shows "previous attempt did not finish" | Nothing was deleted. Press **Resume Setup** and run it again. |

## What works right now

This package contains the **server side** (installer, accounts, roles, foundation profile, locations,
audit log, devices and the sync API). The screens people will use day to day (login page, dashboard, offline
app) are the next build phase — after installing you will see a "System is running" page.
