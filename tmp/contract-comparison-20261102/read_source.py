import openpyxl, pathlib, json, collections, hashlib, datetime
root=pathlib.Path(__file__).parent
p=pathlib.Path(r'C:\Users\Anuj Kumar\Downloads\SDM\documents received\30-10-2026\Station wise deployment of contract agency.xlsx')
w=openpyxl.load_workbook(p,data_only=True)
s=w.worksheets[0]
rows=[]
for i,values in enumerate(s.values,1):
    v=list(values[:7])
    if any(x is not None for x in v):
        rows.append({'row':i,'values':v})
payload={'filename':p.name,'sheet':s.title,'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'rows':rows,'merges':[str(x) for x in s.merged_cells]}
(root/'source.json').write_text(json.dumps(payload,ensure_ascii=False,indent=2,default=str),encoding='utf-8')
print('Rows',len(rows))
for r in rows:
    if r['row']>=6:
        print(r['row'],r['values'])
