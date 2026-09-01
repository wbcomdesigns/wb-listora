# App Sign-In

> **Availability:** Free + Pro. Password sign-in for apps is **Free**. Whether a connected app is enabled at all is a Pro licensing gate - see [Activating Pro](../getting-started/activating-pro.md).

Members can sign in to a connected mobile app by typing the WordPress password they already have, instead of being sent to a browser to approve an application password by hand.

## What it is

WordPress's built-in way for an app to get access is the Application Passwords screen: the member opens their profile in a browser, generates a credential, and copies it into the app. It is secure and it works, and almost nobody completes it.

This feature keeps the same underlying credential and removes the detour. The member types their normal username and password in the app; the site verifies them and hands back an application password scoped to that install. From that point the app holds a revocable credential and the member's real password is not stored anywhere.

The important part is what did **not** change. The app still ends up holding an ordinary WordPress application password. It appears in the member's profile, it can be revoked there, and revoking it signs that install out. Listora did not invent a session format.

## How you use it

### As a site owner - turn it on or off

**Listora > Settings > Advanced > "Let members sign in to the app with their WordPress password."** On by default.

Turning it off does not break existing installs - credentials already issued keep working. It removes the password route for new sign-ins, and the app is told to fall back to the browser approval flow.

### As a member

Open the app, enter your site address, then your usual username and password. Signing in again from the same device **replaces that device's credential** rather than adding another, so the application-password list in your profile does not fill up with an entry per sign-in.

### As a developer

One route: `POST /listora/v1/auth/app-password`. It trades a login for an application password and answers `no-store`, so the response is never cached.

The app does not have to guess whether this route is available. The public app-config payload tells the client which sign-in doors the site offers **before it draws the screen**, so it never presents a path the site will refuse. See [REST API](../developer-guide/rest-api.md).

## Security

This route is the front door to every account on the site, so it is worth stating plainly what guards it:

- **TLS required.** The route refuses to trade a credential over a plaintext connection.
- **Throttled per address and per account**, and the throttle runs *before* any credential is read. Only wrong passwords count toward the limit, so a member who mistypes a username is not locked out by someone else's failures.
- **Uniform failure response.** A wrong username and a wrong password answer identically, so the endpoint cannot be used to discover which accounts exist.
- **Two-factor is never bypassed.** On a site running 2FA, the app is handed back to the browser flow so the second factor can complete. Password sign-in does not become a way around it.

## Settings & options

| Setting | Where | Default |
|---|---|---|
| App sign-in with WordPress password | Settings > Advanced | On |

Site owners who want the switch driven by policy rather than the UI can filter `wb_listora_app_password_login_enabled`.

## Related

- [Activating Pro](../getting-started/activating-pro.md) - the licence gate on connected apps
- [REST API](../developer-guide/rest-api.md) - the app-config payload and the auth route
- [Rate Limiting](rate-limiting.md) - the throttling layer this route uses
- [Advanced Settings](../settings/advanced-settings.md)
