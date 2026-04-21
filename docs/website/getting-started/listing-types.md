# Listing Types

## What it does

Listing types define the shape of your directory. Each type determines which fields appear on submissions, what schema markup is output for SEO, and what filters are available in search. You can use the built-in types or create completely custom ones.

## Why you'd use it

- A restaurant directory needs cuisine, price range, and hours fields. A real estate directory needs bedrooms, square footage, and price. Listing types give each category its own fields.
- Schema.org markup per type improves how Google displays your listings in search results.
- Separate types mean visitors filter by the right attributes — restaurant visitors don't see hotel amenity checkboxes.
- You can create as many custom types as your directory needs.

## How to use it

### For site owners (admin steps)

**Using built-in types:**

1. Go to **Listora → Listing Types**.
2. The 10 built-in types are listed with their field count, active listing count, and status.
3. Click any type to view or edit its fields and settings.

**Built-in types:**

| Type | Fields | Schema.org |
|------|--------|-----------|
| Business | 8 | LocalBusiness |
| Restaurant | 14 | Restaurant |
| Hotel | 12 | Hotel |
| Real Estate | 12 | RealEstateListing |
| Healthcare | 10 | MedicalOrganization |
| Education | 10 | EducationalOrganization |
| Event | 10 | Event |
| Job | 10 | JobPosting |
| Automotive | 10 | AutoDealer |
| Pet Services | 8 | LocalBusiness |

**Creating a custom type:**

1. Go to **Listora → Listing Types** and click **Add New Type**.
2. Set the type name, icon (choose from the Lucide icon picker), color, and Schema.org type.
3. Add field groups using the visual builder. A field group is a section (e.g., "Contact Info", "Hours").
4. Inside each group, add individual fields. Supported field types:
   - **Basic:** Text, Textarea, Number, Email, Phone, URL
   - **Choice:** Select, Multi-Select, Checkbox, Radio
   - **Date & Time:** Date, Time, Date & Time
   - **Media:** Gallery, File Upload, Video
   - **Location:** Map Location
   - **Structured:** Business Hours, Social Links, Price Range
5. Configure type settings: enable/disable map, reviews, and submissions for this type.
6. Click **Save Type**.

**Modifying an existing type:**

1. Go to **Listora → Listing Types** and click the type name.
2. Add, remove, or reorder fields in the visual builder.
3. Save. Existing listings of that type keep their saved data; any removed fields no longer appear on new submissions.

**Deleting a type:**

Delete a type from **Listora → Listing Types**. Listings assigned to that type are preserved — they remain as published posts, but they no longer have a type assigned.

## Tips

- Start with the Setup Wizard (see [Setup Wizard](setup-wizard.md)) — it installs pre-configured demo types with realistic field sets. Editing a demo type is faster than building from scratch.
- Use the **Event** type for time-limited listings (concerts, pop-up markets). The date fields power the **Listing Calendar** block.
- Assign a unique color to each type — it appears on listing cards and map pins, helping visitors distinguish types at a glance.
- If you remove a field from a type, the stored data for that field is not deleted from the database. If you re-add the same field later, existing data will reappear.
- Custom types support the same search filters as built-in types. Price Range, Rating, and Feature filters are available on any type.

## Common issues

| Symptom | Fix |
|---------|-----|
| New type not appearing in submission form | Clear the page cache; new types register on the next request |
| Custom field not saving | Check the field has a unique key — duplicate keys within a type cause the latter field to be ignored |
| Schema.org type not appearing in Google Search Console | Schema markup requires the listing to be published and indexed; allow up to a week for Google to crawl |
| Deleting a type breaks existing listings | Listings are preserved but lose their type assignment; reassign them from the WordPress admin |

## Related features

- [Setup Wizard](setup-wizard.md)
- [Frontend Submission](../features/frontend-submission.md)
- [Search and Filters](../features/search-and-filters.md)
