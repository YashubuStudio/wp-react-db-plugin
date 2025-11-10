# Security Review

## Critical Findings

### SQL Injection via `reactdb` Shortcode
* **Location:** `react-db-plugin/includes/shortcode.php`
* **Issue:** The `reactdb_shortcode` function concatenates the user-supplied `db` attribute directly into an SQL query without sanitisation or preparing the identifier. Attackers can craft a shortcode such as `[reactdb db="users; DROP TABLE wp_users; --"]` to execute arbitrary SQL statements, including destructive commands.
* **Impact:** Remote unauthenticated attackers can execute arbitrary SQL, leading to data disclosure, modification, or complete site compromise.
* **Recommendation:** Strictly validate the `db` attribute before use. Derive the table name with `sanitize_key`, validate it against an allow-list, and build the query with `$wpdb->prepare` or `esc_sql`. Additionally, consider enforcing capability checks so only trusted users can render this shortcode.

## Suggested Remediations
1. Sanitize and validate shortcode attributes before interpolating them into SQL queries.
2. Wrap dynamic identifiers with `$wpdb->prepare` or helper escaping functions to prevent injection.
3. Restrict access to administrative functionality by checking user capabilities where appropriate.
