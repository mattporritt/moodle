#!/usr/bin/env python3
import json,sys
v=json.load(open(sys.argv[1]))
n=ok=bad=skip=0; hash_ok=hash_bad=0; bads=[]
for a,u in v.items():
    for k,x in u.items():
        for kind in ('oauth1','jwt'):
            if kind in x:
                n+=1
                y=x[kind]
                if not y.get('checked'): skip+=1
                elif y.get('valid') and all(y.get(f,True) for f in ('nonce_matches','aud_matches','has_iat_exp') if f in y): ok+=1
                else: bad+=1; bads.append((a,k,kind,y))
        if 'sourcedid_hash_ok' in x:
            if x['sourcedid_hash_ok']: hash_ok+=1
            else: hash_bad+=1; bads.append((a,k,'sourcedid',x))
print(f'signature checks: {n} total, {ok} valid, {bad} INVALID, {skip} not applicable (unsigned launch); sourcedid hash ok={hash_ok} bad={hash_bad}')
for b in bads[:10]: print('  INVALID',b)
