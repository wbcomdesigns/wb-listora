## Understanding Listing Types

Listing types define what kind of content your directory manages. Each type has its own set of fields, schema markup, and display settings.

### Built-in Types

WB Listora includes 10 pre-configured listing types:

| Type | Fields | Schema.org | Icon |
|------|--------|-----------|------|
| Business | 8 | LocalBusiness | building-2 |
| Restaurant | 14 | Restaurant | utensils |
| Hotel | 12 | Hotel | bed |
| Real Estate | 12 | RealEstateListing | home |
| Healthcare | 10 | MedicalOrganization | heart-pulse |
| Education | 10 | EducationalOrganization | graduation-cap |
| Event | 10 | Event | calendar |
| Job | 10 | JobPosting | briefcase |
| Automotive | 10 | AutoDealer | car |
| Pet Services | 8 | LocalBusiness | paw-print |

### How Types Work

Each listing type is a taxonomy term in `listora_listing_type`. When you create a listing, you assign it a type. The type determines:

- **Which fields appear** in the submission form and detail page
- **Schema.org markup** for SEO (Google rich snippets)
- **Search filters** available for that type
- **Display settings** like map pins, card badges, and icons

### Type Manager

Go to **Listora > Listing Types** to manage types:

- **View all types** with field count, listing count, and status
- **Edit a type** to modify its fields, settings, and categories
- **Add new types** with the visual field builder
- **Delete types** you don't need (listings are preserved)

### Custom Fields

Each type has field groups containing individual fields. The visual field builder supports 22 field types:

**Basic:** Text, Textarea, Number, Email, Phone, URL
**Choice:** Select, Multi-Select, Checkbox, Radio
**Date & Time:** Date, Time, Date & Time
**Media:** Gallery, File Upload, Video
**Location:** Map Location
**Structured:** Business Hours, Social Links, Price Range

### Creating a Custom Type

1. Go to **Listora > Listing Types**
2. Click **Add New Type**
3. Set name, icon, color, and Schema.org type
4. Add field groups and fields using the visual builder
5. Configure settings (map, reviews, submissions)
6. Click **Save Type**
