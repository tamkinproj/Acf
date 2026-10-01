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
