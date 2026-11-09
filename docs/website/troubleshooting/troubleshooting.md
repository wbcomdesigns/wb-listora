# Troubleshooting

## Maps

| Symptom | Fix |
|---|---|
| Markers show on a blank background | No tile source is chosen. Open **Listora > Settings > Maps** and pick one under **Tile source**. Site Health reports this too. See [Map Settings](../settings/map-settings.md). |
| A Pro map provider is greyed out | Save its key first. Until then the site uses OpenStreetMap. |
| Google map shows a grey box with a development watermark | The API key is missing or wrong. See [Google Maps](../features/google-maps.md). |
| Kakao, Naver or AMap shows an error or stays empty | The key is wrong, or this site's address is not registered in the provider's console. See [Regional Maps](../features/regional-maps.md). |
| Some listings are not on the map | The map draws up to **Max markers** (default 500) and says how many are hidden. Zoom in to see the rest. |

## Search

| Symptom | Fix |
|---|---|
| A shared filter link ends on "page not found" | Filter links use names that start with `listora_`. Rebuild the link from the search page. See [Search and Filters](../features/search-and-filters.md). |
| A Plus Code finds nothing | Only full codes such as `87G7GQ54+J2` are accepted, and only listings within one distance unit are listed. Short codes that need a town are searched as text. |

## Pro and payments

| Symptom | Fix |
|---|---|
| The Choose a Plan step is missing from the submission form | Monetization is off, or no plan is published and **On sale**. See [Credits and Plans](../features/credits-and-plans.md). |
| Members cannot buy credits | Credit purchases are switched off. The Buy Credits page stays up and says so. |

## Related

- [FAQ](../faq/faq.md)
- [Installation](../getting-started/installation.md)
