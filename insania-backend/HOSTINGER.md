# Install on Hostinger with File Manager only (no terminal)

You need: the zip `insania-hostinger-package.zip`, your hPanel login, and about 15 minutes.

## What goes where

The zip holds two folders. They go **side by side**, in the folder that contains `public_html`:

```
(your hosting folder)
├── insania_app/        ← the private program (never inside public_html)
└── public_html/
    └── server/    ← the small public door to the API
```

The API address will be `https://alinsania.com/server/api`.

## Fresh install with two zips (Hostinger File Manager always extracts into a new folder)
1. Upload `1-insania_app.zip` to the folder that contains `public_html`. Extract it; folder name **insania_app**; destination: that same folder.
2. Upload `2-website.zip` there too. Extract it; folder name **website**. Open `website`, select everything inside (turn on hidden files to see `.htaccess`), **Move** it into `public_html`. Delete the empty `website` folder and both zips.
3. Continue with "Step 4. Install" below.

## Who creates whom
1. **System Admin**: created by the installer (Step 4). Runs the system (health, settings, updates, partner keys). Cannot see children, money, accounts or the website.
2. **President Admin**: created by the System Admin from the first-login checklist. Runs every office feature and the public website.
3. **Everyone else** (Security Admin, Admin Maintenance, staff by department, family accounts): created by the President Admin in the Accounts screen.

## Step 1. Make an empty database
hPanel → **Databases → MySQL Databases**. Create a database and a user, give the user all privileges. Write down: database name, user name, password. (Host is `localhost`.)

## Step 2. Check PHP
hPanel → **Advanced → PHP Configuration**: choose **PHP 8.1 or newer** (8.3 is best). Make sure these extensions are ticked: mbstring, openssl, tokenizer, xml, ctype, fileinfo, pdo_mysql.

## Step 3. Upload
1. hPanel → **File Manager**. Open the folder that contains `public_html`.
2. Upload `insania-hostinger-package.zip` there, right-click → **Extract**. You now have `insania_app` next to `public_html`, and `server` inside `public_html`.
3. Your website itself (landing page and staff app) comes from the separate `alinsania-site-package.zip`: extract that inside `public_html`. The staff app is then at `alinsania.com/app/`. Old `/Beta/...` links redirect to the new address.
4. Delete the zip.

## Step 4. Install
1. In File Manager open `insania_app/storage/app/install/` and open the file **token**. Copy the text inside. (If it is missing, open the install page below once; it creates it.)
2. In your browser go to `https://alinsania.com/server/install`.
3. Check the server list is all green ✓. Fill in the form: the setup code, foundation name, the API address (`https://alinsania.com/server`), the database details, and your System Admin name, email and password.
4. Press **Install**. When you see "Installed ✓", you are done. The setup code is deleted and the page switches itself off.

If it stops part-way, fix what it says and press Install again; it continues.

## Step 5. Check
Open `https://alinsania.com/server/api/ping`. You should see `{"success":true,"data":{"installed":true}}`.

## Partner keys (Dar Al Ber)
Sign in to the API as the System Admin and use `POST /api/system/api-keys` (name = "Dar Al Ber"). The key is shown once. List with `GET /api/system/api-keys`, revoke with `DELETE /api/system/api-keys/{id}`.

## Updates from inside the app (signed patches)
After this is installed you no longer need File Manager for updates.
1. Whoever maintains the system sends you a patch `something.zip`.
2. **Signing (on your own computer or phone, never on the server).** Open `patch-signer.html` in a browser, choose your key file `insania-signing-key.json`, choose the patch zip, press **Create one-file update**. Keep the key file private and offline: whoever has it can change your server. The page shows a key code; it must match the "Trusted signing key" on the Updates page.
3. In the app sign in as System Admin (only the System Admin has this), open **Updates**, choose the one-file update (`something.update.zip`) and press **Upload and update**. It checks, backs up, updates by itself, and shows **Undo** in the history. (Prefer to read the summary first? Choose the zip and the `.sig` file and press **Check patch**, then **Apply update**.)
4. Before changing anything the system copies every file it will replace into `insania_app/storage/app/updates/backups/`. If anything fails it puts everything back by itself. Afterwards **Undo this update** brings the previous version back (files and the update's own database changes).
5. A patch with a wrong or missing signature, a PHP mistake, an unsafe path or the wrong base version is refused before anything is written.

What the backup does not hold: your records and uploaded documents. Keep a separate data backup.
If the app itself will not open, use `https://alinsania.com/server/upgrade`: same upload, check, apply and undo, protected by the setup code in `insania_app/storage/app/install/token`.

## Upgrading later (without a signed patch)
1. Upload the new files over `insania_app` (File Manager → Upload, overwrite). Do **not** delete `.env` or the `storage` folder.
2. Open `https://alinsania.com/server/upgrade`, paste the code from `insania_app/storage/app/install/token` (the page creates it), press **Run upgrade**.

## If something goes wrong
- **The installer page came back ("This database already has tables") after you re-uploaded the files.** Your data is safe. Re-uploading removed the small "installed" marker (and maybe the settings file). Open `https://alinsania.com/server/install`: if the settings file is still there it fixes itself and the page shows "Already installed". If not, open the box **"Already installed before? Reconnect instead"**, fill in the setup code, the API address and your database details, press **Reconnect**. It restores the settings and changes no data; your accounts keep working. **To avoid it:** when updating, always extract the zip over the existing folders and choose "replace files". Never delete the `insania_app` folder first.
The server page explains most problems itself (open `https://alinsania.com/server/`):
- **"Move the insania_app folder"**: it is inside public_html; move it one level up, next to public_html.
- **"The insania_app folder was not found"**: extract the zip in the folder that *contains* public_html.
- **"Some files are missing"**: the extraction stopped part-way; extract the zip again and replace files.
- **"Folders cannot be written"**: set permissions as described below.
- **"PHP is too old"**: choose PHP 8.3 in hPanel.
- **"The server stopped while starting"**: open `insania_app/storage/logs/startup-error.log` and send its last line.
- **"NOT_INSTALLED" JSON**: you have not finished Step 4.
- **500 error**: set permissions of `insania_app/storage` and `insania_app/bootstrap/cache` to 755 or 775 (right-click → Permissions, tick "recursive").
- **Redirects to the home page**: that is your site's catch-all rule; make sure `server/.htaccess` was extracted.
- **Forgot to keep the password**: ask the System Admin; or delete `.env` and `installed.lock`, empty the database and install again (this erases data).

## Safety
Never put `insania_app` inside `public_html`. Keep `.env` private. Keep the demo seeder off (it refuses to run in production anyway).
