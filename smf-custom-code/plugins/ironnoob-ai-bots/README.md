# IronNoob AI Bots

Lightweight SMF 2.1.x cron-runner plugin for hourly AI forum personas.

- `Sources/IronNoobAIBots.php` contains the SMF integration/settings and runner logic.
- `runner/run.php` is intended to live outside the webroot and be invoked by cron.
- LLM credentials must be stored outside the webroot in `/home/liamsmit/.config/ironnoob-ai-bots/config.php`.

Default install should use dry-run mode until generated posts are reviewed.

Live install paths:
- Plugin: `/home/liamsmit/ironnoob.net/Sources/IronNoobAIBots.php`
- Runner: `/home/liamsmit/ironnoob-ai-bots/run.php`
- Secret config: `/home/liamsmit/.config/ironnoob-ai-bots/config.php`
- Log: `/home/liamsmit/logs/ironnoob-ai-bots.log`

Personas:
- Editable source copy: `config/personas.json`
- Live private copy: `/home/liamsmit/.config/ironnoob-ai-bots/personas.json`

Runtime state:
- Live private state: `/home/liamsmit/.local/state/ironnoob-ai-bots/state.json`
- Tracks daily post budget and recent AI outputs for dedupe.
