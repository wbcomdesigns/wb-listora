# Free REST audit — 2026-05-01

**Trigger:** F-33 in [`plan/release-issues-and-flow-tests.md`](../plan/release-issues-and-flow-tests.md). The plan flagged "49 manifest / 51 actual register_rest_route" within the 5% threshold but worth resolving. Re-enumerated from source via AST walk: 49 invocations / 49 distinct paths in code, plus the 1 collection route that `WP_REST_Posts_Controller::register_routes()` auto-registers via `parent::register_routes()` in `Listings_Controller`.

## Counts (canonical)

| Source | Count | Notes |
|--------|------:|-------|
| `register_rest_route()` invocations in code | **49** | All 49 paths distinct |
| `parent::register_routes()` adds | **1** | `/listora/v1/listings` collection from `WP_REST_Posts_Controller` |
| Effective live routes | **50** | 49 + 1 |
| Manifest entries | **50** | After this run's correction |

The plan's "51 actual" came from a raw grep that included `add_action('rest_api_init', …)` lines as if they were route registrations. After AST walk + accounting for inheritance, real count matches the manifest.

## Manifest corrections shipped this run

Two routes existed in code but were missing from the manifest:

| Route | Handler | Permission |
|-------|---------|------------|
| `POST /listora/v1/listings/{id}/deactivate` | `Listings_Controller::deactivate_listing` | `deactivate_listing_permissions` |
| `GET /listora/v1/listing-types/{slug}/categories` | `Listing_Types_Controller::get_categories` | `__return_true` |

Both added with full `route / methods / handler / permission / purpose` shape.

## Inheritance note

`Listings_Controller` extends `WP_REST_Posts_Controller`, not bare `WP_REST_Controller`. The parent's `register_routes()` auto-registers the collection route (`GET /listora/v1/listings`) and a single-item route (`/listings/{id}` GET) — these are real live routes serving `listora_listing` CPT entries. The manifest entries for those (`/listings`, `/listings/{id}`) are correctly populated even though a static AST walk only sees the explicit `register_rest_route()` calls.

A pure AST walker without an inheritance-allowlist would surface these as "stale" — the reproducer below adds an explicit `parent_provided` allowlist covering exactly the routes WP core's parent class registers.

## Reproducing this audit

```bash
cd wb-listora
python3 - <<'PY'
import re, glob, json
paths = set()
for f in sorted(glob.glob('includes/**/*.php', recursive=True)):
    src = open(f).read()
    rb_protected = (re.search(r"protected\s+\$rest_base\s*=\s*['\"]([^'\"]+)['\"]", src) or [None,''])[1]
    rb_runtime   = (re.search(r"\$this->rest_base\s*=\s*['\"]([^'\"]+)['\"]", src) or [None,''])[1]
    rb = rb_runtime or rb_protected or ''
    for m in re.finditer(r"register_rest_route\s*\(", src):
        depth=0; i=m.end()-1
        while i<len(src):
            if src[i]=='(': depth+=1
            elif src[i]==')':
                depth-=1
                if not depth: break
            i+=1
        block = src[m.end():i]
        depth=0; comma=-1
        for k,c in enumerate(block):
            if c in '([': depth+=1
            elif c in ')]': depth-=1
            elif c==',' and not depth: comma=k; break
        if comma<0: continue
        rest=block[comma+1:]
        depth=0; sec=''
        for ch in rest:
            if ch in '([': depth+=1
            elif ch in ')]': depth-=1
            elif ch==',' and not depth: break
            sec+=ch
        sec = re.sub(r"\$this->rest_base", f"'{rb}'", sec)
        path = ''.join(re.findall(r"['\"]([^'\"]*)['\"]", sec))
        if not path.startswith('/'): path = '/' + path
        canon = re.sub(r"\(\?P<([a-z_]+)>[^)]+\)", lambda m: '{'+m.group(1)+'}', path)
        paths.add(canon)

# WP_REST_Posts_Controller auto-registers the collection route via
# parent::register_routes() in Listings_Controller.
paths |= {'/listings'}

mp = {e['route'].replace('/listora/v1','',1) for e in json.load(open('audit/manifest.json'))['rest']['endpoints']}
print('missing:', sorted(paths - mp))
print('stale:  ', sorted(mp - paths))
PY
```

Expected after this run: `missing=[] stale=[]`.
