# Releasing

This package is distributed through [Packagist](https://packagist.org) as
`demografix/demografix`. Packagist does not need a publish step in CI. It reads
new versions directly from the git tags on this repository, so a release is just
a pushed `vX.Y.Z` tag.

## One-time setup

Done once per repository by a maintainer with admin access on
`DemografixGenderize/demografix-php` and an account on packagist.org.

1. Submit the package on Packagist. Go to <https://packagist.org/packages/submit>
   and enter the repository URL
   `https://github.com/DemografixGenderize/demografix-php`. Packagist reads the
   package name (`demografix/demografix`) from `composer.json`.
2. Install the Packagist GitHub App so tags sync automatically. From the package
   page on Packagist follow the "GitHub Hook" or "Auto-Update" instructions, or
   install <https://github.com/apps/packagist> on the repository. After this,
   every pushed tag triggers Packagist to pick up the new version. No API token
   or webhook secret is stored in this repository.

There are no GitHub Actions secrets or OIDC trusted publishers to configure.
Packagist pulls from the public git tags, and the release workflow only needs
the built-in `GITHUB_TOKEN` to create the GitHub Release.

## Cutting a release

1. Update the version. Bump the `USER_AGENT` constant in `src/Client.php` (for
   example `demografix-php/0.2.0`). The release workflow checks that the pushed
   tag matches this value and fails the release if they differ.
2. Commit the change.

   ```
   git add src/Client.php
   git commit -m "Release 0.2.0"
   ```

3. Tag and push the tag.

   ```
   git tag v0.2.0
   git push origin main
   git push origin v0.2.0
   ```

Pushing the tag triggers `.github/workflows/release.yml`, which runs the test
suite as a final gate and creates the GitHub Release. The Packagist GitHub App
detects the new tag and publishes the version on Packagist.

## Consuming the package

```
composer require demografix/demografix
```
