# React DB Plugin for WordPress

This repository provides a simple WordPress plugin that embeds a React
application in the admin dashboard. The React app communicates with
custom REST API endpoints to read and write CSV files and record actions
in a log table.

## Getting Started

1. Install JavaScript dependencies:

```bash
npm install
```

2. Build the React application:

```bash
npm run build
```

After building, the script defined in `package.json` copies the generated
files into `react-db-plugin/assets` as `app.js` and `app.css`.

3. Move the `react-db-plugin` directory to your WordPress
`wp-content/plugins` directory and activate **React DB Plugin** from the
WordPress admin panel.

Once activated, a new "React DB" menu will appear in the WordPress
dashboard. In addition, activation automatically creates a public page at
`/react-db-app/` containing the React interface so you can access the
tool without visiting the admin area.

Activation also creates a table named `wp_reactdb_logs` used to store
operation logs. When the plugin is uninstalled, this table and the
`react-db-app` page are removed automatically.

### Permissions

The REST API routes are protected by the WordPress capability
`manage_options`. Users without this capability will receive a `401
Unauthorized` response. In the React interface these errors are displayed
as "権限がありません" so non‑administrator users will see a permission
warning instead of a blank screen.

If the page wasn't created for some reason, simply create a new page and
insert the `[reactdb_app]` shortcode to embed the interface on the front
end.

## Shortcode and Block

Use the `[reactdb]` shortcode or **React DB Block** to display a row from a
database table. Both accept an `input` attribute formatted as
`DB:"table",data` to specify the table and optional extra data. Example:

```wordpress
[reactdb input='DB:"c1",sample']
```

## Development Notes

- React source files live under `src/`.
- PHP plugin files reside in `react-db-plugin/`.
- Planned page layouts are described in `設計.txt`.

## Testing

Run tests with the following command if the environment has the required
dependencies:

```bash
npm test
```
