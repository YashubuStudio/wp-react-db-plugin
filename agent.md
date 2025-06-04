# Agent Guidelines

This repository contains a WordPress plugin with a React frontend.

* Keep React source files under `src/` and PHP plugin code under `react-db-plugin/`.
* Build the React app using `npm run build` and copy the output to `react-db-plugin/assets`.
* Follow the UI design outlined in `設計.txt` when adding pages.
* Run `npm test` before committing if dependencies are available.

## Current Status

- Unwanted navigation to `#/db` is handled by inline scripts that change the URL
  hash back to `#/` in both the admin page and the front-end shortcode.
- Running `npm test` fails in this environment because `react-scripts` is not
  installed.
