# IP2Location Country Blocker — Zen Cart 2.x Plugin

Blocks storefront visitors from selected countries, based on the visitor's origin
country detected from an **IP2Location BIN database** or the **IP2Location.io web API**.

- Multi-select list of countries to block.
- Configurable block action:
  - **403 Forbidden** — default "Access Denied" page
  - **Custom page** — your own HTML, served with a `403` status
  - **Redirect** — `302` redirect to a custom URL
- **Download Token** support: BIN database from `https://www.ip2location.com/`

---



## Installation

1. **Decompress and copy the plugin container** into your shop root.
2. Log into the admin and open **Modules → Plugin Manager**.
3. The "IP2Location Country Blocker" entry and click **Install**. 



## Configuration

Open **Tools → IP2Location Country Blocker** in the admin.

### Status & Lookup Mode
- **Blocking enabled** — master on/off switch.
- **Lookup mode**
  - `BIN database (IP2Location Database file)` — local lookup, no network, fast.
  - `IP2Location.io Geolocation Web Service API` — uses the IP2Location.io REST service.
  - If the primary mode fails, the other is tried automatically.
- **IP2Location.io API key** — from [ip2location.io](https://www.ip2location.io/pricing)
- **Trust X-Forwarded-For** — honour the `X-Forwarded-For` header for the visitor IP.
  Keep **No** unless the shop sits behind a trusted reverse proxy (the header is spoofable).



### Blocked Countries

A searchable, scrollable checkbox list of every country in your `countries` table
(with select-all / clear and a live count).



### Block Action

- **403 Forbidden (default page)** — a clean, self-contained "Access Denied" page.

- **Custom page** — serve your own HTML with a `403` status (HTML is stored verbatim).

- **Redirect to URL** — `302` redirect; the URL must start with `http://` or `https://`.

    

### Database Download / Update
- **Database file** — `DB1BINIPV6` (Commercial) or `DB1LITEBINIPV6` (LITE).
- **Download token** — from your IP2Location account (Account → Download).
- Click **Download & Update BIN Database** to fetch and unpack the selected package
  into the plugin's `catalog/includes/ip2location/data/` folder. The current file name
  and last-update time are shown.



## Uninstall

Open **Admin → Modules → Plugin Manager**, find the plugin and click **Uninstall**.
This removes the configuration group + settings, the admin page entry and the cache
table, and stops the module loading. The `zc_plugins/IP2LocationCountryBlocker`
folder is left on disk — delete it manually to fully remove it.



## Web-server hardening (nginx)

nginx ignores `.htaccess`. To stop the BIN database and library files being served
directly, add this inside the shop's `server { listen 443 ssl; … }` block and reload nginx:

```nginx
location ~ ^/zc_plugins/IP2LocationCountryBlocker/[^/]+/catalog/includes/ip2location/(data|vendor)/ { deny all; }
```

Apache users get the same protection from the `.htaccess` shipped in the data directory.



## License

MIT. The bundled libraries are:
- [IP2Location-PHP-Module](https://github.com/ip2location/IP2Location-PHP-Module) — MIT, © 2005-2026 IP2Location.com
- [ip2location-io-php](https://github.com/ip2location/ip2location-io-php) — MIT, © 2023-2026 IP2Location.io
