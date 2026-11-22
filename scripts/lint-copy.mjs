#!/usr/bin/env node
// Flags machine-sounding habits and agency clichés in copy. The rules and their reasons are in
// recipe/copy.md, "What never goes in".
// Usage: node vendor/avocadesign/statamic-tools/scripts/lint-copy.mjs file.md [more files] [--self "Business name"]
//        or pipe text in. Exits 1 when it finds a ban.
// Text inside “curly” or "straight" quotes longer than 40 chars is treated as a client quote and skipped.

import { readFileSync } from 'node:fs';

const BAN = [
  'delve', 'elevate', 'unlock', 'unleash', 'empower', 'harness', 'leverag(e|es|ing)', 'supercharge',
  'game[- ]chang(er|ing)', 'cutting[- ]edge', 'state[- ]of[- ]the[- ]art', 'next level', 'world[- ]class',
  'best[- ]in[- ]class', 'seamless(ly)?', 'robust', 'synerg(y|ies)', 'holistic', 'ever[- ]evolving',
  'fast[- ]paced', 'digital landscape', 'realm', 'tapestry', 'testament to', 'embark', 'journey',
  'navigat(e|ing) the', 'look no further', 'one[- ]stop[- ]shop', 'stand out from the crowd',
  "in today'?s (digital )?(age|world)", 'we pride ourselves', 'passionate about', 'dedicated team',
  'team of experts', 'solutions?', 'digital presence', 'online presence', 'drive engagement',
  'transformative', 'revolutioni[sz]e', 'nestled', 'boasts?', 'meticulous(ly)?', 'curated', 'foster',
  'thrive', 'crucial', 'pivotal', 'vital', 'paramount', 'at the heart of', 'more than just',
  'peace of mind', 'whether you.re an? \\w+( \\w+)? or an? ', "here'?s the thing",
  'every step of the way', 'works as hard as you', 'we don.t just', 'not just an? .{1,40}?,? but',
  "it'?s not about .{1,40}?\\. it'?s about", 'imagine an? ', 'in short,', 'at the end of the day',
  'from (small|startups?).{1,30} to (large|enterprise)', 'our team of', 'first impressions',
  // from blader/humanizer (staging, inflation, chatbot residue)
  "let'?s (dive|explore|break)", "here'?s what you need to know", 'without further ado', 'the real question',
  'at its core', 'what really matters', 'to be clear', "don'?t get me wrong", 'a tempting approach',
  'one might be tempted', 'let that sink in', 'read that again', 'the future looks bright', 'exciting times',
  'stands as', 'serves as', 'plays an? (key|crucial|pivotal|vital) role', 'underscor(e|es|ing)',
  'showcas(e|es|ing)', 'interplay', 'intricac(y|ies)', 'intricate', 'vibrant', 'garner(s|ed)?',
  'bolster(s|ed)?', 'deep dive', 'renowned', 'breathtaking', 'groundbreaking', 'diverse array',
  'commitment to', 'say goodbye to', 'unlock the power', 'reimagined', 'i hope this helps', 'great question',
];

const CHECK = [
  'tailored', 'bespoke', 'crafted', 'ensur(e|es|ing)', 'streamlin(e|ed|es|ing)', 'boost', 'comprehensive',
  'innovative', 'effortless(ly)?', 'stunning', 'powerful', 'simply', 'truly', 'very', 'exciting',
  'amazing', 'incredible', 'unique', 'perfect', 'ultimately', 'essentially', "let'?s", 'our team',
  'can help (you )?to', 'the result\\?', 'why\\? ', 'ready to (take|talk|get)',
  // from blader/humanizer (weak alone)
  'the (hard|real|best|new|tricky) part', "this isn'?t (about|mainly)", "i'?m not saying", 'additionally',
  'enhanc(e|es|ed|ing)', 'valuable', 'align(s|ed)? with', 'associated with', 'could potentially',
  "it'?s also possible", 'highlighting', 'reflecting', 'fostering', 'ensuring',
];

const args = process.argv.slice(2);
const selfAt = args.indexOf('--self');
const selfName = selfAt >= 0 ? args.splice(selfAt, 2)[1] : null;
const esc = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
if (selfName) BAN.push(`${esc(selfName.toLowerCase())} (hosts|builds|designs|offers|provides|helps|is|has|will|can|creates|delivers)`);

const rx = (words) => new RegExp(`\\b(${words.join('|')})\\b`, 'gi');
const banRx = rx(BAN);
const checkRx = rx(CHECK);
// "x, y and z" with short items: the rhythmic triad
const triadRx = /\b(\w+(?: \w+){0,3}), (\w+(?: \w+){0,3}),? (and|or) (\w+(?: \w+){0,3})\b/gi;
const negationRx = /\bno \w+(?: \w+)?, no \w+/gi; // "no setup, no waiting"
const fragmentRx = /(?:^|[.!?]\s+)(\w+\.\s+\w+\.\s+\w+\.)/g; // "Fast. Secure. Yours."

function stripQuotes(line) {
  return line.replace(/[“"][^”"]{40,}[”"]/g, (m) => ' '.repeat(m.length));
}

function lint(name, text) {
  const hits = [];
  text.split('\n').forEach((raw, i) => {
    const line = stripQuotes(raw);
    const n = i + 1;
    for (const m of line.matchAll(banRx)) hits.push(['ban', n, m[0], 'trope / cliché']);
    for (const m of line.matchAll(checkRx)) hits.push(['check', n, m[0], 'suspicious word']);
    for (const m of line.matchAll(triadRx)) hits.push(['check', n, m[0], 'list of three: rhythmic triad?']);
    for (const m of line.matchAll(fragmentRx)) hits.push(['ban', n, m[1], 'fragment punchline']);
    for (const m of line.matchAll(negationRx)) hits.push(['ban', n, m[0], 'negation list: say what does happen']);
    if (/^\s*#/.test(line) && /—|–/.test(line)) hits.push(['ban', n, 'dash in heading', 'no dashes in headings']);
    const starts = line.split(/(?<=[.!?])\s+/).map((x) => x.trim().split(/\s+/)[0]?.toLowerCase()).filter(Boolean);
    for (let k = 2; k < starts.length; k++)
      if (starts[k] === starts[k - 1] && starts[k] === starts[k - 2] && starts[k].length > 1)
        hits.push(['check', n, `"${starts[k]}…" ×3`, 'repeated sentence openings']);
    if (/\[(NEEDS PROOF|CLIENT TO APPROVE)/.test(raw)) hits.push(['check', n, 'marker', 'settle it from resources/site/facts.md before it goes live']);
    const dashes = (line.match(/—| – /g) || []).length;
    if (dashes > 1) hits.push(['check', n, `${dashes} dashes`, 'one dash per paragraph at most']);
    if (/!/.test(line)) hits.push(['check', n, '!', 'exclamation mark in our voice?']);
    if (/^\s*(#+\s*)?[^.?!]*\?\s*$/.test(line) && line.trim().length < 80 && !/^#/.test(line.trim()))
      hits.push(['check', n, line.trim(), 'rhetorical question?']);
  });
  const bans = hits.filter((h) => h[0] === 'ban').length;
  console.log(`\n${name}: ${bans} ban, ${hits.length - bans} check`);
  for (const [sev, n, what, why] of hits) console.log(`  ${sev.padEnd(5)} L${n}  "${what}"  ${why}`);
  return bans;
}

const files = args;
let bans = 0;
if (files.length) for (const f of files) bans += lint(f, readFileSync(f, 'utf8'));
else bans += lint('stdin', readFileSync(0, 'utf8'));
process.exitCode = bans ? 1 : 0;
