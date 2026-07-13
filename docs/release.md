# Release

How the wrapper is versioned and tagged, and how it resolves the SDK it consumes.

## Two separate repos

The wrapper and the SDK it consumes are independent polyrepos, each published on its own:

| Package | Repository | Role |
|---------|------------|------|
| `simtabi/sis-sdk` | `github.com/simtabi/sis-sdk` | The pure, zero-dependency SDK engine. Owned by the `simtabi` org. |
| `laranail/sis-wrapper` | `github.com/laranail/sis-wrapper` | This Laravel binding. Owned by the `laranail` org. |

There is no monorepo and no `git subtree` split — each repo is developed and tagged on its own. During local development the wrapper's `composer.json` uses `path` repositories to sibling checkouts (`../../simtabi/sis-sdk`, `../package-tools`, `../console`, `../enumerator`); on a consumer's machine the same dependencies resolve from git VCS.

## Inter-package dependencies resolve via VCS, not Packagist

`laranail/*` packages resolve their inter-package dependencies through **git VCS repositories**, not Packagist. Packagist is treated as unreliable for this family (force-pushed history leaves stale cached clones), so routine work does not push or update these packages on Packagist.

The wrapper declares the `vcs` repositories it needs, including the full transitive `laranail/*` closure and the SDK:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/simtabi/sis-sdk" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
    { "type": "vcs", "url": "https://github.com/laranail/console" },
    { "type": "vcs", "url": "https://github.com/laranail/enumerator" }
]
```

External consumers add the same entries to their own root `composer.json`.

## Single moving `v0.1.0` tag, pre-1.0

While pre-stable, each repo keeps **one `v0.1.0` tag and *moves* it on each change** — the `Initial release` commit is amended and `v0.1.0` re-pointed. No new SemVer versions are cut yet. Constraints stay `^0.1` and resolve the latest tag from the VCS repo, so a consumer on `^0.1` picks up the moved tag on the next `composer update`.

Each repo carries a `branch-alias` of `dev-main → 0.1.x-dev`, so a path or dev checkout still satisfies `^0.1`. Both packages set `minimum-stability: stable` with `prefer-stable: true`.

## Version compatibility

| | `simtabi/sis-sdk` | `laranail/sis-wrapper` |
|---|---|---|
| PHP | `^8.5` | `^8.5` |
| Laravel | — (zero deps) | `^13.0` |
| Depends on | — | `simtabi/sis-sdk ^0.1` |

## Release versions (the domain, not the package)

Note the distinction: this page is about *package* releases. The system itself also models **product release versions** (§7.2) as a first-class value — `MALISA-1.4.2`, `MALISA-2.0.0-rc.1` — ordered by Semantic Versioning 2.0.0. That is domain data stored in the register, unrelated to how these Composer packages are tagged. See [`Version`](tools/facade.md) via `Sis::version()`.

---

[← Docs index](../README.md#documentation)
