# LTI launch parity test

Proves that an LTI activity sends the **same launch data** from two different code trees. It was written to check
that each core_ltix slice branch launches existing activities exactly as Moodle `main` (BASE) does, and it is the
starting point for the launch parity test (workplan decision D21).

It works on a seeded Moodle 5.3 site, because that is what a real upgrade starts from:

1. Restore the 5.3 site (a database dump and a data directory).
2. Upgrade it with the code tree under test (BASE, or a slice branch).
3. For every LTI activity, build the launch as `teacher1` and as `student1`, the same way `mod/lti/launch.php` and
   the OIDC auth endpoint do, and write the result to JSON.
4. Compare the JSON from two trees.

Steps 1 to 3 are done by `bin/capture.sh`, step 4 by `compare/compare_launch.py`.

## What is in the fixture

`fixture/seed_fixture.php` creates deterministic data on a 5.3 site: two categories and courses, `teacher1` and
`student1` in both, a mix of tools (LTI 1.1 shown in the activity chooser, LTI 1.1 preconfigured only, LTI 1.1 hidden,
LTI 1.3 with deep linking, LTI 1.3 restricted to one category, an LTI 2.0 tool proxy with tool settings at every
level, and a course-level tool), activities for each of them including manually configured ones that use no tool at
all, an AGS line item, submissions and grades, and capability overrides at site, category, course and module level.
Service salts and the site's signing keys are fixed so that results are repeatable.

## Requirements

* Docker, a PostgreSQL container, and a PHP image that can run Moodle (`LTIP_IMAGE`, any image Moodle's docker setup
  uses will do).
* A checkout of Moodle 5.3 (`MOODLE_503_STABLE`) to build the fixture from, and one checkout (a `git worktree`) of each
  tree to compare. Each needs a `vendor/` directory from `composer install` (or point `LTIP_VENDOR` at a shared one).
* `LTIP_DB_PASS` set to the database password. The scripts never write it to disk. Other settings (`LTIP_DATA`,
  `LTIP_NETWORK`, `LTIP_DB_HOST`, `LTIP_DB_USER`, `LTIP_DB_CONTAINER`, `LTIP_WWWROOT`, `LTIP_IMAGE`) are described at
  the top of `bin/ltip-run`.

Everything runs in one-off containers with its own database and data directory, so it can run next to a development
site without touching it. A tree that has no `config.php` gets one that reads the settings above.

## 1. Build the fixture (once)

```
git worktree add ../wt/m503 origin/MOODLE_503_STABLE
(cd ../wt/m503 && composer install)
export LTIP_DB_PASS=...
lti-parity/bin/build-fixture.sh ../wt/m503 lti-parity/fixture-build
```

This installs the 5.3 tree, runs `seed_fixture.php`, and writes `fixture.dump` and `moodledata.tgz` into the output
folder (about two minutes). Keep those two files: they are the fixture. `bin/restore-fixture.sh <dir> <db> <data>`
recreates the site from them in seconds.

## 2. Capture the launch data from two trees

```
git worktree add ../wt/base 46c042e7da6          # BASE, the reference
git worktree add ../wt/s1 lti-analysis/slice-1   # the branch under test
lti-parity/bin/capture.sh ../wt/base lti-parity/fixture-build base
lti-parity/bin/capture.sh ../wt/s1   lti-parity/fixture-build s1
```

Each call restores a fresh copy of the fixture, upgrades it with that tree, and writes `out/<label>.json` plus
sidecar files (`.raw.json` un-normalised launch data, `.verify.json` signature checks, `.notices.json` any
debugging or deprecation output, `.meta.json` the release and version). The last line it prints is the
signature check summary for the capture (see below).

## 3. Compare

```
python3 lti-parity/compare/compare_launch.py lti-parity/out/base.json lti-parity/out/s1.json --show-known
```

It prints one line per difference, keyed by activity, user and key path, and exits 0 only when there are none. Useful
options:

* `--map-cand='FROM=>TO'` rewrites text in the candidate before comparing. Slices 1 to 3 move service and return
  URLs from `/mod/lti/` to `/ltix/`. That is an intended move, so compare with
  `--map-cand='webserver/ltix/=>webserver/mod/lti/'` to see only the differences that remain.
* `--strict-order` also fails when the same parameters arrive in a different order (reported as a note by default).
* `--no-default-ignores` also reports the known benign differences below.

Compare trees captured from the same fixture build: the site's signing key id (`kid`) is generated when the fixture
is built, so captures made from two different builds differ in it.

Known benign differences, counted but not reported as failures: `tool_consumer_info_version` and the
`tool_platform.version` claim (they carry the site version, which differs between trees), and the informational
`path` and `launch_route` labels the capture adds to say which code path built the launch.

## What is recorded for each activity and user

For `teacher1` (a normal launch and a launch with a `for_user` target, which for LTI 1.3 is an
`LtiSubmissionReviewRequest`) and `student1` (normal launch): whether the user is enrolled and has `mod/lti:view`,
the tool the activity resolves to, the endpoint, every POST parameter, and for LTI 1.3 the login request, the
decoded id token header and claims, and the launch data the auth endpoint read back from the session (so a lost
`for_user` shows up). That covers the AGS, NRPS and tool settings (LTI 2.0) parameters and claims, custom
parameters and their substitutions, the result sourcedid, and the return URLs.

On trees that launch through the new `core_ltix` routes (slice 4b onwards) the capture builds the launch with the
new launch service and the OIDC authenticator instead, which is how those slices send it.

## How volatile values are normalised

Only values that change on every launch are replaced, by a marker such as `<VOLATILE:oauth_nonce>`. The marker is
kept, so a parameter that disappears or appears is still a difference.

* Replaced by a marker: `oauth_nonce`, `oauth_timestamp`, `oauth_signature`, `jti`, `iat`, `exp`, `nbf`, `nonce`,
  `state`; the random part of the launch id inside `lis_result_sourcedid` and `lti_message_hint`; the sourcedid hash
  (it is derived from that launch id); the `sesskey` in return URLs; and a JWT's signature.
* A JWT carried as a value, such as the signed `lti_message_hint` of slice 4b, is decoded and its claims are
  compared, with the same volatile claims replaced.
* Everything else is compared exactly.

Because a normalised value can hide a wrong one, the capture also checks the raw data on its own: it recomputes every
OAuth 1.0a signature, verifies every id token against the site's key set (signature, nonce, audience, `iat`, `exp`),
and recomputes every sourcedid hash. `compare/verify_summary.py out/<label>.verify.json` prints the totals, and
`capture.sh` prints them at the end. Any `INVALID` is a bug in the tree, whatever the comparison says.

Run a capture twice on the same tree and the normalised files should be byte-identical. If they are not, something
else that is volatile needs normalising.

## Reading a result

A difference is a launch parameter or claim that one tree sends and the other does not, or sends with another value.
Look at the user it affects, the key, and which tree has it. Differences that are not understood are a leaked
behaviour change, for example the whole set of AGS, NRPS and tool settings parameters missing from every existing
launch.

On the verification run these were the results against BASE (the slice report has the detail):

* Slices 1 and 2: identical once `/ltix/` is mapped back to `/mod/lti/`.
* Slice 3 before the `for_user` fix: `for_user` lost on LTI 1.3 submission review launches.
* Slice 4a: AGS, NRPS and tool settings parameters missing.
* Slices 4b and 5: personal data always stripped from LTI 1.1, role URNs, some custom parameters literal.

## What is not covered

Launches from a real browser, the tool's side of the exchange, and deep linking return are not exercised. The
capture builds launch data in the same way the entry scripts do; it does not make HTTP requests. A PHPUnit test
checks that `mod/lti/launch.php` passes the user id to `initiate_login()` correctly.
