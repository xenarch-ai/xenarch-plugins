# Prepare JED Submission Deliverables for Xenarch Joomla Plugin

**Assignee:** Andrey | **Priority:** High | **Labels:** content, marketing

Prepare all assets and copy for [JED submission](https://extensions.joomla.org/support/knowledgebase/submission-requirements/). All text SEO-optimized for: *AI bot payments, content monetization, micropayments, Joomla AI paywall, bot traffic monetization, x402, USDC payments, charge AI bots*.

---

## Copywriting

- [ ] **Extension Name** — unique on JED, no "Joomla" prefix, no version/price/URL in name. Must match XML `<name>` tag. Suggested: `Xenarch` or `Xenarch AI Payment Gate`
- [ ] **Short Description** (~150 chars) — one-liner for search results. SEO-rich
- [ ] **Full Description** (HTML) — structure: intro paragraph → bullet feature list → how it works → requirements → docs links. No features not in this version. No promo for paid upgrades

## Images

- [ ] **Logo/Icon** — high-res extension logo
- [ ] **Screenshots (up to 8)** — admin config panel, bot detection gate, payment flow, pay.json config. Real content, no lorem ipsum

## URLs (all must work, no shortened links)

- [ ] **Download** — GitHub releases page (no file-sharing sites)
- [ ] **Demo** — working non-production demo site
- [ ] **Support** — GitHub Issues (NOT Joomla Forums)
- [ ] **Documentation** — setup/config docs
- [ ] **Website** — xenarch.dev or xenarch.com

## Metadata

- [ ] **Version** — must match XML `<version>` tag
- [ ] **Joomla compat** — J4, J5, J6
- [ ] **Type** — Free download
- [ ] **Category** — check similar extensions for best fit
- [ ] **Tags** — payments, security, bot detection, AI, monetization
- [ ] **Languages** — English (minimum)

## Technical (verify before submit)

- [ ] `LICENSE` file (GPL v2) in repo + `@license` header in all PHP files + `<license>` in XML
- [ ] `<updateservers>` in XML pointing to valid update XML ([required](https://extensions.joomla.org/support/knowledgebase/submission-requirements/joomla-update-system-requirement/))
- [ ] `defined('_JEXEC') or die;` in all PHP files
- [ ] Installs/uninstalls cleanly via Joomla installer, no 777 perms
- [ ] No encrypted/obfuscated code, no blocking call-home
- [ ] Run [JED Checker](https://extensions.joomla.org/extension/jedchecker/) on the package
- [ ] JED developer account created, submitted by author

---

*Review takes 1-4 weeks. Max 3 listings at a time.*
