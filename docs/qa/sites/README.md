# QA site profiles

One JSON file per local QA site: `docs/qa/sites/<site-host>.json`. Copy `example.json`, fill it in, and select it with `QA_SITE=<site-host>` (default: the host of `base_url` in `docs/qa/qa-config.json`). Free and Pro journeys share one profile because the pair shares one site.

Journeys never hardcode a host, login, slug or count. They use the placeholders below, which resolve from the profile.

## Placeholder vocabulary

Brace placeholders are fixed per site and come from the profile. Anything a step computes or creates is a shell-style CAPS variable instead (`$LISTING_ID`, `$BALANCE`, `$COST`, `$TODAY`, `$HTTP_CODE`, `${PREFIX}`), set in the step that first produces it. Never write a computed value as a `{brace}` placeholder.

### Personas (`personas.<name>.login`)

| Placeholder | Meaning | Resolves from |
|---|---|---|
| `{owner}` | The member who owns the fixture listings (subscriber). | `personas.owner` |
| `{other}` | A second member at the SAME role as `{owner}`; used for ownership and permission checks. | `personas.other` |
| `{unnamed}` | A member whose display name equals the login, so the public name is empty. | `personas.unnamed` |
| `{contributor}` | A user with the WordPress Contributor role. | `personas.contributor` |
| `{moderator}` | A user with the Listora Moderator role (Pro). | `personas.moderator` |
| `{nocap}` | A member whose submit or review capability is removed in the step that uses it. | `personas.nocap` |
| `{editor}` | A user with the Editor role. | `personas.editor` |
| `{admin}` | The administrator; the control rung, never the only one. | `personas.admin` |
| `{guest}` | A logged-out visitor. | `personas.guest` (no login) |

### Content (`content.<name>`)

| Placeholder | Meaning | Resolves from |
|---|---|---|
| `{cat}` | Slug of a category that has published listings. | `content.cat` |
| `{loc}` | Slug of a location that has published listings (nested path allowed, for example `us/ny`). | `content.loc` |
| `{tag}` | Slug of a listing tag. | `content.tag` |
| `{feature}` | Slug of a listing feature. | `content.feature` |
| `{type}` | Slug of a listing type. | `content.type` |
| `{type_b}` | Slug of a second listing type. | `content.type_b` |
| `{listing}` | Slug of one published listing owned by `{owner}`. | `content.listing` |

### Pages (`pages.<name>`)

Relative URLs with leading and trailing slash, appended directly to `$SITE_URL` (for example `$SITE_URL{directory}`). Read them from the site, do not guess: `wp eval 'echo \WBListora\Core\Page_Registry::get_id( "<key>" );'`.

| Placeholder | Meaning | Resolves from |
|---|---|---|
| `{directory}` | The Directory (search and grid) page. | `pages.directory` |
| `{dashboard}` | The member dashboard page. | `pages.dashboard` |
| `{add_listing}` | The Add Listing (submission) page. | `pages.add_listing` |
| `{buy_credits}` | The Buy Credits page (Pro). | `pages.buy_credits` |
| `{post_need}` | The Post a Need page (Pro). | `pages.post_need` |
| `{browse_needs}` | The Browse Needs page (Pro). | `pages.browse_needs` |
| `{compare}` | The Compare Listings page (Pro). | `pages.compare` |

### Not placeholders

Braces that belong to the product or to a protocol stay as written: email merge tags (`{listing_title}`, `{sender_name}`, `{site_name}` and the rest of the email template chips), the SEO URL and intro template tokens (`{type}-in-{location}`, `{count}`, `{listings}`), REST route parameters (`{id}`, `{batch_id}`), curl write-out formats (`%{http_code}`) and JSON, PHP, CSS or JS code.
