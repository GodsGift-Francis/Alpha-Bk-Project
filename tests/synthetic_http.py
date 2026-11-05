"""Invoked by http_browser against its disposable fixture database."""
import json
import re
import urllib.request

def run(admin, sender, recipient, check):
    data = {'dataset_type':'synthetic_banking_test_data','currency':'USD','opening_balance_cents':9500,'closing_balance_cents':10000,'transaction_count':1,'transactions':[{'transaction_id':'S1','reference':'R1','date':'2001-01-01','type':'deposit','direction':'credit','amount_cents':500,'currency':'USD','counterparty':'<script>alert(1)</script>','description':'Fixture','running_balance_cents':10000}]}
    def preview(payload):
        boundary='SyntheticHistoryBoundary'
        fields={'action':'preview','account_id':'1','description':'<b>Fixture description</b>','csrf_token':admin.token}
        body=''.join(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n' for k,v in fields.items())
        body+=f'--{boundary}\r\nContent-Disposition: form-data; name="dataset"; filename="fixture.json"\r\nContent-Type: application/json\r\n\r\n'+json.dumps(payload)+f'\r\n--{boundary}--\r\n'
        req=urllib.request.Request(admin.base+'/admin-history.php',body.encode(),{'Content-Type':'multipart/form-data; boundary='+boundary})
        return admin.opener.open(req).read().decode()
    check(sender.get('/admin-history.php')[0]==403,'customer cannot access dataset management')
    check(admin.send('/admin-history.php',{'action':'delete','batch_id':'1'})[0]==403,'dataset mutations require CSRF')
    bad=json.loads(json.dumps(data)); bad['closing_balance_cents']=10001
    check('does not reconcile' in preview(bad),'invalid reconciliation rejected on upload')
    html=preview(data)
    token=re.search(r'name="preview_token" value="([^"]+)"',html)[1]
    check('Import preview' in html and '&lt;b&gt;Fixture' in html,'dataset preview escapes description')
    _,html,_=admin.send('/admin-history.php',{'action':'save','csrf_token':admin.token,'preview_token':token})
    check('Dataset saved' in html,'preview can be saved')
    batch=re.search(r'dataset-history.php\?batch=(\d+)',html)[1]
    html=sender.get('/dataset-history.php?batch='+batch)[1]
    check('Synthetic data' in html and '&lt;script&gt;' in html,'customer history labels data and escapes counterparties')
    for suffix in ['', '&export=json','&invoice=0']:
        check(recipient.get('/dataset-history.php?batch='+batch+suffix)[0]==404,'other customer denied dataset route '+suffix)
    exported=json.loads(sender.get('/dataset-history.php?batch='+batch+'&export=json')[1])
    check(exported['transactions'][0]['is_synthetic'] and exported['notice']=='Synthetic data','export preserves synthetic markers')
    check('Synthetic data' in sender.get('/dataset-history.php?batch='+batch+'&invoice=0')[1],'document identifies synthetic data')
    check(json.loads(sender.get('/api/accounts.php')[1])['accounts'][0]['balance']=='100.00','import leaves balance untouched')
    html=preview(data); token=re.search(r'name="preview_token" value="([^"]+)"',html)[1]
    check('only one dataset' in admin.send('/admin-history.php',{'action':'save','csrf_token':admin.token,'preview_token':token})[1],'duplicate account dataset rejected')
    admin.send('/admin-history.php',{'action':'delete','csrf_token':admin.token,'batch_id':batch})
    check(sender.get('/dataset-history.php?batch='+batch)[0]==404,'batch removal removes customer access')
    html=admin.send('/admin-history.php',{'action':'preview','csrf_token':admin.token,'account_id':'1','description':'Generated fixture','source':'generate','count':'50','start':'2001-01-01','end':'2026-01-01'})[1]
    check('Import preview' in html and '50 transactions' in html,'random generation previews account-matched data')

