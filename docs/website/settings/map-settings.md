## Map Settings

Open **Listora > Settings > Maps**. This tab controls the map background, where maps open, and how markers behave.

![Settings Map - admin UI screenshot (1.0.5)](../images/settings-map.png)

### Map Provider

- **OpenStreetMap** (default): no API key. Listora draws the map with Leaflet and the tile source you choose below.
- **Google Maps** (Pro): needs a Google key with the Maps JavaScript, Places and Geocoding APIs enabled. The option is greyed out until Pro is active and a key is saved.

### Tile source

Listora does not pick a map background for you, because each provider has its own terms. Until you choose one, maps show markers on a blank background and **Site Health** reports that your maps have no background.

1. Open the **Tile source** list.
2. Choose one:
   - **OpenStreetMap - free, for small sites.** Fine for a small directory. OpenStreetMap asks heavy-traffic sites to use another provider.
   - **MapTiler Streets - free key, any traffic level.** Create a free key at maptiler.com and replace `YOUR_KEY` in the address that appears.
   - **Stadia Alidade Smooth - free key, clean style.** Create a free key at stadiamaps.com and replace `YOUR_KEY` in the address that appears.
   - **My own or another provider.** Paste the address of your own tile server into **Map tile URL** and fill in **Tile attribution** with the credit your provider asks for.
3. Click **Save Changes**.

Choosing a provider fills in the address and the credit line for you. The note under the list repeats that provider's usage terms. The Setup Wizard offers the same list.

### Where maps start

This sets the place and zoom a map opens on before any listing is in view.

1. Type a city, address or landmark in **Find a place**, or drag the pin or click on the map.
2. Zoom the map to the view visitors should see first.
3. Click **Save Changes**.

If you prefer numbers, open **Enter coordinates** and fill in **Default latitude**, **Default longitude** and **Default zoom**. Zoom runs from 1 (the world) to 20 (street level). City views usually use 12 to 14.

### Options

| Option | What it does |
|---|---|
| **Marker clustering** | Groups nearby listings into one numbered badge on a busy map. Applies to every map block that does not set its own clustering, and to the mobile app. |
| **Search on drag** | Runs the search again when a visitor pans or zooms the map. |
| **Max markers** | The most markers drawn at once, from 50 to 5000. The default is 500. |

When there are more listings than the marker limit, the map says **Showing 500 of 812 on the map, zoom in to see more**, with your own numbers, so visitors know some are hidden. Zooming in shows the rest.

### How the map frames the listings

When the page loads, the map zooms to fit the listings. With ten or more markers it frames the busy middle of the group and ignores a few far-away outliers, so one listing on another continent does not shrink everything else to a dot. Markers outside the frame are still there. Zoom out to see them.

### Google Maps settings (Pro)

With Google Maps selected:

- **API Key**: your Google Maps JavaScript API key.
- **Map Style**: a preset or your own JSON.
- **Places Autocomplete**: address suggestions in search and on the submission form.

When Google Maps is live (Google selected, a key saved, and the Pro Google Maps feature on), the directory map loads only Google. Leaflet and its tiles are not loaded on that page. If the key is missing, the site keeps using OpenStreetMap.

## Related

- [Google Maps (Pro)](../features/google-maps.md)
- [Setup Wizard](../getting-started/setup-wizard.md)
- [General Settings](general-settings.md)
