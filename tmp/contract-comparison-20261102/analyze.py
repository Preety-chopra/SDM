import json, pathlib, re, collections, datetime
root=pathlib.Path(__file__).parent
d=json.loads((root/'database.json').read_text(encoding='utf-8'))
src=json.loads((root/'source.json').read_text(encoding='utf-8'))
asof='2026-10-29'
stations={s['id']:s for s in d['stations']}
vendors={c['id']:c for c in d['contractors']}
aliases={}
for c in d['contractors']:
    name=c['name'].upper()
    alias=next((a for a,part in [('NCES','NCES'),('JMD','JMD'),('SKM','SKM'),('AWPO','ARMY WELFARE'),('BRADY','BRADY'),('KSJ','KSJ'),('KORE','KORE'),('GYSS','GOOD YEAR'),('BIMLA','BIMLA'),('CYCLOPS','CYCLOPS')] if part in name),c['name'])
    aliases[c['id']]=alias
types={'token_vending':'Ticketing','customer_facilitation':'CFA','watch_and_ward':'W&W','marshall':'Marshall','baggage_handling':'BHS'}
groups=collections.defaultdict(list)
for c in d['service_contracts']: groups[c['contract_group']].append(c)
selected=set()
for group in groups.values():
    eligible=[c for c in group if c['status']!='retired' and c['effective_from']<=asof and (not c['effective_to'] or c['effective_to']>=asof)]
    if eligible: selected.add(max(eligible,key=lambda c:(c['version'],c['id']))['id'])
real=[c for c in d['service_contracts'] if c['id'] in selected and not aliases[c['contractor_id']].startswith('Demo-')]
realids={c['id'] for c in real}
contracts={c['id']:c for c in d['service_contracts']}
coverage=collections.defaultdict(list)
links=collections.defaultdict(set)
for p in d['service_contract_station']: links[p['service_contract_id']].add(p['station_id'])
for c in real:
    if not links[c['id']] and c['station_id']: links[c['id']].add(c['station_id'])
    for sid in links[c['id']]: coverage[sid].append(c)
def tokens(v): return sorted(set(x.strip().upper() for x in re.split(r'[,/]',str(v or '')) if x.strip()))
def join(v): return ', '.join(sorted(set(v)))
def norm(v): return re.sub(r'[^A-Z0-9]','',str(v or '').upper())
special={'KGR':('1','KG'),'KGM':('2','KG'),'KG-6':('6','KG'),'NDI':('2','NDRU'),'CTST-N':('6','CTST'),'MVP1-3':('3','MVPI'),'MVP1-7':('7','MVPI'),'DWTO':('3','DSTO'),'YV':('7','YMVR'),'SNVR':('7','SNVH'),'BIMR8':('8','BIMR'),'MKPR8':('8','MKPR'),'DW09':('9','DW'),'AVIT-3':('4','AVIT')}
def map_station(code,name,section):
    if not name and not code: return [],'Blank source row',''
    if 'PD AREA' in (name or '').upper(): return [],'Non-station sublocation','Welcome PD area is distinct from Welcome station.'
    if code=='SKVR': return [248],'Name alias','Source SKVR matches Shankar Vihar; master station code is ?.'
    if section=='LINE-9' and code in ('NFGH','DNBT'):
        sid=258 if code=='NFGH' else 259
        return [sid],'Name used over conflicting code','Source and master reverse NFGH/DNBT for Najafgarh and Dhansa Bus Stand. Matched by station name.'
    line='AEL' if section=='AMEL' else re.search(r'(\d+)',section).group(1)
    base=code or ''
    if code in special: line,base=special[code]
    elif code!='JLA-8':
        m=re.match(r'^(.+?)\s*-\s*([1-9])$',str(code or ''))
        if m: base,line=m.group(1).strip(),m.group(2)
    candidates=[s['id'] for s in stations.values() if str(s['line_code'])==line and norm(s['station_code'])==norm(base) and norm(base)]
    if not candidates and section=='LINE-3E&4':
        candidates=[s['id'] for s in stations.values() if str(s['line_code'])=='4' and norm(s['station_code'])==norm(base)]
    if len(candidates)==1:
        s=stations[candidates[0]]
        method='Exact line/code' if str(code)==str(s['station_code']) else 'Code alias'
        mapping_note='Source calls Anand Vihar Line-4 but code AVIT-3. Mapped to Line 4 using the station name.' if code=='AVIT-3' else ''
        return candidates,method,mapping_note
    if len(candidates)>1: return candidates,'Ambiguous master station','Multiple master records have this line and station code. No coverage conclusion made.'
    return [],'Missing from station master','No verified station mapping.'

comparison=[]; other=[]; issues=[]; represented=set(); section=''; rawrows=[]
def issue(category,row,service,excel,db,missing,extra,note,ids=''):
    issues.append([category,row['row'],row['section'],row['name'],row['code'],service,excel,db,missing,extra,ids,note])
for r in src['rows']:
    a,b,seq,name,code,tick,mixed=r['values']
    if not isinstance(a,int): continue
    if b: section=str(b).strip()
    row={'row':r['row'],'section':section,'name':name or '', 'code':code or ''}
    rawrows.append([r['row'],a,section,seq,name,code,tick,mixed])
    if r['row']>=281:
        agencies=tokens(mixed)
        names=join(vendors[i]['name'] for i,v in aliases.items() if v in agencies)
        refs=join(c['contract_number'] for c in real if aliases[c['contractor_id']] in agencies)
        status='Not represented as a separate station location' if name else 'Blank source row'
        note='No exact location coverage comparison is possible from station links. The mixed heading includes office boy/loader duties; service type is not stated.' if name else 'Serial/sequence only; no location or agency supplied.'
        other.append([r['row'],a,section,seq,name,code,tick,mixed,status,names,refs,note])
        continue
    ids,method,note=map_station(code,name,section)
    valid=len(ids)==1
    sid=ids[0] if valid else None
    if valid: represented.add(sid)
    dbcs=coverage.get(sid,[]) if valid else []
    dt={aliases[c['contractor_id']] for c in dbcs if c['service_type']=='token_vending'}
    dm={aliases[c['contractor_id']] for c in dbcs if c['service_type']!='token_vending'}
    et=set(tokens(tick)); em=set(tokens(mixed))
    miss_t=et-dt if valid else set(); miss_m=em-dm if valid else set()
    extra_t=dt-et if valid else set(); extra_m=dm-em if valid else set()
    refs=join(f"{c['id']} ({c['status']})" for c in dbcs)
    def status(expected,missing,extra):
        if not valid: return method
        if not expected: return 'No agency stated in Excel'
        if missing: return 'Excel agency lacks matching coverage'
        if extra: return 'Excel covered; additional DB agencies'
        return 'Agency sets match'
    comparison.append([r['row'],a,section,name,code,join(str(i) for i in ids),str(stations[sid]['line_code']) if valid else '',stations[sid]['station_code'] if valid else '',stations[sid]['name'] if valid else '',method,tick,mixed,join(dt) if valid else None,join(dm) if valid else None,join(miss_t),join(miss_m),join(extra_t),join(extra_m),status(et,miss_t,extra_t),status(em,miss_m,extra_m),refs,note])
    if not valid:
        issue('Mapping review',row,'Location',join(et|em),'','','',note or method,join(str(i) for i in ids))
        continue
    if method=='Name used over conflicting code': issue('Station code conflict',row,'Location',code,stations[sid]['station_code'],'','',note,str(sid))
    if method=='Name alias': issue('Master code incomplete',row,'Location',code,stations[sid]['station_code'],'','',note,str(sid))
    for label,expected,actual,missing,extra in [('Ticketing',et,dt,miss_t,extra_t),('CFA / W&W / Marshall / BHS',em,dm,miss_m,extra_m)]:
        if missing:
            issue('Excel agency lacks matching coverage',row,label,join(expected),join(actual),join(missing),'','Excel lists this agency, but no selected contract links it to this station in this service bucket.',refs)
        if extra:
            issue('Broader contract coverage',row,label,join(expected),join(actual),'',join(extra),'Contract scope is broader than recorded deployment. This is not evidence of an incorrect contract. Blank Excel cells do not mean no deployment.',refs)

# Reverse check: database station records not represented by any verified source station row.
for sid,cs in coverage.items():
    if sid in represented: continue
    s=stations[sid]
    row={'row':None,'section':'Line '+str(s['line_code']),'name':s['name'],'code':s['station_code'] or ''}
    issue('DB station absent or unresolved in Excel',row,'All services','',join(aliases[c['contractor_id']] for c in cs),'',join(aliases[c['contractor_id']] for c in cs),'No uniquely mapped source station row. Check omitted stations, unresolved source rows and separate interchange records.',join(str(c['id']) for c in cs))

inventory=[]; dbcoverage=[]
for c in sorted(d['service_contracts'],key=lambda x:(aliases[x['contractor_id']],x['contract_number'],x['version'],x['id'])):
    alias=aliases[c['contractor_id']]
    reason='Compared' if c['id'] in realids else ('Demo contractor excluded' if alias.startswith('Demo-') else ('Retired version excluded' if c['status']=='retired' else ('Outside source date' if c['effective_from']>asof or (c['effective_to'] and c['effective_to']<asof) else 'Older version excluded')))
    inventory.append([c['id'],alias,vendors[c['contractor_id']]['name'],c['contract_number'],types[c['service_type']],c['version'],c['status'],c['effective_from'],c['effective_to'],len(links[c['id']]),reason,c['contract_group']])
    if c['id'] in realids:
        for sid in sorted(links[c['id']]):
            s=stations[sid]
            dbcoverage.append([c['id'],alias,c['contract_number'],types[c['service_type']],c['status'],sid,str(s['line_code']),s['station_code'],s['name'],s['sequence_no']])
counts=dict(collections.Counter(x[0] for x in issues))
payload={'source':{k:src[k] for k in ['filename','sheet','sha256']},'captured_at':d['captured_at'],'asof':asof,'comparison':comparison,'other':other,'issues':issues,'contracts':inventory,'coverage':dbcoverage,'counts':counts,'raw':rawrows,'selected_contracts':len(real),'drafts':sum(c['status']=='draft' for c in real),'source_station_rows':len(comparison),'matched_station_rows':sum(bool(x[5]) and ',' not in x[5] for x in comparison),'represented_station_ids':len(represented)}
assert len(rawrows)==416
assert len(comparison)+len(other)==416
assert len({r[0] for r in rawrows})==416
assert len(dbcoverage)==sum(len(links[c['id']]) for c in real)
(root/'analysis.json').write_text(json.dumps(payload,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({k:v for k,v in payload.items() if k not in ['comparison','other','issues','contracts','coverage','raw']},indent=2))
print('UNRESOLVED')
for row in comparison:
    if row[9] not in ('Exact line/code','Code alias'): print(row[:10],row[-1])
print('MISSING AGENCY COUNTS',collections.Counter(a for i in issues if i[0]=='Excel agency lacks matching coverage' for a in i[8].split(', ')))
