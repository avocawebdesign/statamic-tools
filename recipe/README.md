# Build recipe

`recipe.md` is Avoca Design's build recipe: the process an AI agent follows when it builds or extends an Avoca Statamic site. It covers:

- how a site is put together: the layout with its header and footer partials, the Site Details global, pages with a page header and then the page builder or a simple page, and the design tokens;
- the order for deciding how to build something: use a block the site has, install a library item, change an existing block, and only then build a new one;
- the conventions for new blocks, text editor sets and collections;
- the review steps, from a plan the developer approves to the client's sign-off.

## How a site uses it

The recipe stays in this package. A site refers to it rather than copying it, so a change here reaches a site when the site updates the package.

- The site's `CLAUDE.md` imports it, so Claude Code loads it in every session. The import is a line of its own, outside any code span or code block:

      @vendor/avocadesign/statamic-tools/recipe/recipe.md

- The site's `AGENTS.md` can't import files, so it points agents to the same path.
- The starter kit's exported `CLAUDE.md` and `AGENTS.md` carry both, so every new site has them. For a site made before that, add them by hand.
- The path exists only once the site has this package installed with Composer. Until then the import loads nothing and the pointer leads nowhere.

## Facts and proof

`facts.md` is how an agent writes copy without inventing anything: the site keeps the facts its pages may state in `resources/site/facts.md`, and the workflow says how to start that file, fill its gaps, write from it and change a fact everywhere it appears. A site doesn't import it, because only copy needs it: the recipe's Facts and proof section sends an agent to it. Keep its list of sections in step with the starter kit's `resources/site/facts.md`.

## Writing copy

`copy.md` is how an agent writes copy that sounds like the business and its customers rather than like a machine: the site's `resources/site/voice.md` and `resources/site/customer-voice.md`, how to capture customer language, how to write a page, the patterns that never go in, and `scripts/lint-copy.mjs`, which checks a draft for them. Like `facts.md`, a site doesn't import it: the recipe's Writing copy section sends an agent to it. Keep its list of patterns and the script's in step.

## What it points to instead of naming blocks

The recipe never names blocks or sets, because every site has its own. It sends agents to:

- `resources/site/catalogue.md` and `/site/content`, for what the site already has;
- `php please avoca:library --json`, for what the library can install;
- `resources/site/collections/<handle>.md`, for the decisions behind each collection;
- `resources/site/facts.md`, for what the site's pages may claim;
- `resources/site/voice.md` and `resources/site/customer-voice.md`, for how it says it.

Keep it that way when you edit it.

## Editing the recipe

- Write instructions an agent follows, in plain British English, with no em dashes.
- When a command, path or convention the recipe describes changes in the addon or the kit, change the recipe in the same pull request.
- Decisions for one site belong in that site's `resources/site/` files, not here.
