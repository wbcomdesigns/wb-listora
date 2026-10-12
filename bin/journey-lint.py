#!/usr/bin/env python3
"""Journey lint: every journey follows docs/qa/journeys/.template.md.

Checks the frontmatter keys, the section order, slug == filename, group ==
folder, that the group has a _group.md manifest, the owning plugin, and that no journey names a site (host, login, absolute path).
Lints this repo and the sibling wb-listora-pro when it is checked out.

    python3 bin/journey-lint.py        # exit 1 on any finding
"""
import os,re,sys,yaml,collections
REQ=['journey','group','actor','mode','plugin','priority','roles','covers','prerequisites','estimated_runtime_minutes','last_verified','verified_against','viewports','themes','supersedes','site_profile']
SECS=['Why','Setup','Steps','Role ladder','Pass criteria','Fail diagnostics','Not verified','Cleanup']
LIT=re.compile(r'https?://[\w.-]+\.local\b|/Users/\w+|qa_vendor_0\d|dana_whitfield|qa_editor')
out=collections.defaultdict(list); n=0
HERE=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
for r in [HERE, os.path.join(os.path.dirname(HERE),'wb-listora-pro')]:
    root=f'{r}/docs/qa/journeys'
    if not os.path.isdir(root): continue
    for dp,_,fs in os.walk(root):
        for f in fs:
            if not f.endswith('.md') or f in('README.md','.template.md','MICRO_CHECKS.md') or f.startswith('_'): continue
            n+=1; p=os.path.join(dp,f); t=open(p).read(); key=r[10:] or 'free'; rel=('pro:' if r.endswith('pro') else 'ora:')+os.path.relpath(p,root)
            m=re.match(r'---\n(.*?)\n---\n(.*)',t,re.S)
            if not m: out['no-frontmatter'].append(rel); continue
            try: fm=yaml.safe_load(m.group(1)) or {}
            except Exception as e: out['bad-yaml'].append(rel); continue
            miss=[k for k in REQ if k not in fm]
            if miss: out['missing-keys'].append(rel+' '+','.join(miss))
            if str(fm.get('journey'))!=f[:-3] and re.sub(r'^\d+-','',f[:-3])!=str(fm.get('journey')): out['slug-mismatch'].append(rel+' -> '+str(fm.get('journey')))
            want='wb-listora-pro' if r.endswith('pro') else 'wb-listora'
            if fm.get('plugin')!=want: out['plugin-mismatch'].append(rel+' = '+str(fm.get('plugin')))
            secs=[l[3:].strip() for l in m.group(2).split('\n') if l.startswith('## ')]
            if [s for s in secs if s in SECS]!=SECS: out['section-order'].append(rel+' : '+'|'.join(secs))
            extra=[s for s in secs if s not in SECS]
            if extra: out['extra-sections'].append(rel+' : '+'|'.join(extra))
            if LIT.search(t): out['site-literal'].append(rel+' : '+','.join(sorted(set(LIT.findall(t)))))
            if re.match(r'^(2\.0-)',f): out['version-in-name'].append(rel)
            if fm.get('group')!=os.path.basename(dp): out['group-mismatch'].append(rel+' = '+str(fm.get('group')))
            if fm.get('actor') not in ('customer','admin','system'): out['bad-actor'].append(rel)
            if fm.get('mode') not in ('browser','api','cli','mixed'): out['bad-mode'].append(rel)
            if not os.path.exists(os.path.join(dp,'_group.md')): out['group-without-manifest'].append(os.path.basename(dp))
print('journeys',n)
for k,v in out.items():
    print(k,len(v))
    for x in v: print('  ',x)
sys.exit(1 if out else 0)
