# Contributing

## Commit messages decide releases

Merges are squashed, and the pull request title becomes the commit subject on
`main`. That subject is read by semantic-release, which cuts the tag and the
GitHub release — so the title is not a formality, it is the version bump.

Titles follow [Conventional Commits](https://www.conventionalcommits.org):

```
feat(router): match a path segment by name
fix(router): answer 405 rather than 404 for a known path
docs: explain why the server binds to loopback
```

| Prefix | Release |
| --- | --- |
| `fix`, `perf` | patch — `1.2.3` → `1.2.4` |
| `feat` | minor — `1.2.3` → `1.3.0` |
| `feat!`, or `BREAKING CHANGE:` in the body | major — `1.2.3` → `2.0.0` |
| `docs`, `test`, `refactor`, `build`, `ci`, `chore` | none |

A pull request whose title does not parse is rejected by a check before it can
be merged, because a subject semantic-release cannot read is a release that
silently never happens.

Put the reasoning in the pull request body. It becomes the commit body, and it
is the part someone reads in a year when they are trying to work out why.

## Breaking changes

Mark them, and say what to do instead:

```
feat(router)!: give handlers the request as a wrapper

BREAKING CHANGE: a handler typed against ServerRequestInterface now
receives Http\Request. Reach the PSR-7 request through ->psr.
```

## Before opening a pull request

```bash
composer test     # PHPUnit
composer analyse  # PHPStan
```

## Testing a server

The suite never opens a socket. `Router::handle()` takes a PSR-7 request and
returns an answer, so routing, argument binding and containment are all
exercised by calling it — no ports, no waiting, no flakiness on a busy machine.

Only `HttpPlugin` binds anything, and it is deliberately thin for that reason:
everything worth testing happens on the other side of it.
