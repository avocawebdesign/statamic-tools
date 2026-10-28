# Changelog

Avoca Tools uses semantic versioning. While the version starts with 0, a release that breaks something sites rely on, or needs them to do something when they update, moves the middle number (0.1 to 0.2). Anything else moves the last number (0.1.0 to 0.1.1).

## v0.2.1 (28 October 2026)

### Feedback

- **One digest for a review, not one every ten minutes.** The team's email now waits until reviewers have said nothing for 30 minutes, or until the oldest comment in it has waited two hours, whichever comes first. A client who spends an hour leaving twenty comments sends one email after they stop; one commenting all afternoon, one every couple of hours. The scheduler checks every five minutes. `FEEDBACK_DIGEST_QUIET` and `FEEDBACK_DIGEST_HOLD` change the two, in minutes, and `digest_minutes` is gone from the config. `avoca:feedback:notify --now` sends what is waiting at once, and `--dry-run` says when it would go.
- **The digest is by person, then page.** Each reviewer's comments and replies are together, under their name with a count, page by page, and the subject says who: `6 new comments and 2 replies from Jane Client on Acme`. The plain text part no longer escapes apostrophes as `&#039;`.
- **New this week.** In the site's Comments panel and the prototype's, a comment made in the last week by anyone but whoever is looking is marked New, and one with a reply in that time New reply, as is the reply. The team's own words are never new to the team. The panel says how many comments have something new, and a Resolved fold says when something in it is. `recent_days` in the config sets the week. `avoca:feedback` marks what reviewers said this week as new, and `--json` adds each comment's `recent`.
- **Links to comments.** Copy link, in every comment's ⋯ menu on the site and in the prototype, copies an address that opens the comment in place. A prototype link now also switches to the frame the comment was made in when only the other is showing, and sets the page options that were showing when it was made, so the pin lands on what the reviewer saw. `avoca:feedback` prints each comment's address, the server's with `--from`, and `--json` adds it as `link`.
- **A floating Add comment in the prototype.** While the Comments panel is shut, Add comment floats in the bottom right corner. Pressed, the panel opens, the button travels into its place at the foot of the panel and asks for a spot; it travels back as the panel shuts.
- **To do on update:** nothing. A site that set `digest_minutes` in a published copy of the config can drop it.

## v0.2.0 (18 October 2026)

A new release line, the first since v0.1.55. The recipe now builds blocks with Tailwind utilities in their templates, the add-on brings the design check that proves a conversion changes nothing and an audit of a site against the recipe, and critical CSS has a method. Each part below says what a site does about it.

- **The starter kit asks for `>=0.2.0 <1.0`:** a new site starts on this release and takes every release after it up to 1.0, which is a move made on purpose. It asked for `<2.0`.
- **To do on update:** a site that asks for `<2.0` takes this release with `composer update avocadesign/statamic-tools`, and can narrow its constraint to the kit's `>=0.2.0 <1.0`. A launched site is held to 0.1, so it moves only on purpose, as the recipe's Working on a launched site says: change its constraint to `^0.2.0`, then follow each part's To do on update below.

### Persist, and overriding an add-on's views

- **Persist is for Peak's toolbar and Peak SEO's consent banner,** and the recipe's Scripts section now says so. It said to load persist only where the toolbar renders, which left a site whose consent banner was switched on without it. The layout loads `persist.js` wherever either renders (`environment == 'local' || seo:use_consent_banner`), and `persist.js` registers the plugin as the module runs rather than on `alpine:init`, because the banner's own `alpine:init` listener calls `Alpine.$persist` first. The focus plugin stays in `site.js` where the header traps focus on every page, as the kit's mobile navigation does.
- **Changing what an add-on renders is an override, never a rebuild:** a copy of its view under `resources/views/vendor/<package>/`, with a header naming the version and the steps to repeat each change, and each change marked `{{# Avoca: ... #}}`. A new section of the recipe says so, and lists the overrides the kit ships.
- **To do on update:** on a site with Peak SEO's consent banner switched on, check it works: Alpine's persist plugin must load wherever the banner renders and be registered before Alpine starts. The starter kit's `persist.js` and layout show how. Its copy of the banner also replaces Peak's `neutral` colour, which Avoca sites don't have, and fixes the banner's script when no Revoke consent before date is set.

### The audit

- **`php please avoca:site:audit` reads a site's templates, CSS and scripts against the recipe,** so what the Whoosh clean-up found by hand is caught on the next site, or in a new block, before review. It renders nothing and changes nothing. Each finding gives the file and line, what it found, the rule in a line and the recipe section it comes from. It looks for aliases of Tailwind's tokens, two tokens holding one value, a spacing scale of the site's own, tokens outside the token files and colours written out anywhere but `colours.css`; BEM classes, how much of a stylesheet could be `@apply`, a class only one template uses, a stylesheet that only imports another, a rule overriding Peak's, a Tailwind entry without `source(none)`, and colours and type sizes written into templates; an `@source` line in a file `critical.css` shares, and a page-specific critical sheet that saves the other pages less than 10% of their critical CSS; scripts that find elements by class, mark state with one or read values from a stylesheet, Alpine plugins nothing uses and a captcha the layout prints; a bare `<img>`; and comments that repeat the values beside them or tell the code's history. The README's Audit section has each rule.
- **It is advisory:** it exits 0 whatever it finds, unless `--strict`. A site's copies of vendor views are never reported. `--json` reports as JSON, `--all` lists every finding rather than the first eight of each rule, paths narrow it to a block's files, and `--root` audits another folder.
- **A site records what it keeps on purpose** in `resources/site/audit-ignore.yaml`, each entry with the rule, the file and the reason. An entry without a reason silences nothing, and one that no longer matches is listed so it can go.
- **The recipe's Automated checks run it** beside the strict check. Three rules it checks are now the recipe's too: only `colours.css` defines colours, though a `color-mix()` of them can sit beside a component; an illustration's stylesheet may keep the class names it was drawn with, BEM included, and writes what utilities can say with `@apply`; and a page-specific critical sheet is worth having only when folding it back would make most other pages' critical CSS 10 to 15% larger or more.
- **Run on Whoosh's history, it finds what the clean-up removed by hand:** the `--t-*` alias layer and the `--s1` to `--s10` scale, a BEM stylesheet per block, `whoosh.css` overriding the grid, Peak's `@source` list reaching the critical CSS, four page-specific critical sheets, scripts reading values from CSS and the unused morph plugin, and comments that repeat the code's values or tell its history. On Whoosh now it finds 46 things, most of them about its illustrations: their BEM classes, how much of each stylesheet could be `@apply`, and the classes their scripts mark state with.
- **To do on update:** nothing. Run it once, fix what is quick, and record in `resources/site/audit-ignore.yaml` what the site keeps on purpose. Work through what it finds case by case: some, such as an illustration's BEM names or a page-wide state class, stay, recorded with their reason.

### The design check

- **`scripts/design-check.sh` proves a change leaves the design as it was,** for moving a block to utilities or changing a shared class or a token. `design-check.sh before` builds the assets, then snapshots every page builder block, the header, the page header and the footer on the pages `avoca:site:urls` names, at 390 and 1440 wide. Each snapshot holds every element's computed styles and box, matched by its place in the DOM rather than its class, and a screenshot. `design-check.sh compare before after` prints SAME or DIFFERENT per block, and names a block for every property that changed, with a diff image of its pixels. `--block` and `--state` add a block's states, such as a carousel's second slide or its look before a scroll reveal. The site is served from the build each run makes: with the Vite dev server on, `public/hot` is set aside until the run ends, and only one run goes at a time. Each block is loaded fresh on a page of its own, one at a time, with every web font loaded first and used even where `font-display: optional` would leave it out, and timers of a second or more held, so a carousel is taken on the slide it loads on. It is photographed with everything around it hidden, so a change to one block can't make its neighbour differ. `--jobs 4` takes four blocks at once, about three times quicker, though two runs of the same code then agree less often. Runs go in `storage/design-check/`, which keeps itself out of git, and Puppeteer comes from the site's own `node_modules`, which the starter kit lists. It is Whoosh's `scripts/design-check`, which proved Whoosh's Proof strip pixel-identical after its conversion, taken into the add-on with its page list now coming from the site. The README's Design check section has the rest.
- **The recipe's Styling rule names it,** in place of Whoosh's copy.
- **To do on update:** nothing. A site keeping a copy of Whoosh's `scripts/design-check` can delete it and use this one.

### Blocks styled with utilities

- **Blocks style themselves with Tailwind utilities in their templates,** as Peak sets a site up, under the recipe's new Styling section. The rule that CSS for a component goes in its own file in `resources/css/components/` is gone: that folder keeps the kit's buttons, caption and clickable parent, and artwork or choreography utilities can't express. Tokens are declared only in `theme.css` and `colours.css`, with Peak's heading and prose tokens in `typography.css`. A size of a block's own is a number on Tailwind's spacing scale, such as `max-w-70` for 280px, with an arbitrary value only where no number fits. A script takes its values in its `x-data` call rather than from CSS, finds elements by `data-` attributes rather than classes, and marks state with `data-` attributes that utilities read.
- **Nothing is styled twice, so a restyle is one edit.** A fragment of markup repeated within or across blocks becomes a partial that owns all of it, wrapper and state attributes included, in `page_builder/<handle>/` or `components/`. A class list repeated on wrappers a partial can't own becomes a shared class with `@apply` in a components-layer section of `utilities.css`, so a template's utilities always override it. A class list used several times in one template can be an Antlers variable. `@apply` is never a stylesheet for one block. Shared behaviour, such as a scroll reveal, a crossfade or an `armed` variant for what a script will show, is an `@utility` or a `@custom-variant` in `utilities.css`.
- **The scales are tuned to the design.** A site keeps Peak's Utopia scale with its step names, and may tune single steps by hand where no one configuration gives the design's sizes, each step carrying its own line height as `--text-*--line-height`. Tailwind's leading, tracking, radius and easing scales are tuned the same way, with a name of the site's own only between two of Tailwind's steps. `typography.css` keeps Peak's `--typography-*` wiring onto the steps. Designs from Paper reads the kit's numbers as the kit's, and says what a tuned step means going up and coming back down.
- **Headings off the scale.** A heading styled as one of the site's headings is still a plain `<h1>` to `<h6>` or takes `heading-size-*`. One deliberately off the scale carries utilities in its template and is listed in the site's `resources/site/design.md`; used a second time, it becomes a heading class with `@apply` in `typography.css`.
- **A conversion is proved to change nothing,** by comparing every element's computed styles and the pixels before and after, as Whoosh's `scripts/design-check` does. The visual review step says to quote what the comparison found.
- **`site.css` names what Tailwind scans,** with `@import "tailwindcss" source(none);` and Peak's `@source` list moved there from `peak.css`. Without it Tailwind scans the whole repository, documentation and the prototype included, and builds CSS for class names written in them. And because `critical.css` imports `peak.css`, Peak's list there made the critical CSS scan every view, so its own `source(none)` and `@source` list never took effect. The Critical CSS section now says no shared import names a source. On Whoosh the two together made `site.css` 8.5% smaller and `critical.css` 25% smaller.
- **The CSS class field's class is defined at the end of `utilities.css`, outside any layer,** since a block's styles are now utilities, which win over a class in a layer.
- **To do on update:** nothing. A block with a stylesheet of its own keeps working; move it to utilities as a change of its own, proved with a design comparison, and on a launched site suggest it in the plan. A site that wants the smaller CSS moves Peak's four `@source` lines from `peak.css` into `site.css` and adds `source(none)` to its `@import "tailwindcss"`. Compare the built `site.css` before and after: a class that disappears and is used was written somewhere outside the list, such as a fieldset's options or a global, and needs a line of its own. On a site with a `critical.css`, check the first paint with the network throttled afterwards, because a partial the critical list missed was hidden by Peak's list until now.

### Critical CSS

- **The recipe says how critical CSS is built, under Colours and design tokens.** `resources/css/critical.css` is a second Vite entry beside `site.css`, starting `@import "tailwindcss" source(none);` and naming the above-fold partials with `@source`: the layout, the header, the navigation, the page header, the logo and the button partials. Tailwind generates the utilities those templates use, so nothing has to measure what is above the fold, and every `npm run build` regenerates it. The layout inlines it with `{{ vite:content }}` and loads `site.css` with the `media="print"` swap and a `noscript` copy behind it. The section has the three lines for the head, says which imports are shared with `site.css` and why the duplication is deliberate, and says what to check after a change to the header or the page header. avoca.design has run this for a while: 10.2 KB inlined against 25.1 KB, compressed.
- **`avoca:site:check` checks the `@source` list.** On a site with a `resources/css/critical.css` it follows the partials the header and the page header render and warns for any no `@source` line covers, and warns when the entry is not in `vite.config.js` or not inlined by the layout. A site without a `critical.css` is checked for nothing, and it never fails, even with `--strict`: the page renders either way.
- **The one thing to keep current is the `@source` list.** A partial added to the header or page header and not added to the list has its utilities missing at first paint and arriving when the full sheet lands, which the section says to check by watching the first paint with the network throttled.
- **To do on update:** nothing. A site without a `critical.css` carries on as it is; the section says how to add one when a site wants it.

## v0.1.55 (15 October 2026)

- **Comments are committed through the site's server git script, on Core as well as Pro.** v0.1.53 handed them to Statamic's git, which only runs with Pro. Now a change to a comment runs `scripts/server-git.sh commit --now` once the response has gone, so the reviewer never waits, and the comment is committed, and pushed where the remote can fast-forward, within seconds, with a subject such as `Feedback: comment on /partners [BOT]`. It needs the site's copy of the script, `STATAMIC_GIT_ENABLED=true`, and the web server's PHP user able to commit and push with the cron job's key; cron's every-minute run still catches anything missed. `FEEDBACK_COMMIT=false` turns it off, and unset it is on anywhere but a local machine. The README's Feedback section has the rest.
- **The server git script takes a subject:** `AVOCA_GIT_SUBJECT` sets what commit mode commits as, with `[BOT]` added when it is missing. Unset, it is `Content saved on the server [BOT]` as before.
- **To do on update:** a site with `scripts/server-git.sh` runs `php please avoca:site:script` to take the new copy, which `avoca:site:check` now says is behind. With the old copy, comments are still committed straight away, under the script's usual subject.

## v0.1.54 (15 October 2026)

- **v0.1.53 broke every Pro site with git off,** locally and on most servers: every page and command threw "Statamic Git integration is disabled", because the add-on built Statamic's Git facade to listen for comments, and its constructor throws when git is off. The add-on now listens as `Git::listen()` does inside, without the facade, and a test boots a Pro site with git off.
- **To do on update:** skip v0.1.53. A site that took it updates to this release.

## v0.1.53 (15 October 2026)

- **Comments are committed as they're made,** on a server with Statamic's automatic git. Every change to a comment's file, made, replied to, edited, resolved, reopened, raised or answered as a question, its pin moved or deleted, fires `FeedbackChanged`, which Statamic's git commits, and pushes where the server pushes, as it does a save in the control panel, with a message such as `Feedback: comment on /partners`. Before, a comment made on the server stayed untracked until someone saved something in the control panel. It needs Statamic Pro and `STATAMIC_GIT_ENABLED=true`, as Statamic's git does. With `STATAMIC_GIT_DISPATCH_DELAY` set, as the kit's `.env.example` has it, changes are gathered into one commit with Statamic's own message. The README's Feedback section has the settings.
- **To do on update:** nothing. On a server with automatic git and the `sync` queue, the commit and any push run while a reviewer's comment posts; a queue worker on `STATAMIC_GIT_QUEUE_CONNECTION` takes them off the request. Comments already untracked on a server go in with the next commit.

## v0.1.52 (15 October 2026)

- **Reviewers see decisions as questions.** In the comments panel, on the site and in the prototype, the filter is Questions, a card's badge says Question, the team makes a comment a question and records its answer, the outcome is headed Answered, and Who decides is Who answers. The prototype's help says a question to answer. Nothing stored changes, and the command and `resources/site/decisions.md` still call them decisions.
- **Done is now Resolved:** the filter, the folded list beneath each page, the help, and the button, Resolve in place of Mark done.
- **The filters are a button group,** the chosen one raised, like the prototype's Notes and Comments switch, on the site and in the prototype.
- **To do on update:** nothing.

## v0.1.51 (15 October 2026)

- **A Done filter** in the comments panel, on the site and in the prototype, lists the comments closed and the decisions made, page by page in number order, each decision with its outcome.
- **Open is now Comments, and lists only comments.** Decisions still to make are under Decisions, and what's done under Done. Beneath each page, Comments folds away the comments closed and Decisions the decisions made.
- **All comments is now All pages.** Opening a comment on another page, from a link or the panel, switches All pages to the filter that lists it.
- **To do on update:** nothing.

## v0.1.50 (15 October 2026)

- **Comments are numbered in one sequence, in the order they were made,** instead of starting again at 1 on every page: across the whole prototype, every version included, and separately across the site. A comment keeps its number, so it is the same in every list, on its pin and when the team talks about it.
- **Lists read in number order.** A page's comments are listed by number, decisions among them rather than first, with those done still folded away beneath. In All comments, pages come in the order of their first comment showing, so the numbers run down the list; the site's panel no longer puts the page you're on first.
- **To do on update:** nothing. Comments already made are renumbered once, by when they were made.

## v0.1.49 (15 October 2026)

- **People can edit their own comments and replies,** on the site and in the prototype: Edit in a comment's ⋯ menu, or beneath your own reply, changes the text in place, with Save and Cancel, and Escape cancels. The team can also edit anything the team wrote, but only a reviewer edits a reviewer's words, and the server checks this whatever the panel shows. Authors are matched by name, since comments keep a name and not an email. An edited comment or reply shows "edited" beside its time and records who edited it and when. Edits aren't in the team's digest, and a decision's outcome is still changed by reopening it.
- **`avoca:feedback --edit=<id> --message="…"`** changes a comment's text from the command line, here or on a server with `--from`, so a long comment can be shortened without opening the panel. The command acts for the team, so it edits the team's comments and never a reviewer's. Listings say "edited" beside the time of anything edited.
- **To do on update:** nothing.

## v0.1.48 (14 October 2026)

- **When the design is done, the control panel screenshots are checked,** before `design` is merged into `main`: every block in the page builder and every set in the text editor. Developer details on `/site/content` says which are missing or not added; the agent takes those, retakes any the design has made out of date, commits them together and lists in the pull request any it couldn't take. It is under Control panel screenshot, with a pointer from Branches and commits.
- **The recipe has a Launch section.** At launch a site holds the add-on to the line it launches on, such as `^0.1.48` in place of the kit's `<2.0`, so later releases in that line still arrive and a new line waits for the developer; it records `launched:` with the date in `resources/site/updates.yaml`; and it finishes what the recipe leaves until launch, gathered into one list. Working on a launched site, the recipe applies to the change in hand: anything a newer recipe would build differently goes in the plan as a suggestion, and a release's To do on update is a suggestion too. Moving to a new line is a change of its own. Before you start now asks whether the site has launched.
- **`avoca:site:check` warns when a launched site still lets the add-on move to a new line,** and says what to ask for instead. A site without `launched:`, or one taking the add-on from a branch or a local folder, gets no warning.
- **A list inside a repeating item, such as the points on a card, is a grid with one text field,** under the recipe's grid or replicator guidance, rather than bullets in the item's text editor.
- **To do on update:** nothing. A site whose design is already done can run the screenshot check at any time. A site already live can record `launched:` and narrow its constraint, as the Launch section says.

## v0.1.47 (14 October 2026)

- **Every phone number, email address and social link, and the business's address, comes from Site Details,** even where a design shows it only once. Before, the recipe said so only for details used in more than one place.
- **A design with real contact details means a question first.** When a footer, contact page or any other design shows real ones, rather than placeholders or the kit's sample values, the agent lists them beside what Site Details holds, in the plan, and asks whether to import them. The template reads them from Site Details whatever the answer. The Paper conversion table and the plan's checklist say the same.
- **`main` is merged into `design` at the start of every session,** with `git fetch origin` and `git merge origin/main`, so the design branch has the quick fixes and, on a site edited on the server, the client's content. A conflict goes to the developer.
- **To do on update:** nothing. A site whose footer or contact page types in a phone number, email address, social link or address should move it to Site Details when that part is next worked on.

## v0.1.46 (14 October 2026)

- **The recipe says when to use a grid and when a replicator** for content that repeats, under Building a new block's Fieldset, for blocks, sets and collection blueprints alike. Any item with a text editor goes in a replicator, collapsed as an accordion, because a grid can't collapse its rows and every editor stays open; so do items of more than one kind. A grid is for items that are the same short form, stacked once a row has more than three fields or a textarea. Switching an existing field between the two means moving its saved content.
- **Design work goes on a branch called `design`,** for the whole design stage and every design brought down from Paper. A quick fix, even one from Paper, goes on a branch of its own from `main`. Only `main` goes live, so both reach the live site through a pull request into `main`. The recipe's new Branches and commits section has this, and Before you start, Designs from Paper and the pull request step point to it.
- **When a page needs new sections, each one is committed as soon as it is built,** and the agent asks the developer whether to push before starting the next.
- **The recipe checks for a newer add-on** at the start of every piece of work, and each day when the work runs on, under Updates' new Keeping the add-on current: `composer show avocadesign/statamic-tools --latest`, then `composer update avocadesign/statamic-tools` within the site's constraint, the changelog's To do on update for each release missed, the catalogue and the strict check, `composer.lock` committed on its own, and the recipe read again. A new release line outside the constraint goes to the developer. The add-on's releases don't wait the runner's three days.
- **To do on update:** nothing. Existing grids with a text editor in each row still work; change them when the block is next worked on. A site without a `design` branch gets one from `main` when design work starts.

## v0.1.45 (13 October 2026)

- **The recipe no longer assumes static generation.** Every Avoca site runs Statamic's full static cache in production, with PHP still behind it, so `nocache`, forms and requests from the browser to the site's own server are allowed. The recipe's Static generation rules are replaced by Static caching: the cached page is the same for everyone, so anything per visitor goes in a small `nocache` region; forms work, with the CSRF token filled in by JavaScript; which routes and query strings the cache keeps, and which to exclude; what clears it; the server's rewrite rule; how to check a page with the cache on locally; and why a staging site taking feedback runs the half strategy. Hosting, Before you start and the plan's checklist follow it, and the Testimonials preset's comment on random order no longer mentions a static build.
- **The server git script no longer says the kit ignores `storage/forms`,** which the kit now keeps in git. Only that comment changed.
- **To do on update:** nothing required. A feature held back because a site might be generated statically can now be built, and plans or `resources/site/` notes citing the static generation rules are out of date. `avoca:site:check` will say a site's `scripts/server-git.sh` is behind; `php please avoca:site:script` brings it up to date and loses nothing.

## v0.1.44 (10 October 2026)

- **A click on the pages closes the prototype's side panel,** Notes or Comments, as a click on the site's page closes its panel: in a frame or on the stage around them. The bars, buttons and menus leave it open, and so do a comment's pin, a click choosing a spot, and a comment or reply with text in it, which closing would lose.
- **To do on update:** nothing.

## v0.1.43 (10 October 2026)

- **The Content model has the Sitemap's room at its sides:** 32px, its title bar included. Both have 16px on a phone.
- **The site's Comments tab is black, with square corners,** rather than yellow. Its count, and its ring when focused from the keyboard, take the site's colour, as below.
- **The team can delete a comment** made by mistake, twice or as a test: Delete, in red in the ⋯ menu, in the prototype and on the site, asks first. Reviewers can't, and a decision can't be deleted until it is made a comment again. `avoca:feedback --delete=<id>` does the same, locally or with `--from`. A deleted comment's file can be found in git's history once it has been committed.
- **Add comment sits across the foot of the Comments panel,** above Show comment pins, in the prototype, the Sitemap and the Content model included, and on the site, rather than floating over the page. While a spot is being chosen it says Click the spot you mean, with Cancel. On the site, a click on the page outside the panel closes it, and a comment half written is still there when the panel opens again.
- **Comments take the site's primary colour** rather than yellow, on the site and in the prototype: Add comment, the comment counts and, in the prototype, the outline while choosing a spot and the page options' heading use the primary button's colours, `--btn-primary-bg` and `--btn-primary-text`. A site can set its own for both with `--feedback-accent` and `--feedback-accent-ink` on `#avoca-feedback` in its CSS. With neither, they're Avoca's blue. Amber still marks a decision to make.
- **To do on update:** nothing.

## v0.1.42 (10 October 2026)

- **Fixed: the line along the top of the sitemap reaches the first and last pages.** Each column now draws its own piece of it, so it meets every column however wide each is.
- **The sitemap has more room:** 32px at its sides, its title bar included, 32px between columns, and 16px between pages stacked in a column. When it's wider than the screen it scrolls right to the edges, and its last column ends 32px clear once scrolled to the end, so it no longer needs extra room on the right.
- **Comments on something this version doesn't have.** Opening a comment from an earlier version, in All comments with Comments on earlier versions too, takes you to that version with the comment open and its pin where it was made, rather than to the same page in this version, where its pin didn't show. A decision whose page this version doesn't have is listed last in All comments, under the page's old title and marked Not in this version; opening it keeps you where you are, with the comment open, rather than taking you to the home page. A pin on the Sitemap or the Content model whose page box or card has gone sits where it was made, ringed with a dashed line, as pins on the pages already were.
- **To do on update:** nothing.

## v0.1.41 (10 October 2026)

- **The content model's drawer makes room for itself.** Where there's space, the diagram moves over while the drawer is open, beside the Comments panel too, so no card is ever under it, and takes the full width back when it closes. On a narrow screen it still covers the diagram.
- **Fixed: the sitemap's lines join up.** The line under a page now starts at its box and stops at its last child, and each branch reaches its box. Before, it began a little below the box, ran on past the last child, and stopped just short of each box.
- **Each tab opens at its top.** The Sitemap and the Content model no longer open scrolled to wherever the last tab was left.
- **To do on update:** nothing.

## v0.1.40 (10 October 2026)

- **Comments on the Sitemap and the Content model.** Each has a title bar, like a page's in the prototype, with a Comments button that opens the comments panel beside it. Add comment pins a comment to a page box or a card, or to an empty spot, and the pins sit on the diagram as they do on the pages. All comments lists them under Sitemap and Content model, after the pages, and opening one takes you to its tab. `avoca:feedback` names the tab as the comment's place.
- **The Sitemap's description is one line:** Select a page to open it in the prototype. `PROJECT.sitemapIntro` is no longer shown.
- **The Content model has no side panel.** Selecting an item opens a drawer from the right, over the diagram, with its expected number of entries, its fields, its relationships and the pages it appears on; it scrolls on its own, so a long list of fields fits. Its close button, Escape, selecting the card again or a click on empty space closes it. The cards just name each item. What the model holds, what changed since the proposal and the checks against the source documents sit in columns below the diagram.
- **The Sitemap and Content model title bars run edge to edge,** with no gap around them.
- **To do on update:** take `sitemapIntro` out of `PROJECT` in `data.js`, if it's there.

## v0.1.39 (10 October 2026)

- **The sitemap and the content model sit on the main background,** with no panel around them.
- **Journeys only show with the pages.** On the Sitemap and Content model tabs the left strip keeps only the agency's logo, and on narrow screens the Journeys button hides.
- **To do on update:** nothing.

## v0.1.38 (10 October 2026)

- **Page notes describe the page and nothing else:** one line on what it's for, and its content as displayed, section by section in order, under On this page (was Content to prepare). Considerations are retired from the notes panel and the `data.js` template; the recipe says anything still to agree is a decision comment, anything decided lives in its outcome, advice and reminders stay out, and technical notes only say how something is built that the page doesn't show.
- **`SITEWIDE.content` describes the header and footer as displayed,** like a page's content, on the Sitemap tab. `SITEWIDE.consider` still shows there until it's renamed.
- **The sitemap's room sits above its scroll bar,** which runs along the foot of the canvas, with more room above Home. While it runs off the right edge it fades there, and gains room on the right so its last column scrolls clear of the fade.
- **The Sitemap tab has no side panel.** What it held is on the page: the intro joins the description under the heading, and the menus, the header and footer, where the source documents go and what changed since the proposal sit in columns below the tree, which now has the full width.
- **To do on update:** in `data.js`, rename `SITEWIDE.consider` to `content`, take `consider` out of each page's notes, and rewrite `content` as what the page shows.

## v0.1.37 (10 October 2026)

- **Site-wide notes show once, in the Sitemap's panel,** under Header and footer beside the menus, rather than in every page's notes. A page now has notes, and a Notes button, only when it has something of its own.
- **The recipe says what site-wide notes are for:** the rare point every page shares that the pages can't show. Page notes don't describe the header, menus or footer, which every page draws and the Sitemap lists; anything to agree about them is a comment pinned on them. Most sites leave `SITEWIDE` empty.
- **Fixed: the Sitemap and Content model tabs draw their side panels again.** Since v0.1.34 the line that places Add comment sat inside the code that fills the panel, so on those tabs it kept showing whatever was there before.
- **To do on update:** nothing; a site's site-wide notes move to its Sitemap panel. Delete any that only describe the header, menus or footer.

## v0.1.36 (10 October 2026)

- **The recipe says decisions belong in the comments, not the notes.** Notes say what is settled and stay clean and short; a question, a choice or anything still to agree is a comment raised as a decision.
- **To do on update:** nothing.

## v0.1.35 (10 October 2026)

- **Page notes' sections sit under full-width lines,** as pages do in All comments, and the content to prepare is a plain numbered list rather than bold items with a bar beside them.
- **Technical notes read as plain sentences,** not bullets, and the recipe says to leave where content comes from to the content model links rather than repeating it in the text.
- **To do on update:** nothing.

## v0.1.34 (10 October 2026)

- **Sitemap boxes fit their page names.** Columns start wider and grow to fit their longest word, and the sitemap scrolls sideways when they need more room, so a long title never spills out of its box. Addresses break only after a slash, a hyphen or a dot.
- **Add comment floats in yellow at the foot of the page** while the Comments panel is open, rather than sitting in the panel: centred on the frames in the prototype, and on the page beside the panel on the site. In the prototype, while a spot is being chosen, it says so and a click on it stops.
- **In All comments, Showing sits beside the page's name,** rather than at the far right; on the site, This page does too.
- **The site's Comments tab is yellow,** like Add comment, with the open count in a dark pill.
- **All comments filters to Open or Decisions,** and each page's done comments fold away beneath it under Done, as they do on the page. The Done and All filters go, and To decide reads Decisions.
- **All comments has more room between pages,** each under a line that runs the panel's full width, and the filters share the panel's width.
- **The recipe says how to write prototype page notes,** short and in the client's words: the description says what the page is for, the content to prepare lists only what the client has to supply, and the technical notes say where content comes from, such as Site Details.
- **To do on update:** nothing.

## v0.1.33 (10 October 2026)

- **All feedback reads All comments** in the Comments panel, in the prototype and on the site.
- **In All comments, another page's heading takes you there,** with an arrow beside it, in place of an Open page or Go to page link. The page you're on stays plain.
- **To do on update:** nothing.

## v0.1.32 (10 October 2026)

- **Only decisions are labelled,** in the prototype and on the site: the Comment label goes, and Decision sits over the card's top right corner, amber while it's to make and green with a tick once done.
- **Remove decision reads Make a comment,** in the team's ⋯ menu.
- **Show comment pins sits at the foot of the Comments panel,** pinned there over a list longer than the panel. On the site it shares the foot with who's commenting.
- **To do on update:** nothing.

## v0.1.31 (10 October 2026)

- **The recipe says which background colours to use in the prototype:** white, light and medium greys, and darkish greys down to about `gray-700`, but nothing darker, the Dark scheme included, since the interface around the frames is near-black and a dark band there makes the page seem to end. The page options guidance moves under the same new prototype section.
- **Decisions lose their yellow borders,** in the prototype and on the site: the label is amber text in the same grey outline as Comment, and the card no longer has a yellow edge. The amber number still marks a decision to make.
- **A comment's time sits below its author's name,** in the prototype and on the site, replies included.
- **To do on update:** nothing.

## v0.1.30 (10 October 2026)

- **A comment is labelled only Comment or Decision,** in the prototype and on the site. The colour says where it stands: plain while open, amber for a decision to make, green with a tick once done. Who decides, when the team gives it with `avoca:feedback --who`, shows as a line inside the open comment rather than in the label, and the date no longer wraps beside a long label.
- **Comments have an open and close chevron** at the right of their header: down on a closed comment, up on an open one.
- **Add comment stays on one line** in the Comments panel.
- **Fixed: the site's Comments tab opens again.** Since v0.1.26 a click on the tab, or a `?review` or `?feedback=` link, failed with "open is not a function": the loader's new setting for a reviewers list took the name of the function that opens the panel. Pins and the count, which start another way, still worked.
- **To do on update:** nothing.

## v0.1.29 (10 October 2026)

- **The page options bar starts folded,** to one line of what's showing, on every visit, like page notes and journeys. Show options opens it.
- **Frames are just the page.** The browser bar and its address are gone from every frame; the page's address is beside its title above. Display: Desktop shows the page edge to edge at full size in all the room it has, with no frame or label, so the page lays out at that width and follows the window as it's resized. Both keeps the desktop as a 1440 by 900 laptop scaled to fit beside the phone.
- **Sites can add other devices,** such as a tablet, with `DEVICES` in `data.js`: none by default. Each becomes a choice in Display options, shown on its own, and comments made on it are kept for it.
- **Menus close when you click into a page,** as they do for a click anywhere else: Display options, the version menu and a comment's ⋯ menu.
- **The Display options button says what's showing:** Display: Both, Display: Desktop or Display: Mobile.
- **Comments are in git.** They are kept in `content/feedback`, one file per comment, rather than `storage/app/feedback`, which git ignores. Comments made locally go up with a push; on a server whose content is edited there, its git script commits them with the rest of the content, so they come down with a pull rather than only through `avoca:feedback --from`. The digest's record of when it last sent stays in storage, so it never makes a commit. Comments saved in storage by an earlier version move across the first time they're read.
- **To do on update:** nothing, unless a site's repository is public: comments, with reviewers' names, now go into its history.

## v0.1.28 (10 October 2026)

- **The page options bar is just the options,** each name above its choices, and Close or Show options to fold it: no line asking reviewers to try each, and no note beside an option's name, which only invited "to decide". Decisions are made in the comments.
- **The recipe says how to write page options:** a word or two for each name and choice, no question in the label, two or three real alternatives, and out of the next version once decided.
- **To do on update:** a site whose `data.js` gives an option a `note` can drop it; it no longer shows.

## v0.1.27 (10 October 2026)

- **Journeys are numbered,** with who takes each one under its name and its device as a small icon on the right, rather than the words Mobile or Desktop. The journey being followed has its number filled in.
- **To do on update:** nothing.

## v0.1.26 (10 October 2026)

- **Page options in the prototype.** A page can be shown more than one way for reviewers to compare, from `OPTIONS` in `data.js`: a bar above the frames shows the choices on pages that have them, and folds to one line of what's showing. A choice redraws the page in both frames, or in the one it names, or sets the timeline's layout with `mode: true`. `pages.js` reads it with the new `option('id')` helper, the frame carries `data-option-<id>` for CSS, and a comment records which choices were showing. It replaces the timeline switch in page notes (`tryit`), which only one site's timeline used.
- **The Comments tab shows on every page when the site lists its reviewers,** since only their emails can sign in: no `?review` link needed. Without a list it still waits for `?review`.
- **Journeys** list Arrives, Wants to and Success each above its text, and say "an enquiry" rather than "a enquiry".
- **To do on update:** a site whose `data.js` sets `tryit` on a page moves that switch to `OPTIONS`.

## v0.1.25 (10 October 2026)

- **Feedback on the site works as the prototype's comments do.** The tab reads Comments and carries the number open on the site. Its panel shows this page's comments, with those done folded away, or all the feedback page by page, filtered to open, to decide or done. Pins are graphite while open, amber for a decision to make and green once done; they can be dragged when they cover something, switched off with Show comment pins, and still show for a comment being pointed at. The team raises and records decisions from the panel, and Add comment writes the comment in the panel with the + on the page. Cards no longer say where a comment is in words (the pin shows it; `avoca:feedback` still does).
- **Fewer, smaller actions,** on the site and in the prototype. An open comment has a slim reply box, whose Reply button shows once something is typed, and one button for the next step: Mark done, Record decision (the team) or Reopen, which reopens a decision and a resolved comment alike. The team's Make it a decision and Make a comment sit in a ⋯ menu. Show on page is gone: opening a comment brings its pin into sight, scrolling only when it's out of view, and one on another page takes you there.
- **Pins show as reviewers browse.** For a browser that has reviewed before, the loader starts the widget once the page is idle, so pins and the count show without a click. Visitors who haven't reviewed still get only the loader.
- **To do on update:** nothing.

## v0.1.24 (10 October 2026)

- **The team hears about new comments.** One email every 10 minutes, a digest of what reviewers said since the last on the site and in the prototype, each with a link that opens the comment, through sign-in when needed (the prototype now takes `?comment=<id>`). It goes to reviewers marked `team: true` and `FEEDBACK_NOTIFY`'s addresses, through the site's mail, and leaves out the team's own comments. `avoca:feedback:notify` sends it, with `--dry-run` to see who would be told what; Laravel's scheduler runs it while feedback is on. If an email fails, the next run sends everything since. `avoca:site:check` warns when feedback is on and nobody would hear.
- **Pins can be moved.** Drag a comment's pin in either frame when it covers something: it pins to the element it's dropped on, for everyone, and a click still opens the comment. Anyone signed in can, as anyone can resolve, through a new `comments/{id}/anchor` route.
- **With pins switched off,** pointing at a comment in the panel still shows its pin, and the open comment's.
- **The prototype's pins switch reads Show comment pins,** in the Comments tab and in Display options.
- **The side panel closes with a cross** rather than a panel icon.
- **The add-on's config is merged as it registers,** rather than as it boots, so the scheduler sees it.
- **To do on update, on a site being reviewed:** make sure the server runs Laravel's scheduler (`php artisan schedule:run` every minute in cron), and mark the team in `reviewers.yaml` with `team: true` or set `FEEDBACK_NOTIFY`.

## v0.1.23 (10 October 2026)

- **The prototype's guide says it isn't designed at all:** the design comes after the prototype is approved, rather than "it isn't the final look".
- **To do on update:** nothing.

## v0.1.22 (10 October 2026)

The prototype's decisions come from its comments, and its interface is reworked around them.

- **Comments in the prototype.** With feedback on, the Comments button, with the number still open, opens a side panel. Add comment asks for a spot on either frame; the numbered pin sits on the element clicked, in both frames and at the same size whatever the zoom: graphite while open, amber for a decision to make, green once done. Done covers a decision made and a comment resolved, which a client can't tell apart, so the panel says Done and Mark as done for both. It lists this page's comments or all the feedback, filtered to open, to decide or done, with a switch for the pins. Prototype comments keep their version, page, route and frame, and never mix with the site's own comments or counts.
- **Decisions are comments the team raises.** A control panel login, a reviewer marked `team: true` in `reviewers.yaml`, or `avoca:feedback` can raise a comment as a decision, record what was decided, reopen it or take it off; reviewers can't. Decisions carry from one version to the next. `avoca:feedback` gains `--decisions`, `--raise`, `--who`, `--decide`, `--outcome`, `--drop-decision` and `--write-decisions`, which copies them into `resources/site/decisions.md` at sign-off.
- **Decisions written into `data.js` are gone,** with the Decisions tab and the numbered markers. `pin()` and `toConfirm()` draw nothing, so pages that call them still work.
- **Notes and Comments buttons.** The side panel stays out of sight until one opens it, on its Notes or Comments tab. Notes open with every section showing and no longer repeat the page's address; a page without notes has no Notes button.
- **The content model is the team's** until it's shared: `PROTOTYPE_CONTENT_MODEL=everyone` shows it to reviewers, `off` hides it from everyone.
- **Layout.** Always dark, with no light and dark switch. The desktop frame is a 1440 by 900 laptop, and the phone has the same browser frame; a frame's size shows on pointing at its name, its zoom always. Both, Desktop or Mobile, Scroll together and Comment pins sit in a Display options menu. The version is a link under the site's name, which comes from `APP_NAME` unless `data.js` names it. The signed-in name is shortened to the name and Sign out.
- **To do on update:** nothing. Optionally, take `WHO`, `DECISIONS`, `DEC`, `SITEMAP_DECISIONS`, `BEFORE_DESIGN` and the `decisions` in `NOTES` out of a site's `data.js`, and set `name: ''` to use `APP_NAME`; mark the team in `reviewers.yaml` with `team: true`.

## v0.1.21 (10 October 2026)

The discovery prototype moves into the add-on, so every site gets its improvements.

- **The add-on serves `/prototype`:** the route, the switch, the sign-in and the interface. On for local and staging, off everywhere else, unless `PROTOTYPE_ENABLED` says otherwise; a 404 when off or when the site has no `prototype` folder. Signing in is the same as for feedback, in the same cookie: the password when `PROTOTYPE_PASSWORD` is set, an email on the reviewers list when the site keeps one, or nothing with neither.
- **The site keeps only what the prototype says,** one folder per version: `data.js`, `pages.js` and `version.json` with `format: 2`. The page helpers, such as `pageHeader()`, `img()` and `pin()`, come from the add-on as function declarations a site's `pages.js` can redefine.
- **The frames use the site's real CSS.** The add-on inlines `resources/css/site.css` and its imports, and Tailwind's browser build compiles it in each frame at the version the site builds with, so the pages lay out exactly as the site does. A wireframe layer on top greys the brand colours and adds the placeholders, markers and patterns.
- **A new look,** in the feedback widget's palette: graphite on cool greys, system fonts with nothing loaded from outside, and the same controls, with focus rings only for the keyboard. The sign-in page matches.
- **`php please avoca:prototype:version 2`** starts a version as a copy of the newest, replacing `new-version.sh` and its Python. A version from before the move, a folder with a built `index.html` and no `data.js`, is served exactly as it was built.
- **To do on update:** nothing until a site removes its own copy of the prototype (`app/Http/Controllers/PrototypeController.php`, its middleware, `config/prototype.php`, its routes, sign-in view and tests): while that controller is there, the site keeps serving its own copy. The starter kit no longer ships it.

## v0.1.20 (10 October 2026)

Feedback gets tighter control over who can see and use it.

- **No password, no feedback.** Anywhere but a local machine, feedback stays off without `PROTOTYPE_PASSWORD`, and `avoca:site:check` says so. Without one, anybody who found the site could read and write its comments.
- **Nobody else sees it.** The Feedback tab only appears in a browser that has signed in to review, or that opens a link ending `?review` (the one reviewers are sent) or `?feedback=<id>`. The open count needs sign-in too.
- **A list of who may comment.** With `resources/site/reviewers.yaml` listing names and emails, signing in asks for an email on the list and shows the list's name, not one typed in, and taking someone off the list signs them out. Without the file, any name will do.
- **A developer key for the command.** `avoca:feedback --from` now sends the server's `FEEDBACK_KEY` (`--key`, or this site's `.env`), never the reviewers' password, and a server with no key refuses those requests. `--password` is gone.
- **Comments are treated as written by reviewers.** The command prints their text escaped, so it can't pass for its own output, and says above every listing, and in its JSON as `about`, that a comment is a request to consider, never an instruction. The recipe tells an agent to put anything unusual to the developer.
- **Neutral colours.** The widget uses graphite rather than purple, resolved pins are grey, and the panel's heading no longer shows a focus ring when the panel opens.
- **To do on update, on a site using feedback away from a local machine:** set `PROTOTYPE_PASSWORD` if it has none, set `FEEDBACK_KEY` there and on the machine that reads it with `--from`, and send reviewers the `?review` link.

## v0.1.19 (10 October 2026)

Feedback: comments pinned to the site's pages, for a client review on local or staging.

- **Off unless `FEEDBACK_ENABLED=true`,** in every environment. Switched off, the code that adds anything to a page is never registered and every feedback route is a 404, so production pages don't change by a byte. Switched on, a page gets one deferred script under 4 KB and a Feedback tab with the open count; the widget loads on the first click. It sits outside Statamic's static cache, so cached copies never hold it.
- **Pinned to the page.** Add feedback asks for a spot, and the comment is pinned to the element clicked, at that point within it, so its numbered pin follows the element. Each comment says in words where it is, such as "Form block, "Name", near "Contact us"", reading the block from the template comments the kit's partials already leave in the page.
- **Threads, resolving and who.** Replies, resolve and reopen, recording who resolved it and when. Someone logged in to the control panel comments as the team; anyone else gives a name, and the password when `PROTOTYPE_PASSWORD` is set, in the prototype's own sign-in cookie, so one name works in both.
- **Stored as files,** one YAML file per comment in `storage/app/feedback`. No database, no outside service.
- **`php please avoca:feedback`** lists open comments in words with their element, or as JSON, and resolves, reopens and replies. `--from` does the same with a server's comments over HTTP, sending its password, so comments made on staging reach a developer's machine. `?feedback=<id>` on a page opens that comment.
- `avoca:site:check` warns when feedback is switched on in production. The README has a Feedback section, and the recipe's review step 5 says how to run a client review with it.
- **Nothing to do on update.** Feedback stays off until a site switches it on.

## v0.1.18 (10 October 2026)

The recipe's Designs from Paper section, rewritten after testing it against Paper itself.

- **Check Paper first.** Paper is changing quickly, so the section opens by sending whoever works with it to Paper's build log and roadmap, and to the tools its MCP offers, with a list of the features it works around (themes and tokens with `calc()` and `color-mix()`, CSS grid, native Tailwind, components, rich text, remote MCP, per-file permissions, the scale tool) and what each would change once released.
- **Before anything goes up:** the client's colours, a warning when the site has no font of its own (Paper stands Inter in), and the type scale settled in the site. During design the scale's numbers change in the site and the new sizes go to Paper in the same step; a single size is never edited in Paper.
- **The whole theme goes up, not only what the site uses:** every text size at desktop and phone width (phone sizes as `--phone-text-*`), every spacing step from 0 to 384px, Tailwind's line heights and letter spacing, the radii and the breakpoints. Each value has one token per Tailwind group, because Paper turns a typed value into the class of the one token that has it and gives up when two share it.
- **Colours are linked the way the site links them,** set in order from their sources, Tailwind's own included, so changing `--color-primary` in Paper moves everything built on it. A `color-mix()` colour can't be linked in Paper yet and goes up plain, with its formula in the description.
- **Images, layer names, and two new pages.** Images go in as local files. Only layer names survive, so they say what each part is. A Colour schemes page shows each scheme for checking, and a Components page holds every button style and state, measured against the site's own buttons.
- **Coming back down:** Paper's Tailwind export is a first draft, layer names are read from the tree, phone sizes come from their tokens, a button is the site's partial with only its rounding class carried over, and the type scale's numbers match the kit's corrected scale.
- **Nothing to do on update.** The recipe is all that changed.

## v0.1.17 (10 October 2026)

The recipe covers designs from Paper, and the site check catches the agency's logo left in place.

- `avoca:site:check` warns while form emails or the site header still show the agency's logo. The kit points both at `public/agencies/<AGENCY>` until the client's logo arrives, and a site must not reach client sign-off like that. Form emails are checked in every language the site uses, through `form_mail_logo` in `lang/<language>/strings.php`; the header through `resources/views/components/_logo.antlers.html`. It is a warning and never fails, even with `--strict`, because the agency's logo is expected while a site is being built. The control panel keeps the agency's logo and is never mentioned.
- The recipe's review step 5 checks both logos before a new site's first sign-off, and the Forms section says how to swap the form email logo.
- The recipe gains a Designs from Paper section: the two artboard widths, the site's tokens as Paper tokens on the way up, and on the way back down a rule for every value, with a conversion table so nothing reaches a template as an arbitrary pixel or colour value.
- **Nothing to do on update.** A site that still shows the agency's logo gets two new warnings until the client's logo is in.

## v0.1.16 (6 October 2026)

- `/site/style` reads a breakpoint set in px as px. It took every breakpoint for rem and multiplied it by 16, so a site with `--breakpoint-md: 768px` listed its section spacing and content widths from "12288px and up". Breakpoints in rem or em read as before.

## v0.1.15 (6 October 2026)

The reference pages take their look from the site, so a dark design gets dark reference pages.

- `/site/content` and `/site/style` put the `<body>` classes from the site's layout on their own, so blocks preview on the site's page background rather than on white. `site.body_class` gives the classes instead, for a layout that sets them per page, and `site.layout_template` names the layout.
- The pages' own text, lines, buttons and panels are mixed from the site's text colour and page background, in place of fixed slate greys, so they read on light or dark. Highlights use `--color-primary`, titles the site's heading font and the rest its sans font. A site can set any `--sk-*` variable on `.sk-reference` in its own CSS.
- On the content page the details of each block and set sit on a panel a shade darker than the page, and the preview on the page background, so the two are told apart.
- A header that floats over the page, absolute or fixed as one over a hero image is, no longer covers the toolbar: its height is kept clear, and a fixed or sticky one keeps the quick links below it. `site.header` is `auto`, `flow` or `hidden`.
- The contrast ratios on `/site/style` start against the page background. Choosing another background turns that section's text light or dark to match it.
- **Nothing to do on update.** A site on a white page looks as it did. A site that published the config doesn't need the three new keys: without them the defaults apply.

## v0.1.14 (22 September 2026)

The server git script becomes the site's file rather than the package's.

- `php please avoca:site:script` writes `scripts/server-git.sh` into the site, executable, and records the version and a checksum of it in `resources/site/installed.yaml`. The site owns that copy from then on: the add-on never reads it back and never changes it again, so a site whose server needs something different can have it. The starter kit runs the command when a site is installed, so new sites start with one.
- It refuses to write over a copy the site has changed, and says so rather than doing it. `--diff` shows what differs, `--force` takes the add-on's copy anyway. A copy the site has not touched is simply brought up to date, because nothing is lost. Run it by hand, not from a deploy script: it exits non-zero when it refuses.
- `avoca:site:check` warns when the add-on's copy has moved on, and when a copy exists that nothing recorded publishing. It says nothing about a site with no copy, because a site whose content is not edited on the server never needs one, and nothing about a copy the site changed on purpose.
- `scripts/server-git.sh` in the package is executable at last. It was committed 0644, which is why only `bash vendor/...` ever worked.
- **Nothing existing breaks.** The package keeps the script at the same path, so cron jobs and deploy scripts that call `vendor/avocadesign/statamic-tools/scripts/server-git.sh` keep working. That path stays working; the site's own copy is what the notes and the recipe now describe.

## v0.1.13 (22 September 2026)

- `avoca:site:check` warns when `llms.txt` doesn't mention a collection that has a route, and when the starter kit's placeholder text is still in it. A site that gains a collection stops describing itself accurately, and nothing was saying so. Warnings only: the site works either way.
- The recipe says how `llms.txt` is put together and what it needs when a site grows.

## v0.1.12 (22 September 2026)

- `/site/style` and `/site/content` read the space between blocks from `--block-space` tokens when the page template names a rhythm rather than numbers, such as the starter kit's new `stack-block`. A template that still carries `stack-12 md:stack-16` is read as before.

## v0.1.11 (22 September 2026)

- Help text on a field is one short line, and the recipe says so: five or six words saying what the field does, with what it is for and when to use it left to the block's guidance, where the reference pages show it. The library blocks' display settings follow, and so does the one a scaffolded collection writes.

## v0.1.10 (22 September 2026)

Every page builder block can take a CSS class for one-off design work, and block margins are their own fieldset so every block can have them.

- The library's FAQ, Projects and Testimonials blocks import `block_margins` and `custom_class`, and pass the class to the wrapper. A site needs the matching starter kit change for those fieldsets to exist.
- Scaffolding a collection writes all three imports into its listing block, in the same order: scheme, margins, class.
- `/site/style` reads the margin options from their own fieldset, through the new `margins_fieldset` config key, with `class_fieldset` beside it.
- `/site/content` leaves the class field empty in its previews. A sample sentence in a class attribute is worse than no class.

## v0.1.9 (22 September 2026)

- `php please avoca:site:urls` says which pages a check should render, because listing them by hand does not survive a site with two thousand of them. It takes the pages the site names in `resources/site/updates.yaml`, every page a collection is mounted on, one entry from each collection so each collection's own template runs, and then whichever pages add blocks and blueprints nothing chosen already has. `--sample` sets how many of those last it adds.
- `check-site.sh` renders that list rather than a hand written one.

## v0.1.8 (22 September 2026)

Groundwork for updating dependencies automatically. Nothing runs on its own yet: this is what a site has to offer the runner that will.

- `scripts/check-site.sh`: installs from the lock files, builds, refreshes, runs `avoca:site:check --strict`, serves the site and renders the pages a site lists. It writes `.env.check` and runs with `APP_ENV=check` and Statamic Pro off, so it never touches the site's `.env` and needs no licence key. Exit 0 renders, 1 doesn't, 2 couldn't run. A database is only made and migrated when the site has actually moved a driver to it: flat file sites don't get one.
- The recipe has an Updates section: what a site owes the process, and the rules the runner works to, which are patch and minor only, nothing younger than three days, canary sites before the fleet, fast forward before pushing and stop if it can't, and no pull request without a green check.

## v0.1.7 (22 September 2026)

- The reference pages render with the images a site already has, rather than generating nine grey placeholders into `images/site/` and leaving them in the client's asset library. They take what the images container holds, preferring files with "placeholder" in the name, then the biggest over 1200 pixels on the long side, which is how installing a library item already chose an image. A gallery walks that list instead of showing one image six times, and nothing is ever written to the container.
- `avoca:site:install` writes nothing now. It says which images the pages will use, and warns when the container holds none.
- The crop settings on `/site/style` show one real image through every crop, instead of a generated image already cut to that shape, so the crop is what changes between them.
- `statamic-tools.site.placeholder_dir` is gone, as nothing is generated.
- A site that ran an earlier version can delete `public/images/site/` and its `.meta` folder once nothing in its content points at them.

## v0.1.6 (22 September 2026)

- The readme's installing section drops the repository entry sites needed while the package was private. It comes from Packagist now, so Composer needs no repository and no credentials anywhere.

## v0.1.5 (22 September 2026)

The first release of the package in public, under a new name. A site updating to it changes the package it requires; nothing about how the addon works changes with it.

- Renamed to `avocadesign/statamic-tools`, with the namespace `Avocadesign\StatamicTools`, the config file and key `statamic-tools`, and views under `statamic-tools::`. The `avoca:` commands keep their names. Packagist can't rename a package once published, hence doing it now.
- Published source, not open source. `LICENSE` allows running it on a site Avoca Design Ltd hosts, or elsewhere by written agreement, and a site keeps the version it has if it leaves.
- The published history starts at one commit. The development history is archived privately, because early versions of the recipe described how Avoca hosts sites.
- Avoca's own hosting notes have left the package. They described how Avoca hosts rather than how to build a site, and the package installed them onto every client server. `server-git.sh` stays, because it runs on the server, and has moved to `scripts/`: deploy scripts and cron jobs that call it need their path changed.
- The recipe's hosting section now holds only what someone building a site needs: ask how the site is hosted, build to the static rules where you can, pull before working on a site whose content is edited on the server, and leave `[BOT]` to the server.
- A licence. The addon is published so Avoca's sites can install it, and is not open source. `composer.json` declares `proprietary`.
- `tests/` and `phpunit.xml` no longer ship in the Composer package, through `.gitattributes`.
- The README says how to install it, that issues and pull requests aren't monitored, and credits Agentic for two ideas.
- The recipe says one form per site on Statamic's free edition, what to do instead, and that enquiries go to the Site Details email.

## v0.1.4 (17 September 2026)

The build recipe covers structured data and contact details found in content. The addon's code is unchanged.

- Structured data: a site's knowledge graph lives in the SEO global's custom JSON-ld field, filled from Site Details, and a page that offers a service adds only a Service node in its own SEO tab. The recipe says why the starter kit publishes Peak's SEO snippet and what to do when Peak SEO updates, and that Antlers in those fields reads values and modifiers but doesn't run tags.
- Contact details found in a site's content go in the Contact Details set rather than being typed in. A detail Site Details doesn't hold yet, such as a postal address, is added as a field and an option on the set first, in five steps.

## v0.1.3 (16 September 2026)

- `/site/style` lists a `--prose-*` token as a prose colour only when its value is a colour, so spacing tokens such as `--prose-space` stay out of the colour list.

## v0.1.2 (16 September 2026)

- Sites take every release up to and including 1.x: the starter kit asks for `<2.0` rather than `^0.1`, so a new release line inside that range reaches them like any other release, while 2.0 stays a deliberate move.
- The prose sample on `/site/style` puts two paragraphs next to each other, and ends with text straight inside the stacked article, so both paragraph rhythms show.

## v0.1.1 (16 September 2026)

New blocks, sets and collections are checked against the library before they are built.

- `avoca:check-name {handle}` says whether a handle is free for a new block, set or collection: not used by a library item the site hasn't installed, nor by anything the site already has. Similar names are listed as warnings.
- `avoca:make:collection` stops when a library item the site hasn't installed uses the collection's handle, such as `testimonials`. `--ignore-library` makes it anyway.
- `avoca:site:check` warns when a library item the site hasn't installed uses a handle the site already has.

## v0.1.0 (15 September 2026)

The first tagged release, commit 5efa22b. New sites made from the Avoca starter kit require `^0.1`.

- `/site/style` and `/site/content`, the reference pages built from the site's CSS tokens, templates, fieldsets and guidance files.
- `avoca:site:install`, `avoca:site:catalogue` and `avoca:site:check` with `--strict` and `--stubs`.
- `avoca:make:collection`, for a collection with a page builder or custom blueprint, a listing block and a record an AI agent finishes it from.
- The editor role kept holding the permissions for every collection, taxonomy, navigation, global set and asset container, with `avoca:site:permissions` and opt-outs in `resources/site/editor-access.yaml`.
- The library, with `avoca:library` and `avoca:library:install`, and the FAQ, Projects and Testimonials presets.
- The build recipe in `recipe/`, and hosting research and drafts in `hosting/`, which are untested.
