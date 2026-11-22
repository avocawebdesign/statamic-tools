# Writing copy

Read this, and [facts.md](facts.md), before you write or change copy on an Avoca site: a page's text, a block's heading, a case study, a post, a meta description, an email or a social post. The recipe's [Writing copy](recipe.md#writing-copy) section sends you here.

`facts.md` decides what a page may claim. This page decides how it sounds: written from the customer's situation, in their language, with none of the habits that make text read as machine-written. Readers can't always name those habits, but they notice them and trust the page less.

## The site's files

Three files in `resources/site/`, all for the team and never served:

- **`facts.md`**: what the pages may claim. See [facts.md](facts.md).
- **`voice.md`**: how this business sounds, and who it writes for. It has two parts. **Voice**: who "we" is, the spelling (NZ, AU or UK English), words the business uses and never uses, the call to action, and a right and a wrong sentence for each rule. **Audiences**: for each kind of customer, their situation when they find the site, what's on their mind, and what they need to hear. Audiences point at `facts.md` for proof rather than listing it.
- **`customer-voice.md`**: customers' own words, sorted by theme, each with its source, so copy can echo how customers describe their problem rather than how the business does.

A site made before the kit shipped these creates them the first time copy needs them, with `title: Voice` or `title: Customer voice` in the front matter.

## Capturing customer voice

Sources, most useful first: short interviews with clients, discovery calls and meeting notes, testimonials and reviews, briefs, enquiries, support requests.

- **Copy their words exactly.** Paraphrase loses the value. Mark a cut with `…`.
- **Give each line its source, date, audience, and the moment** (before they chose the business, during the work, long after).
- **Testimonials stay in their collection.** Quote a short phrase and name the entry; don't copy the whole testimonial into the file.
- **Enquiries, emails and calls are recorded without a name** or anything that identifies the person ("arts trust manager, discovery call, Nov 2026"). They're for learning the language. They never become a fact in `facts.md`, and they never go on a page without the person's permission.
- **Look for** what they were trying to get done (the practical job, how they want to feel, how they want to be seen), their pains, what triggered the search, the outcome they wanted in their words, the alternatives they weighed, and what nearly stopped them.
- **Judge what a theme can carry.** High confidence needs three or more independent sources, raised unprompted. Two sources, or prompted only, is medium. One source is low: use it to ask better questions, not to write a headline. Testimonials come from happy clients writing for an audience, so they never tell you what nearly stopped someone; only an interview does.

Interview questions, asked in order:

1. What was going on when you first started thinking about a new website?
2. What had you tried, or put up with, before that?
3. What nearly stopped you going ahead?
4. Who else did you look at, and what made you pick us?
5. What surprised you once we were working together?
6. If a friend asked about us, what would you tell them?
7. What's different now?

## Writing a page

1. Read `voice.md`, the page's audience, and the facts the page will use.
2. Pick three to six lines from `customer-voice.md` that fit this reader. Write from their language.
3. Open with the reader's situation, not the offer. Then the strongest real fact that answers it, as `facts.md` says.
4. Write the plain version first, then make it warmer. Warmth comes from understanding the reader, not from adjectives.
5. One idea per sentence. Vary the length. A sentence that would fit on any competitor's site unchanged isn't finished.
6. Write as "we" to "you". Never refer to the business in the third person in its own voice.
7. Run the check below, and fix what it finds.
8. Read it aloud as the owner talking to that customer across a table. Cut anything they wouldn't say.

## What never goes in

Client quotes are exempt: a word a customer said stays in their quote.

**Structures**

| Pattern | Example | Instead |
| --- | --- | --- |
| Rhythmic lists of three | "plans it, builds it and keeps it working" | The one that matters, or two specific ones |
| Not X but Y | "Not just a website, but a growth engine" | Say Y plainly |
| It's not about X. It's about Y. | "It's not about pixels. It's about people." | Say Y plainly |
| Fragment punchlines | "Fast. Secure. Yours." | A real sentence |
| Question, then its answer | "The result? A site you love." | State it |
| Rhetorical question openers | "Tired of a website that doesn't work?" | Describe their situation in a statement |
| Announcing the point | "Here's what you need to know", "The hard part is…" | Make the point |
| Arguing with no one | "To be clear, this isn't about…", "A tempting approach would be…" | Delete it, keeping any real claim |
| Tidy closers | "That's the difference." after a paragraph that showed it | End on the last fact |
| Padding "-ing" tails | "…, showcasing our commitment to quality" | Cut the tail |
| Avoiding is and has | "serves as", "stands as", "boasts" | is, are, has |
| Negation lists | "No setup, no waiting, no fuss." | Say what does happen |
| Truism openers | "In today's digital world…" | Start with their situation |
| Restating closes | "In short…", "At the end of the day…" | Stop at the last useful point |
| "From X to Y" spans | "From small businesses to large organisations" | Name who it's for |
| Dashes | three in a paragraph | None in headings, ledes, calls to action, meta descriptions or ads; one per paragraph in body copy |

**Words and phrases never used in the business's own voice:** delve, elevate, unlock, unleash, empower, harness, leverage, supercharge, game-changer, cutting-edge, state-of-the-art, next level, world-class, seamless, robust, synergy, holistic, ever-evolving, landscape, realm, tapestry, testament, embark, journey (for a project), navigate (figurative), look no further, one-stop shop, stand out from the crowd, in today's digital age, we pride ourselves, passionate about, dedicated team, team of experts, solutions, digital presence, transformative, revolutionise, nestled, boasts, meticulous, curated, foster, thrive, crucial, pivotal, vital, at the heart of, more than just, peace of mind, every step of the way, showcase, underscore, vibrant, renowned, breathtaking, groundbreaking, commitment to, say goodbye to.

**Use sparingly:** tailored, bespoke, crafted, ensure, streamline, boost, comprehensive, innovative, effortless, stunning, powerful, truly, very, amazing, unique, perfect, "Let's", exclamation marks, emoji.

## Checking a draft

```
node vendor/avocadesign/statamic-tools/scripts/lint-copy.mjs draft.md --self "Business name"
```

Or pipe text in. `--self` names the business, so the check can flag it talking about itself in the third person. A `ban` is fixed before anyone sees the draft. A `check` is fixed, or kept for a reason you can give. Quoted passages over 40 characters are skipped as customer quotes. The script exits 1 when it finds a ban.

If the agent has a humanizer skill (such as blader/humanizer), run it after the check, giving it `voice.md` and a few lines of `customer-voice.md` as the writing sample. The script catches words and simple shapes; a humanizer pass catches structure across sentences.

## Presenting copy

Give one recommended version, then two to four short alternatives, each with a one-line reason. List the facts the copy uses and every `[NEEDS PROOF]` or `[CLIENT TO APPROVE]` marker it leaves, as the recipe's plan step asks.

## Answer options

AI answers and featured snippets lift short passages that answer a question on their own. Don't reshape a page to provide them: the copy has to sell, and read cleanly as a page. Offer them instead.

When a section touches a question the site tracks (`seo/tracking/questions.csv`, where the site has one) or one the customer voice shows people asking, add an answer option to the alternatives:

- Two to four sentences that answer the question without the rest of the page, with the subject named rather than "this" or "it".
- One fact from `facts.md` where there is one: a number, a place, a time frame.
- The same voice and checks as everything else. It is an alternative, not a block of FAQ text.
- Say which question it answers, in the one-line reason.

Whoever approves the copy chooses. If an option is used, mark the question `answered` in `questions.csv` with the page. If none fits the page, leave the question `not yet`: it may need its own page or section, which is a decision for the page plan, not the copy.
