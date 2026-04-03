## Installation & Activation

### Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+

### Install from WordPress.org

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "WB Listora"
3. Click **Install Now**, then **Activate**

### Install from ZIP

1. Download the plugin ZIP from [wblistora.com](https://wblistora.com)
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate**

### After Activation

WB Listora automatically:

- Creates 10 custom database tables for fast queries
- Registers the `listora_listing` post type and taxonomies
- Adds the **Listora** menu to your admin sidebar
- Redirects you to the **Setup Wizard**

### Verify Installation

Check that everything is working:

1. Go to **Listora > Dashboard** — you should see the main dashboard
2. Go to **Listora > Settings** — verify settings are accessible
3. Visit any page on your site — no errors should appear

### Troubleshooting

**Plugin won't activate:**
- Ensure PHP 7.4+ is installed (`php -v` in terminal)
- Check for conflicts: deactivate other plugins temporarily

**Database tables not created:**
- Deactivate and reactivate the plugin
- Check your database user has CREATE TABLE permissions

**Menu not appearing:**
- Clear your browser cache
- Check your user role has `manage_options` capability
