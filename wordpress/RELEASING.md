# Releasing the Xenarch WordPress Plugin

## Build the ZIP

```bash
cd wordpress
bash scripts/build-release.sh
```

Produces `xenarch-{version}.zip` in the `wordpress/` directory.

## Version Bump

Before building a release, update the version in three places:

1. `xenarch.php` line 6: `* Version: X.Y.Z`
2. `xenarch.php` line 24: `define( 'XENARCH_VERSION', 'X.Y.Z' );`
3. `readme.txt` line 7: `Stable tag: X.Y.Z`

Add a changelog entry in `readme.txt` under `== Changelog ==`.

## GitHub Release

```bash
# Tag
git tag -a vX.Y.Z -m "Release X.Y.Z"
git push origin main --tags

# Create release with ZIP attached
gh release create vX.Y.Z \
  --title "Xenarch X.Y.Z" \
  --notes "See changelog in readme.txt" \
  wordpress/xenarch-X.Y.Z.zip

# Clean up local ZIP
rm wordpress/xenarch-X.Y.Z.zip
```

## WordPress.org SVN Deployment

After WordPress.org approves the plugin, they provide SVN access.

### SVN structure

```
xenarch/
  trunk/       # Latest version (mirrors ZIP contents)
  tags/X.Y.Z/  # Tagged releases
  assets/      # Listing assets (banners, icons, screenshots)
```

### First-time setup

```bash
svn co https://plugins.svn.wordpress.org/xenarch/ svn-xenarch
```

### Deploy a release

```bash
cd svn-xenarch

# Update trunk from ZIP
rm -rf trunk/*
unzip /path/to/xenarch-X.Y.Z.zip -d /tmp/xenarch-release
cp -r /tmp/xenarch-release/xenarch/* trunk/

# Tag the release
svn cp trunk tags/X.Y.Z

# Upload listing assets (first time or when changed)
cp /path/to/wordpress/.wordpress-org/banner-1544x500.png assets/
cp /path/to/wordpress/.wordpress-org/banner-3088x1000.png assets/
cp /path/to/wordpress/.wordpress-org/icon-256x256.png assets/
cp /path/to/wordpress/.wordpress-org/icon-512x512.png assets/
cp /path/to/wordpress/.wordpress-org/screenshot-*.png assets/

# Stage and commit
svn add --force trunk/ tags/ assets/
svn status | grep '!' | awk '{print $2}' | xargs -r svn rm
svn ci -m "Release X.Y.Z"
```

### Asset naming

WordPress.org expects specific filenames in `assets/`:
- `banner-1544x500.png` (hi-DPI banner)
- `icon-256x256.png`
- `screenshot-1.png` through `screenshot-N.png` (matched to `== Screenshots ==` in readme.txt)

Only PNGs and SVGs. Do NOT upload `listing-copy.md` or `preview.html`.

## Plugin Check

Run before every release on the test server (gate.xenarch.dev):

```bash
ssh root@5.78.183.39 "docker exec gate-wordpress-wordpress-1 wp plugin check xenarch --format=table --allow-root"
```

0 errors required. Warnings are acceptable.
