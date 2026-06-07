# WordPress.org Plugin Submission Checklist

## Status
- [x] Plugin meets WP.org slug rules: `qtale-tts` (lowercase, alphanumeric, hyphens only)
- [x] Stable tag in readme.txt matches `Version:` header (currently 2.6.25)
- [x] GPL-compatible license (GPLv2-or-later)
- [x] External services disclosure section added to readme.txt
- [x] No remote-loaded code (only audio files / static assets)
- [x] No third-party tracking
- [x] LICENSE file in repo root
- [ ] **Tested up to** bumped to current WP (we currently say 6.7 — verify on a 6.7 install before submission)
- [ ] Screenshots in `/assets/` directory (referenced as `screenshot-1.png` etc.)
- [ ] Plugin banner + icon for WP.org listing (`/assets/banner-1544x500.png`, `/assets/icon-256x256.png`)
- [ ] Optional: `assets/screenshot-*.png` for the screenshot gallery on plugin page

## Submission Process

### Step 1: Create WordPress.org account
- Go to https://login.wordpress.org/register
- Username: `qtale` (or `activeweb` if `qtale` is taken)
- Use **nilsotto@activeweb.no** email

### Step 2: Submit plugin for review
- Go to https://wordpress.org/plugins/developers/add/
- Upload ZIP of plugin (build via GitHub Actions tag, or manual `zip -r`)
- Fill out form (plugin name, description, contributors)
- Note: WordPress.org expects the plugin folder inside the ZIP to be `qtale-tts/`
  (no nesting). Verify with `unzip -l qtale-tts-2.6.25.zip | head`.

### Step 3: Wait for review (7-14 days)
- Initial automated checks happen within ~24h
- Manual review by WP.org plugin team takes 5-14 days typically
- Watch the email `nilsotto@activeweb.no` for review comments
- Common feedback to address:
  - Sanitize all `$_GET`/`$_POST`/`$_REQUEST` input
  - Escape all output via `esc_html()`, `esc_attr()`, `esc_url()`
  - Use `wp_remote_get/post` (we do via cURL → may need to switch)
  - No fwrite/file_put_contents on user-controlled paths
  - No bundled minified libraries without source

### Step 4: Approval → SVN access
- Approval email contains SVN URL: `https://plugins.svn.wordpress.org/qtale-tts/`
- WP.org uses SVN (not git!) — you'll have a separate workflow for SVN releases:
  ```
  svn co https://plugins.svn.wordpress.org/qtale-tts/ wp-qtale-svn
  cd wp-qtale-svn
  # Copy plugin files to trunk/
  cp -r ../qtale-tts/* trunk/
  # Tag the release
  svn cp trunk tags/2.6.25
  svn ci -m "Release 2.6.25"
  ```

### Step 5: Setup `/assets/` for plugin page
- `banner-772x250.png` (low-res) + `banner-1544x500.png` (Retina)
- `icon-128x128.png` + `icon-256x256.png` (Retina)
- `screenshot-1.png` ... `screenshot-N.png` (referenced in readme.txt)
- These go in SVN's `/assets/` directory, NOT in the plugin ZIP

## Potential review blockers (address pre-submission)

1. **External API requirement** — WP.org has accepted plugins that REQUIRE a paid
   service (e.g., Akismet, Jetpack, Algolia), but the External Services disclosure
   must be crystal clear. We've added it. ✓
2. **API key field stored in plain text** — we store API key in WP options.
   This is fine per WP.org rules (it's user-provided credentials), but maybe
   add `update_option`-encryption for extra polish.
3. **Cloud Storage credentials** — these are sent to api.qtale.no for encrypted
   storage. Disclosed in External services section. ✓

## Branding/positioning for WP.org listing

- **Title:** Q-Tale TTS — Norwegian Premium Text-to-Speech for WordPress
- **Tagline:** Embed AI-generated audio narration with 25+ languages, Sámi support, and your own cloud storage
- **Tags:** text-to-speech, tts, accessibility, audio, podcast, voice, narration, norsk, ai, samisk
- **Selling point #1:** Only WP plugin with Sámi (Giellalt) TTS voices
- **Selling point #2:** Cloud Storage offload to YOUR own FTP/S3 (rare for TTS plugins)
- **Selling point #3:** GDPR-friendly (Norwegian/EU hosting, no US transfer)

## Post-submission marketing

- Soft launch: GitHub stars + tweets from @activeweb
- Hard launch: WordPress.org listing live → press release to:
  - WordPress.org Plugin Directory (automatic via submission)
  - Norwegian WP community (FB groups)
  - Accessibility community (a11y matters)
  - Local press for Sámi-support angle (NRK Sápmi, ITavisen)
