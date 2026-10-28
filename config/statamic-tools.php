<?php

return [
    'site' => [
        // Reserved URL namespace: /site, /site/style, /site/content
        'prefix' => 'site',

        // On production the /site pages are only visible to a logged-in Statamic user.
        'protect_in_production' => true,

        // Entry point whose @import graph is scanned for @theme tokens.
        'css_entry' => 'resources/css/site.css',

        // The critical CSS entry, inlined by the layout. A site without one is checked for nothing.
        'critical_css' => 'resources/css/critical.css',

        // Type-scale tokens listed by value only, not set as a specimen (they are display sizes).
        'type_scale_note_only' => ['text-8xl', 'text-9xl'],

        // Classes the typography section demonstrates (in addition to bare elements).
        'typography_classes' => ['lede', 'subheading', 'card-heading', 'caption', 'brand-text', 'heading-size-1', 'heading-size-2', 'heading-size-3', 'heading-size-4'],

        // Where blocks and sets are defined and rendered.
        'page_builder_fieldset' => 'page_builder',
        'blocks_view_path' => 'page_builder',
        'article_fieldset' => 'article',
        'article_field' => 'article',
        'sets_view_path' => 'components',
        'image_set' => 'image',
        // The page template whose outer element spaces the blocks on every page (its stack-* classes).
        'page_template' => 'default',
        // The layout template. Its <body> classes go on the reference pages, so blocks preview on the site's own page
        // background and the pages take its colours. body_class gives them instead, for a layout that sets them per page.
        'layout_template' => 'layout',
        'body_class' => null,
        // The site's header at the top of the reference pages. auto keeps clear the space a header that floats over the
        // page (absolute or fixed) would cover, flow puts it in the page like any other element, hidden leaves it out.
        'header' => 'auto',

        // Fieldsets that hold the scheme and button options.
        // llms.txt: the global and field holding what the site tells language models about itself.
        'llms_global' => 'bots',
        'llms_field' => 'llms_content',
        'scheme_fieldset' => 'colour_scheme',
        'margins_fieldset' => 'block_margins',
        // Imported last by every block, so a site's own class wins over the block's layout classes.
        'class_fieldset' => 'custom_class',
        'button_fieldset' => 'button',

        // Knowledge-base articles linked from the top of each tab on /site/content.
        'help' => [
            'page_builder' => ['label' => 'How the page builder works', 'url' => 'https://avoca.design/kb/the-page-builder-in-statamic'],
            'text_editor' => ['label' => 'How the text editor works', 'url' => 'https://avoca.design/kb/bard-editor-statamic'],
        ],

        // Guidance files: <docs_path>/blocks/<handle>.md and <docs_path>/sets/<handle>.md
        'docs_path' => 'resources/site',

        // Where avoca:site:script puts the server scripts it publishes. The site owns what lands there.
        'scripts_path' => 'scripts',

        // The AI block catalogue the site commits: written by avoca:site:catalogue, checked by avoca:site:check.
        'catalogue_path' => 'resources/site/catalogue.md',

        // The role editors use. It is kept holding the permissions for every collection, taxonomy, navigation, global set
        // and asset container, so it works as soon as the site moves to Statamic Pro: see avoca:site:permissions.
        'editor_role' => 'editor',
        // Structures kept away from that role, as handles listed under collections, taxonomies, navigation, globals or assets.
        'editor_access_path' => 'resources/site/editor-access.yaml',

        // Option fields not to expand into per-block variants on /site/content.
        'variant_exclude' => [],
        // Repeating fields that sample a single item: a block's buttons read as one call to action.
        'sample_single' => ['buttons'],
        'margins_field' => 'block_margins', // its options render between two neighbouring blocks so the gap is visible
    ],

    // Avoca's library of ready-made blocks, sets and presets. The addon's own library/ folder is always searched
    // first; add more folders here. Items are {category}/{handle}/item.yaml, described in library/README.md.
    'library' => [
        'paths' => [],
        // Where a site records the library items it installed, and their versions.
        'installed_path' => 'resources/site/installed.yaml',
        // The asset container sample images come from. A preset's sample entries mark each image 'avoca:sample-image',
        // and installing replaces the marker with an image this container already has, as library/README.md describes.
        'sample_images_container' => 'images',
    ],

    // The discovery prototype, at /prototype. Its interface is the add-on's; what it says is the site's own, in
    // prototype/<version>/: data.js, pages.js and version.json.
    'prototype' => [
        // On for local and staging when PROTOTYPE_ENABLED isn't set, off everywhere else. Set it to override.
        'enabled' => env('PROTOTYPE_ENABLED'),
        // The site's prototype folder.
        'path' => 'prototype',
        // The Tailwind the frames compile the site's CSS with, when the site's node_modules doesn't say.
        'tailwind' => '4.3.3',
        // Who sees the Content model tab: 'team' (a control panel login, or a reviewer marked team), 'everyone', or
        // 'off'. The team only, unless PROTOTYPE_CONTENT_MODEL says otherwise.
        'content_model' => env('PROTOTYPE_CONTENT_MODEL', 'team'),
    ],

    // Feedback: comments pinned to the site's pages, for a review on local or staging. Off unless FEEDBACK_ENABLED is
    // true, in every environment: switched off, nothing is added to any page and nothing extra runs on a request.
    // Anywhere but a local machine it also needs PROTOTYPE_PASSWORD, and stays off without one.
    'feedback' => [
        'enabled' => (bool) env('FEEDBACK_ENABLED', false),
        // The prototype's password, which reviewers are given. Signing in to comment asks for it.
        'password' => env('PROTOTYPE_PASSWORD'),
        // The developer's key, for avoca:feedback --from to read and resolve a server's comments from another machine.
        // Never give it to reviewers: it acts as the team. Without one, the command's routes refuse every request.
        'key' => env('FEEDBACK_KEY'),
        // The people who may comment, by name and email, when the site lists them. Without the file, any name will do.
        'reviewers_path' => 'resources/site/reviewers.yaml',
        // The cookie that remembers who is commenting. The prototype's sign-in sets the same one, so a name given in
        // either place works in both.
        'cookie' => 'prototype',
        // One YAML file per comment and its replies, in git with the rest of the content: made locally they go up with a
        // push, and made on a server whose content is edited there, its git script commits them. Relative to the site.
        'path' => 'content/feedback',
        // Whether a change to a comment runs the site's scripts/server-git.sh commit --now once the response is sent, so
        // a comment made on the server is committed, and pushed, within seconds rather than at the script's next quiet
        // spell. Only when the site has the script and STATAMIC_GIT_ENABLED is true. Unset, on anywhere but a local
        // machine; FEEDBACK_COMMIT=false turns it off.
        'commit' => env('FEEDBACK_COMMIT'),
        // Who hears about new comments and replies from reviewers: the reviewers marked team: true, and these
        // addresses, comma separated. Laravel's scheduler checks every five minutes and sends one digest for a review:
        // once reviewers have said nothing for digest_quiet_minutes, or once the oldest comment in it has waited
        // digest_hold_minutes, whichever comes first.
        'notify' => env('FEEDBACK_NOTIFY'),
        'digest_quiet_minutes' => (int) env('FEEDBACK_DIGEST_QUIET', 30),
        'digest_hold_minutes' => (int) env('FEEDBACK_DIGEST_HOLD', 120),
        // Comments made, and replies added, in the last recent_days are flagged as new, in the panel and the command.
        'recent_days' => 7,
        // Front-end pages that never get the widget.
        'exclude' => [],
    ],
];
