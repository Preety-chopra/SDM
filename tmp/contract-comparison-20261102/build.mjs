import fs from 'node:fs/promises';
import path from 'node:path';
import { Workbook, SpreadsheetFile } from '@oai/artifact-tool';
const base=path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/,'$1'));
const data=JSON.parse(await fs.readFile(path.join(base,'analysis.json'),'utf8'));
const out='C:/Project/sdm/outputs/contract-comparison-20261102';
await fs.mkdir(out,{recursive:true});
const wb=Workbook.create();
const navy='#243B53', ink='#172B4D', light='#F0F4F8';
const summary=wb.worksheets.add('Summary');
function init(s,lastRow,lastCol){
  s.showGridLines=false;
  const r=s.getRangeByIndexes(0,0,lastRow,lastCol);
  r.format.font={name:'Arial',size:10,color:ink};
  r.format.verticalAlignment='center';
  r.format.rowHeight=22;
}
function col(i){let x='';for(i++;i;i=Math.floor((i-1)/26))x=String.fromCharCode(65+(i-1)%26)+x;return x;}
function tableSheet(name,title,context,headers,rows,widths,height=38){
  const s=wb.worksheets.add(name);init(s,rows.length+6,headers.length);
  s.getRange('A2').values=[[title]];s.getRange('A2').format.font={size:14,bold:true};
  s.getRange('A3').values=[[context]];s.getRange('A3').format.font={size:10,italic:true,color:'#526777'};
  s.getRangeByIndexes(5,0,1,headers.length).values=[headers];
  if(rows.length)s.getRangeByIndexes(6,0,rows.length,headers.length).values=rows;
  const t=s.tables.add(`A6:${col(headers.length-1)}${rows.length+6}`,true,name.replace(/ /g,'')+'Table');
  t.showFilterButton=true;
  const hr=s.getRangeByIndexes(5,0,1,headers.length);hr.format={fill:navy,font:{bold:true,color:'#FFFFFF'},wrapText:true,horizontalAlignment:'center',rowHeight:44};
  const body=s.getRangeByIndexes(6,0,rows.length,headers.length);body.format.wrapText=true;body.format.rowHeight=height;
  widths.forEach((w,i)=>s.getRangeByIndexes(0,i,rows.length+6,1).format.columnWidth=w);
  s.freezePanes.freezeRows(6);s.freezePanes.freezeColumns(name==='Station Comparison'?5:2);
  return s;
}
init(summary,34,4);summary.tabColor=navy;
summary.getRange('A1:A34').format.columnWidth=65;summary.getRange('B1:B34').format.columnWidth=22;summary.getRange('C1:C34').format.columnWidth=4;summary.getRange('D1:D34').format.columnWidth=120;
summary.getRange('A2').values=[['Contract deployment comparison']];summary.getRange('A2').format.font={size:16,bold:true};
summary.getRange('A4:B6').values=[['Deployment date',new Date('2026-10-29T00:00:00Z')],['Database read on','2 Nov 2026, 12:08 IST'],['Contracts compared',data.selected_contracts]];
summary.getRange('B4').setNumberFormat('dd mmm yyyy');
summary.getRange('A8:B8').values=[['Finding','Count']];summary.getRange('A8:B8').format={fill:navy,font:{bold:true,color:'#FFFFFF'}};
const categories=['Excel agency lacks matching coverage','Station code conflict','Master code incomplete','Mapping review','Broader contract coverage','DB station absent or unresolved in Excel'];
summary.getRange('A9:A14').values=categories.map(x=>[x]);
summary.getRange('A16:B20').values=[['Source station-section rows',data.source_station_rows],['Station rows uniquely matched',data.matched_station_rows],['Non-station section rows',data.other.length],['Named non-station locations',data.other.filter(r=>r[4]).length],['Compared contracts still in draft',data.drafts]];
summary.getRange('A22').values=[['Priority review']];summary.getRange('A22').format.font={bold:true};
summary.getRange('A23').values=[['50 source station rows contain the 56 coverage discrepancies.']];
summary.getRange('A24').values=[['Najafgarh / Dhansa Bus Stand: NFGH and DNBT are reversed.']];
summary.getRange('A25').values=[['SKM and KORE contracts are drafts. Their coverage is included.']];
summary.getRange('A26').values=[['Filter Differences by category, line, agency or source row.']];
summary.getRange('D4:D20').values=[
 ['Comparison basis'],
 ['Contract coverage is compared with deployment as of 29 October 2026.'],
 ['Latest eligible non-retired version per contract group, including drafts. Demo contractors are excluded.'],
 ['Ticketing is compared separately. The mixed agency column is compared against CFA, W&W, Marshall and BHS.'],
 ['Broader contract coverage means a DB-linked agency is absent from the deployment cell. It is not a proven error.'],
 ['Blank source agency cells mean “not stated”. They are not treated as evidence of no deployment.'],
 ['Station codes are matched within the actual metro line. Verified aliases are visible in Station Comparison.'],
 ['Maujpur-Babarpur matches two master rows (183, 226). Coverage is unresolved for this source row.'],
 ['Five station-section rows are blank. Welcome PD Area is a separate sublocation without a station match.'],
 ['131 named office, quarter, patrol, parking and other locations lack separate station assignments.'],
 ['Those locations are retained in Other Locations; their contract scope cannot be verified from station links.'],
 ['The source contains no contract numbers, validity dates, penalty clauses or publication status.'],
 ['These fields cannot be directly reconciled; existing contract dates and status are listed in Contracts.'],
 ['All 416 serial-numbered source rows are retained across Station Comparison and Other Locations.'],
 ['Source row numbers refer to the original Excel sheet. Combined agency cells are split on comma or slash.'],
 ['Agency aliases and full contractor names are listed in Contracts. No database records were changed.'],
 ['Counts of coverage discrepancies represent station/service rows, not individual agencies.']
];summary.getRange('D4').format.font={bold:true};

const order={'Excel agency lacks matching coverage':0,'Station code conflict':1,'Master code incomplete':2,'Mapping review':3,'Broader contract coverage':4,'DB station absent or unresolved in Excel':5};
const diffs=[...data.issues].sort((a,b)=>order[a[0]]-order[b[0]]||(a[1]??9999)-(b[1]??9999));
const diff=tableSheet('Differences','Differences and coverage review','Coverage gaps first. Broader scope and unresolved rows are separate review categories.',
 ['Category','Excel row','Excel section / DB line','Station / location','Excel station code','Service bucket','Excel agencies','DB agencies','Excel agency without coverage','Additional DB agency','DB contract IDs / status','Explanation'],diffs,[40,10,22,45,16,29,30,34,29,28,42,80],56);
diff.getRange(`A7:A${diffs.length+6}`).conditionalFormats.add('containsText',{text:'Excel agency lacks',format:{fill:'#FDE7E9',font:{color:'#9C1C26',bold:true}}});
diff.getRange(`A7:A${diffs.length+6}`).conditionalFormats.add('containsText',{text:'Broader contract',format:{fill:'#FFF3CD'}});
tableSheet('Station Comparison','Station deployment compared with contracts',`Source: ${data.source.filename}, sheet ${data.source.sheet}. Rows 6–280; merged line labels are carried down.`,
 ['Excel row','Excel serial','Excel section','Excel station name','Excel station code','DB station ID(s)','DB line','DB station code','DB station name','Mapping method','Excel ticketing (raw)','Excel mixed agencies (raw)','DB ticketing agencies','DB other service agencies','Ticketing coverage gaps','Other service coverage gaps','Additional DB ticketing','Additional DB other agencies','Ticketing result','Mixed service result','DB contract IDs / status','Mapping note'],data.comparison,[10,11,19,46,16,17,10,16,43,29,25,34,26,37,27,29,27,32,39,39,45,65],42);
tableSheet('Other Locations','Other locations requiring scope review','Source rows 281–421. Office boy/loader duties and non-station scopes cannot be verified from station assignments.',
 ['Excel row','Excel serial','Excel section','Section sequence','Location (raw)','Code (raw)','Ticketing (raw)','Mixed agencies (raw)','Comparison status','Recognized contractor names','Related selected contract references','Limitation'],data.other,[10,11,35,14,67,25,22,32,46,75,65,80],50);
const inventory=data.contracts.map(r=>r.map((v,i)=>(i===7||i===8)&&v?new Date(v+'T00:00:00Z'):v));
const inv=tableSheet('Contracts','Existing contract inventory','Read-only Sail MySQL snapshot, 2 November 2026 at 12:08 IST. Selection is evaluated at the source deployment date.',
 ['Contract ID','Agency alias','Contractor name','Contract reference','Service','Version','Status','Effective from','Effective to','Station links','Comparison selection','Contract group'],inventory,[13,15,66,31,20,10,14,18,18,15,30,34],34);
inv.getRange(`H7:I${inventory.length+6}`).setNumberFormat('dd mmm yyyy');
inv.getRange('N6:O10').values=[['Source','Value'],['Deployment workbook',data.source.filename],['Sheet / source date',data.source.sheet],['Source SHA-256',data.source.sha256],['Database captured',data.captured_at]];
inv.getRange('N6:N10').format.columnWidth=25;inv.getRange('O6:O10').format.columnWidth=86;inv.getRange('N6:O6').format.font={bold:true};
tableSheet('DB Coverage','Selected contract station assignments','Evidence for the comparison. One row per selected contract and station-master record; draft mappings are included.',
 ['Contract ID','Agency alias','Contract reference','Service','Contract status','DB station ID','DB line','DB station code','DB station name','Station sequence'],data.coverage,[13,15,31,20,18,15,12,18,48,18],28);

summary.getRange('B9:B14').formulas=categories.map((x,i)=>[`=COUNTIF('Differences'!$A$7:$A$${data.issues.length+6},A${i+9})`]);
wb.recalculate();
const checks=await wb.inspect({kind:'table',range:'Summary!A8:B20',include:'values,formulas',tableMaxRows:14,tableMaxCols:2,maxChars:4500});
console.log(checks.ndjson);
for(let i=0;i<categories.length;i++){
  const value=summary.getRange(`B${i+9}`).values[0][0];
  if(value!==data.counts[categories[i]]) throw new Error(`Summary discrepancy for ${categories[i]}: ${value}`);
}
console.log((await wb.inspect({kind:'match',searchTerm:'#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!',options:{useRegex:true,maxResults:30},summary:'Final error scan',maxChars:1800})).ndjson);
const previews=[['Summary','A1:D27'],['Differences','A1:H10'],['Station Comparison','A1:L10'],['Station Comparison','M6:V11'],['Other Locations','A1:I10'],['Contracts','A1:K11'],['DB Coverage','A1:J11']];
for(let i=0;i<previews.length;i++){
  const [sheetName,range]=previews[i];
  const blob=await wb.render({sheetName,range,scale:1,format:'png'});
  await fs.writeFile(path.join(base,`preview-${i}.png`),new Uint8Array(await blob.arrayBuffer()));
}
const file=await SpreadsheetFile.exportXlsx(wb);
const target=path.join(out,'Contract_Deployment_Differences.xlsx');await file.save(target);
console.log(JSON.stringify({output:target,sheets:6,findings:diffs.length,coverageRows:data.coverage.length}));
