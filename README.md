# IronNoob Forum Archive

Local working archive for IronNoob SMF forum code, theme migration evidence, and operational notes.

## Repository layout

- `ironnoob-theme-migration/` — theme migration notes and hand-picked source snapshots; generated screenshots/HTML captures are ignored for public safety.
- `smf-custom-code/` — local archives of custom SMF plugins, themes, server configuration, AI bot code, and disabled legacy mods.

## Usage notes

- This repository is an archive/workspace, not an automatic deployment or sync system.
- Copy any live-site changes deliberately, with backups and validation.
- Do not commit live secrets, credentials, database dumps, `Settings.php`, SMTP passwords, Turnstile secrets, cookies, or backup archives containing secrets.
- Nested Git metadata from the archived subprojects has been flattened so this parent directory is the single repository root.

## Live site reference

- Site: <https://ironnoob.net>
- Live host: `ssh everlast`
- Live webroot: `/home/liamsmit/ironnoob.net`
