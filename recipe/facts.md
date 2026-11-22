# Facts and proof

Read this before you write or change any copy on an Avoca site: a page's text, a block's heading, a case study, a post, a meta description or `llms.txt`. The recipe's [Facts and proof](recipe.md#facts-and-proof) section sends you here.

Every claim on a client's site has to be true, and the claims that persuade a reader are the ones only the client could make: a real number, a real customer's outcome, something learned from doing the work. So each site keeps the facts its pages may state in one file, `resources/site/facts.md`. Each fact is checked once, there, written into every page that needs it, and changed everywhere in one pass when it changes.

Adapted from Ilias Ism's proof-kb (github.com/Illyism/seo-skills, MIT licence), cut down to one client's website.

## Rules that come first

- **Never invent.** Don't write a number, customer, quote, outcome, sector, place, award or year unless it is in `resources/site/facts.md`, the site's content or the brief, or the developer has confirmed it. Where a page needs a fact nobody has given you, write `[NEEDS PROOF: what is needed]` where the fact belongs, and add it to Open questions in `facts.md`. A visible gap gets filled. An invented number goes live.
- **Say what the business can do, not who it has done it for, until it has.** Write a sector, place or service the client wants but hasn't worked in yet as something they offer or would welcome, never as experience. `facts.md` lists these under Not yet.
- **Only what is public or approved.** State a customer outcome only when it is already published, as a case study, a testimonial entry or on the customer's own site, or when the client has approved the wording. Draft one that isn't with `[CLIENT TO APPROVE]` beside it. Form submissions, enquiries, invoices and anything from the client's private systems never go in `facts.md` or on a page.
- **Testimonials stay in their collection.** `facts.md` points at the entry and never copies the words. A page never turns a testimonial into a claim it doesn't make.
- **Real pictures are proof, illustrations aren't.** A photo of real work, or a screenshot of a real result, can back a claim. Never present a generated or mocked up image as the client's own work or results.
- **`facts.md` is for the team.** It lives in `resources/site/`, which is never served, and nothing on the site links to it. Facts reach the site only as sentences written for each page.

## The file

A site made from the starter kit has `resources/site/facts.md`. For a site made before that, create it the first time copy needs it, with `title: Facts` in its front matter and these sections:

- **The business:** when it started, where it is and where it works, who runs it, its accreditations and memberships.
- **What it offers:** the services and products, and any prices the site states.
- **Numbers:** what a page may quote, such as years trading, jobs done or people employed, each with the date it was counted.
- **Customer outcomes:** what changed for a customer, each pointing at the published case study or testimonial entry, or marked approved with the date the client approved it.
- **Know-how:** what the client knows from doing the work, in their words.
- **Not yet:** the sectors, places and services the client wants but hasn't worked in.
- **Open questions:** each `[NEEDS PROOF]` still open, with the page it is on.

Each fact is one line: the fact, then in brackets where it came from, when it was checked, and whether it can be published.

```
- Founded in 2006 by its two directors (source: discovery call; checked 2026-03-03; public)
- 140 kitchens fitted since 2015 (source: the client's job list, counted 2026-03-10; checked 2026-03-10; approved)
```

`public` means the fact is already published, `approved` that the client has approved saying it, and `internal` that it may guide the writing but never appears on a page. Give a number the date it was counted, because it goes stale.

## Starting the file

Fill it from what the site already has before asking anyone: the brief and the discovery notes, Site Details, the structured data in the SEO global, the testimonials and case studies, and the client's current site. Write only what those say, with each one as its source. Then ask for the gaps, as the next section says.

## Asking for what is missing

When a page is short of proof, put three to five questions to the developer, each answered by one fact: a number, an outcome or a lesson. Say where the answer might already be, such as a case study, the client's job list or their reviews, so they can point to it rather than remember it. Save each answer in `facts.md` before you write the page. Questions nobody can answer yet go under Open questions.

## Writing a page

1. Read `facts.md` and the page's entry.
2. Open with the reader's situation and the strongest real fact that answers it, in the first two sentences.
3. Write each page's sentences fresh. The same fact can appear on several pages, but the same sentence shouldn't.
4. Change the entry in `content/`, never a copy of it, and only what the fact needs.
5. In the plan, as [review step 1](recipe.md#1-a-plan-the-developer-approves) asks, list the facts the copy uses and every marker it leaves.

## When a fact changes

A new number, a changed price, a new accreditation, or a customer outcome the client has now approved:

1. Change its line in `facts.md`, with the new source and date.
2. Find every page that states it. Search for the old value itself, in the ways a page might write it:
   ```
   grep -rnF -e '140 kitchens' -e '140 new kitchens' content
   ```
   Open each result and check it is the same fact, because the same number can mean something else.
3. Rewrite each mention for its own page. Change a page's date only where a fact on it changed.
4. List the pages you changed, and what changed on each, for the developer.

## Checking a page

List every claim on the page: numbers, years, names, sectors, places, prices and awards. Trace each one to `facts.md` or to its source.

| Claim | Source | Status |
| --- | --- | --- |
| "since 2006" | facts.md | ok |
| "trusted by hundreds of homeowners" | none | take out: no count |

Status is one of: ok, out of date (with the current fact), `[NEEDS PROOF]` or take out.

## Before launch

No page goes live with a marker. `php please avoca:site:check` warns for every file in `content/` that still has `[NEEDS PROOF` or `[CLIENT TO APPROVE` in it, and [Launch](recipe.md#at-launch) in the recipe checks for them. Settle each one with a fact, or take the claim out.
