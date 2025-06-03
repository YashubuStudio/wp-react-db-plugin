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

Copy the contents of the generated `build` directory into
`react-db-plugin/assets`. The plugin expects `app.js` and `app.css` to be
located there.

3. Move the `react-db-plugin` directory to your WordPress
`wp-content/plugins` directory and activate **React DB Plugin** from the
WordPress admin panel.

Once activated, a new "React DB" menu will appear and open the React
interface.

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
