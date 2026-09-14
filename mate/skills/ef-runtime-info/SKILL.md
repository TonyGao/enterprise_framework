---
name: ef-runtime-info
description: Read this project's live runtime facts that are not described by the source code - LLM role/provider bindings, custom view designs, and i18n completeness. Use before answering which model serves an AI role, before locating or editing a custom Twig view design, and before declaring i18n work done.
---

# EF runtime info

Three Mate tools expose application state that source code does not describe.

- `ef-llm-roles` - the provider/model bound to each LLM role (vision, reasoning, general, lightweight, embedding), read live from `platform_llm_role` / `platform_llm_provider`. Use before changing AI configuration or explaining a model choice.
- `ef-view-designs [--module=<name>]` - custom Twig designs under `templates/views`, with their versions and file paths. Use to locate a design (e.g. `company_edit_form`) before editing it. `.checkpoints` and `.history` snapshots are excluded.
- `ef-i18n-status` - runs `php bin/console ef:i18n-scan --json` and summarizes missing `|trans` / `t()` keys and hardcoded CJK in Twig and JS. Run it before declaring i18n work done: missing Twig/JS keys and hardcoded CJK in Twig/JS must be 0.

Run them with:

```
vendor/bin/mate tools:call ef-llm-roles
vendor/bin/mate tools:call ef-view-designs --module=组织架构
vendor/bin/mate tools:call ef-i18n-status
```

Add `--format=json` for machine-readable output.
