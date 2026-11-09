# Regional Maps: Kakao, Naver and AMap

> **Availability:** Pro only. Requires [WB Listora Pro](../getting-started/activating-pro.md).

## What it does

WB Listora Pro adds three regional map providers next to Google Maps. Pick one and the directory map, the listing detail map, the Add Listing location picker and address search all use it.

| Provider | Covers | Key you need |
|---|---|---|
| **Kakao Maps** | South Korea | JavaScript key |
| **Naver Maps** | South Korea | Client ID |
| **AMap (Gaode)** | Mainland China | Web JS API key and security code |

## Why you'd use it

- Visitors in South Korea and China expect the local map, with local street names and addresses that Google and OpenStreetMap draw poorly.
- Address search on the Add Listing form uses the provider's own geocoder, so Korean and Chinese addresses resolve.

## How to use it

1. Go to **Listora > Settings > Maps**.
2. Under **Map Provider**, choose **Kakao Maps**, **Naver Maps** or **AMap**. The option is greyed out until its key is saved.
3. Open the provider's block lower on the page and fill in its fields:
   - **Kakao Maps**: create an app in Kakao Developers, copy its JavaScript key, and add your site's address under **Platform > Web**.
   - **Naver Maps**: register an application in Naver Cloud Platform, enable Dynamic Map and Geocoding, copy the Client ID, and add your site's address to the web service URLs.
   - **AMap**: create an application in the AMap console, add a key of type **Web (JS API)**, and paste the key and its **Security code**.
4. Click **Save Changes**.
5. Open a page with a map block and the Add Listing form to check both.

## Good to know

- Listings keep their coordinates in the standard GPS system (WGS-84). For AMap, Listora converts them to AMap's system (GCJ-02) when it draws and searches, so saved coordinates never change and you can switch provider at any time.
- Marker clustering works on Kakao Maps. Naver Maps and AMap draw one marker per listing.
- If the key is missing or wrong, the site falls back to OpenStreetMap so the map never goes blank.
- Serving a site in mainland China with AMap also needs an ICP licence, which AMap checks.
- The provider applies to the whole site. You cannot use one on one page and another on the next.

## Common issues

| Symptom | Fix |
|---|---|
| Map area shows a provider error or stays empty | The key is wrong, or this site's address is not registered in the provider's console. Add it and reload. |
| The provider option is greyed out | Save its key first. |
| Pins sit a few hundred metres off on AMap | Check the listing coordinates are standard GPS values, not already converted. |

## Related features

- [Google Maps](google-maps.md)
- [Map Settings](../settings/map-settings.md)
- [Map Providers (developers)](../developer-guide/map-providers.md)
