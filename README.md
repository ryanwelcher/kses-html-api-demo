# wp_kses Parser Comparison

WordPress trunk reimplements `wp_kses()` on the HTML API, expected to ship in 7.2 or 7.3 ([r64233](https://core.trac.wordpress.org/changeset/64233), [#66208](https://core.trac.wordpress.org/ticket/66208)). `wp_kses()` isn't part of the HTML API. It's a separate sanitizer that now uses the HTML API's `WP_HTML_Tag_Processor` to parse its input.

This plugin runs the same HTML through `wp_kses()` with the legacy parser and with the new parser, and shows both results side by side under **Tools → KSES Demo**.

It works by flipping the `wp_kses_force_legacy_parser` filter around each call. Core reads that filter every time `wp_kses()` runs, so both versions can run in one request.

The sample inputs come from the Make Core post: [Progress Report: wp_kses()](https://make.wordpress.org/core/2026/10/07/progress-report-wp_kses/). You can also paste your own HTML into the form at the top of the page.

## Run it

You need WordPress nightly (7.2-alpha, r64233 or later).

```bash
npx @wp-playground/cli@latest server \
  --wp=nightly \
  --mount=.:/wordpress/wp-content/plugins/kses-html-api-demo \
  --blueprint=./blueprint.json
```

The blueprint logs you in, activates the plugin, and opens the demo page.

## Browser version

There's also a no-install version at **https://wp-kses-tester.space.fast/**. It runs WordPress nightly in your browser with [WordPress Playground](https://wordpress.org/playground/), so you can paste HTML and compare both parsers without setting anything up. Its source lives in [ryanwelcher/spacefast-demos](https://github.com/ryanwelcher/spacefast-demos/tree/trunk/wp-kses-tester).
