# Installing WB Listora Pro

## What it does

WB Listora Pro is a premium add-on for WB Listora (Free). It adds Google Maps, a credit-based payment system, analytics, multi-criteria reviews, lead forms, and more. This guide covers installing Pro and verifying it is active.

## Requirements

- WB Listora (Free) version 1.0.0 or higher, installed and activated.
- WordPress 6.4 or higher.
- PHP 7.4 or higher.
- A valid WB Listora Pro license key (from [wblistora.com](https://wblistora.com)).

## How to install

### For site owners (admin steps)

1. Log in to [wblistora.com](https://wblistora.com), go to your account, and download the latest `wb-listora-pro.zip`.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.
5. Pro activates and you'll see a notice asking you to enter your license key.

### Verify activation

1. Go to **Listora → Settings → License**.
2. Enter your license key and click **Activate License**.
3. A green **"License activated"** notice confirms success.
4. Under **Plugins**, confirm both **WB Listora** and **WB Listora Pro** are listed as active.
5. Go to **Listora → Settings** — you should see a **Pro** tab in the settings navigation.

## Tips

- Do not delete WB Listora (Free) after installing Pro — Pro is an add-on, not a replacement.
- Local development environments (Local by Flywheel, DevKinsta, etc.) skip remote license validation automatically. You'll see a "local mode" notice instead of an error.
- If you manage multiple sites, each site requires a separate license activation. Deactivate on one site before activating on another if your license has a site limit.
- Auto-updates: once your license is active, Pro updates appear in **Dashboard → Updates** alongside your other plugins.

## Common issues

| Symptom | Fix |
|---------|-----|
| Pro menu items not appearing | Ensure WB Listora (Free) is active — Pro requires it |
| "Invalid license key" error | Double-check the key from your account at wblistora.com; copy-paste rather than typing |
| ZIP upload fails | Check `upload_max_filesize` in your PHP settings; increase to at least 32MB |
| Pro settings tab missing | Deactivate and reactivate WB Listora Pro |

## Related

- [License Management](pro-license.md)
- [Credits and Plans](../features/credits-and-plans.md)
