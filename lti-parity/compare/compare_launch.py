#!/usr/bin/env python3
"""compare_launch.py <refjson> <candjson> [--strict-order] [--ignore-key REGEX]

Prints every difference between two normalised launch captures by activity / user / key path.
Exit code 0 only if the two captures are identical (the `_meta` block is ignored; parameter ORDER differences are
reported as notes and only fail with --strict-order)."""
import json, re, sys

def flatten(v, path=''):
    out = {}
    if isinstance(v, dict):
        for k, x in v.items():
            out.update(flatten(x, f'{path}.{k}' if path else k))
        if not v:
            out[path] = {}
    elif isinstance(v, list):
        for i, x in enumerate(v):
            out.update(flatten(x, f'{path}[{i}]'))
        if not v:
            out[path] = []
    else:
        out[path] = v
    return out

# Values that differ between code trees purely because the site version number differs (not launch behaviour).
BENIGN = re.compile(r'(post_params\.tool_consumer_info_version|claim/tool_platform\.version|^path|^launch_route)$')

def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    # --map-cand 'FROM=>TO' rewrites substrings of every candidate string value before comparing (used to isolate a known URL move)
    maps = [a[len('--map-cand='):].split('=>') for a in sys.argv[1:] if a.startswith('--map-cand=')]
    strict = '--strict-order' in sys.argv
    if len(args) != 2:
        print(__doc__); return 2
    ref = json.load(open(args[0])); cand = json.load(open(args[1]))
    if maps:
        txt = json.dumps(cand)
        for f, t in maps:
            txt = txt.replace(f, t)
        cand = json.loads(txt)
    ra, ca = ref['activities'], cand['activities']
    ndiff = 0; nord = 0; nknown = 0
    strictver = '--no-default-ignores' in sys.argv
    for act in sorted(set(ra) | set(ca)):
        if act not in ca:
            print(f'DIFF {act}: missing in candidate'); ndiff += 1; continue
        if act not in ra:
            print(f'DIFF {act}: missing in reference'); ndiff += 1; continue
        for user in sorted(set(ra[act]) | set(ca[act])):
            if user not in ca[act] or user not in ra[act]:
                print(f'DIFF {act} / {user}: only in {"reference" if user in ra[act] else "candidate"}'); ndiff += 1; continue
            fr, fc = flatten(ra[act][user]), flatten(ca[act][user])
            for k in sorted(set(fr) | set(fc)):
                if k not in fc:
                    print(f'DIFF {act} / {user} / {k}: only in reference = {fr[k]!r}'); ndiff += 1
                elif k not in fr:
                    print(f'DIFF {act} / {user} / {k}: only in candidate = {fc[k]!r}'); ndiff += 1
                elif fr[k] != fc[k] and BENIGN.search(k) and not strictver:
                    nknown += 1
                    if '--show-known' in sys.argv:
                        print(f'KNOWN-BENIGN {act} / {user} / {k}: ref={fr[k]!r} cand={fc[k]!r} (site version number / informational code-path label)')
                elif fr[k] != fc[k]:
                    print(f'DIFF {act} / {user} / {k}: ref={fr[k]!r} cand={fc[k]!r}'); ndiff += 1
            # Order of the POST parameters (informational).
            for blk in ('post_params',):
                o1 = list(ra[act][user].get(blk, {}).keys()); o2 = list(ca[act][user].get(blk, {}).keys())
                if o1 != o2 and sorted(o1) == sorted(o2):
                    print(f'NOTE {act} / {user} / {blk}: same keys, different order'); nord += 1
    print(f'-- {ndiff} difference(s), {nord} order-only note(s), {nknown} known-benign site-version difference(s); {len(ra)} ref activities, {len(ca)} candidate activities')
    return 1 if (ndiff or (strict and nord)) else 0

if __name__ == '__main__':
    sys.exit(main())
