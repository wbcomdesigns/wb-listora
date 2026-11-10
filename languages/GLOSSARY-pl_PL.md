# Glossary: pl_PL (Polish, formal/impersonal)

Shared by WB Listora (Free) and WB Listora Pro. One English term, one rendering.

Polish has three plural forms (`msgstr[0]` = 1, `msgstr[1]` = 2-4 except 12-14, `msgstr[2]` = 5+).

| English | Polish | Notes |
|---|---|---|
| Listing | ogłoszenie (pl. ogłoszenia, gen. pl. ogłoszeń) | Dominant existing rendering. Older strings that use "oferta" / "Listing" are not rewritten |
| Directory | katalog | Existing: "Wyświetl katalog" |
| Claim (noun) | roszczenie (pl. roszczenia, gen. pl. roszczeń) | Existing: "Roszczenie", "Prześlij roszczenie" |
| Claim (verb / state) | zgłosić roszczenie; "przejęte" for a claimed listing | "Listing Claimed" = "Ogłoszenie przejęte" |
| Claimant | wnioskodawca | Existing |
| Review | opinia (pl. opinie, gen. pl. opinii) | Existing: "Opinia" / "Opinie"; reviewer = recenzent |
| Badge | odznaka (pl. odznaki) | Existing: "niestandardowe odznaki" |
| Credits | kredyty (gen. pl. kredytów) | Existing: "Kup kredyty" |
| Featured | wyróżnione / wyróżnione ogłoszenie | Existing: "Wyróżnione" |
| Category | kategoria | Existing |
| Amenity | udogodnienie (pl. udogodnienia) | Existing: "Funkcje i udogodnienia" |
| Opening hours | godziny otwarcia | Existing |
| Bookmark / Favorite | ulubione | New strings use "ulubione" ("Dodano do ulubionych") |
| Dashboard | panel | "Mój panel" |
| Lead | potencjalny klient (pl. potencjalni klienci) | Existing |
| Analytics | analityka | |
| Pending | oczekujące | "Oczekujące przeglądu" for Pending Review |
| Draft | wersja robocza / szkic | New strings use "wersja robocza" |
| Owner | właściciel | |
| Member | członek (pl. członkowie) | |
| Space (community) | przestrzeń | Existing: "przestrzenie członków" |
| Terms of service | regulamin | |
| Tile (map) | kafelek (pl. kafelki) | |

Protected (kept in English): WB Listora, Listora, Wbcom Designs, Pro, WordPress, WooCommerce, Stripe, PayPal, Google, Google Maps, OpenStreetMap, Cloudflare, reCAPTCHA, REST, API, URL, URI, CSV, JSON, XML, HTML, CSS, SEO, GDPR, ID, AI.

Notes:
- Register: imperative second person as is standard in Polish software UI ("Zaloguj się", "Wybierz", "Spróbuj ponownie"); no gendered first/second-person past forms ("Opinia została przesłana", not "Przesłałeś").
- Existing inconsistencies in earlier translations ("Claim"/"Roszenia" typo, "Listing"/"Bookmark" left in English, "oferta"/"ogłoszenie") are not rewritten; this glossary governs new translations only.
- Strings with a `%s` that holds a name or product (member, type, plugin) avoid inflection by using a colon pattern, e.g. "Zaktualizowano: %s.", "Do: %s", "Autor: %s".
- Multi-noun plural templates (`%1$s %2$s`) use "<noun>: <verb> <count>" so every plural form stays valid for msgfmt (all forms may only use arguments present in `msgid_plural`).
- Countries use standard Polish exonyms (Niemcy, Włochy, Czechy, Wielka Brytania, Stany Zjednoczone).
