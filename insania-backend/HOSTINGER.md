# Install on Hostinger with File Manager only (no terminal)

You need: the zip `insania-hostinger-package.zip`, your hPanel login, and about 15 minutes.

## What goes where

The zip holds two folders. They go **side by side**, in the folder that contains `public_html`:

```
(your hosting folder)
├── insania_app/        ← the private program (never inside public_html)
└── public_html/
    └── Beta/server/    ← the small public door to the API
```

The API address will be `https://alinsania.com/Beta/server/api`.

## Step 1. Make an empty database
hPanel → **Databases → MySQL Databases**. Create a database and a user, give the user all privileges. Write down: database name, user name, password. (Host is `localhost`.)

## Step 2. Check PHP
hPanel → **Advanced → PHP Configuration**: choose **PHP 8.1 or newer** (8.3 is best). Make sure these extensions are ticked: mbstring, openssl, tokenizer, xml, ctype, fileinfo, pdo_mysql.

## Step 3. Upload
1. hPanel → **File Manager**. Open the folder that contains `public_html`.
2. Upload `insania-hostinger-package.zip` there, right-click → **Extract**. You now have `insania_app` next to `public_html`, and `Beta/server` inside `public_html`.
3. If `public_html/Beta` already exists, extract anyway; only `Beta/server` is added.
4. Delete the zip.

## Step 4. Install
1. In File Manager open `insania_app/storage/app/install/` and open the file **token**. Copy the text inside. (If it is missing, open the install page below once; it creates it.)
2. In your browser go to `https://alinsania.com/Beta/server/install`.
3. Check the server list is all green ✓. Fill in the form: the setup code, foundation name, the API address (`https://alinsania.com/Beta/server`), the database details, and your System Admin name, email and password.
4. Press **Install**. When you see "Installed ✓", you are done. The setup code is deleted and the page switches itself off.

If it stops part-way, fix what it says and press Install again; it continues.

## Step 5. Check
Open `https://alinsania.com/Beta/server/api/ping`. You should see `{"success":true,"data":{"installed":true}}`.

## Partner keys (Dar Al Ber)
Sign in to the API as the System Admin and use `POST /api/system/api-keys` (name = "Dar Al Ber"). The key is shown once. List with `GET /api/system/api-keys`, revoke with `DELETE /api/system/api-keys/{id}`.

## Upgrading later
1. Upload the new files over `insania_app` (File Manager → Upload, overwrite). Do **not** delete `.env` or the `storage` folder.
2. Open `https://alinsania.com/Beta/server/upgrade`, paste the code from `insania_app/storage/app/install/token` (the page creates it), press **Run upgrade**.

## If something goes wrong
- **"NOT_INSTALLED" JSON**: you have not finished Step 4.
- **500 error**: set permissions of `insania_app/storage` and `insania_app/bootstrap/cache` to 755 or 775 (right-click → Permissions, tick "recursive").
- **Redirects to /Beta/**: that is your site's catch-all rule; make sure `Beta/server/.htaccess` was extracted.
- **Forgot to keep the password**: ask the System Admin; or delete `.env` and `installed.lock`, empty the database and install again (this erases data).

## Safety
Never put `insania_app` inside `public_html`. Keep `.env` private. Keep the demo seeder off (it refuses to run in production anyway).
