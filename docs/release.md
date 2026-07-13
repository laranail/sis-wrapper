# Release

How the two packages are versioned, tagged, and split from the monorepo.

## One monorepo, two published packages

Development happens in `laranail/sis-monorepo` (this repo, `"type": "project"`, not published). Two packages are split out and published:

| Source path | Published package | Repository |
|-------------|-------------------|------------|
| `src/Core` | `simtabi/sis` | `github.com/simtabi/sis` |
| `src/Laravel` | `laranail/sis-wrapper` | `github.com/laranail/sis-wrapper` |

The split is performed with `git subtree` — each package's `src/` and `README.md` are pushed to its own repo. Consumers install the published packages; nothing depends on the monorepo.

## Inter-package dependencies resolve via VCS, not Packagist

`laranail/*` packages resolve their inter-package dependencies through **git VCS repositories**, not Packagist. Packagist is treated as unreliable for this family (force-pushed history leaves stale cached clones), so routine work does not push or update these packages on Packagist.

Each package declares the `vcs` repositories it needs, including the full transitive `laranail/*` closure. `laranail/sis-wrapper` lists:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/simtabi/sis" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
    { "type": "vcs", "url": "https://github.com/laranail/console" }
]
```

External consumers add the same entries to their own root `composer.json`.

## Single moving `v0.1.0` tag, pre-1.0

While pre-stable, each repo keeps **one `v0.1.0` tag and *moves* it on each change** — the `Initial release` commit is amended and `v0.1.0` re-pointed. No new SemVer versions are cut yet. Constraints stay `^0.1` and resolve the latest tag from the VCS repo, so a consumer on `^0.1` picks up the moved tag on the next `composer update`.

Each repo carries a `branch-alias` of `dev-main → 0.1.x-dev`, so a path or dev checkout still satisfies `^0.1`. Both packages set `minimum-stability: stable` with `prefer-stable: true`.

## Version compatibility

| | `simtabi/sis` | `laranail/sis-wrapper` |
|---|---|---|
| PHP | `^8.5` | `^8.5` |
| Laravel | — (zero deps) | `^13.0` |
| Depends on | — | `simtabi/sis ^0.1` |

## Release versions (the domain, not the package)

Note the distinction: this page is about *package* releases. The system itself also models **product release versions** (§7.2) as a first-class value — `MALISA-1.4.2`, `MALISA-2.0.0-rc.1` — ordered by Semantic Versioning 2.0.0. That is domain data stored in the register, unrelated to how these Composer packages are tagged. See [`Version`](tools/facade.md) via `Sis::version()`.

---

[← Docs index](../README.md#documentation)
