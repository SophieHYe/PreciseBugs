from django.shortcuts import render
from django.http import HttpResponse, HttpResponseRedirect, HttpResponseNotFound
from models import Record,Store
from django.db import connection
from recordstoreapp.forms import RecordForm,StoreForm
from django.shortcuts import redirect
from django.core.urlresolvers import reverse

def index(request):
	context_dict = {}
	return render(request, 'index.html', context_dict)

def about(request):
	context_dict = {}
	return render(request, 'about.html', context_dict)

def faq(request):
	context_dict = {}
	return render(request, 'faq.html', context_dict)

def contact(request):
	context_dict = {}
	return render(request, 'contact.html', context_dict)

def search(request):
	context_dict = {}
	q = request.GET['q'].replace('%', '').replace('_', '').strip()
	if 'q' in request.GET and q != '':
		q = '%' + q + '%'
		cursor = connection.cursor()
		cursor.execute("SELECT id,title,artist,cover FROM recordstoreapp_record WHERE title like %s or artist like %s or label like %s or cat_no like %s;", [q,q,q,q])
		rec_list=cursor.fetchall()
		

		total=len(rec_list)
		pg=int(request.GET['page']) if 'page' in request.GET else 1
		ub=min(pg*12, total)

		context_dict['rec_list'] = rec_list[(pg-1)*12:ub]
		maxrange = int(total/12)
		if total%12 > 0: 
			maxrange = maxrange + 1
		if maxrange == 1: 
			maxrange = 0
		context_dict['range'] = range(1,maxrange+1)
		print total
		context_dict['q'] = q

	return render(request, 'search.html', context_dict)


def new_releases(request):
	context_dict = {}
	
	rec_list = Record.objects.all()
	total=len(rec_list)
	pg=int(request.GET['page']) if 'page' in request.GET else 1
	ub=min(pg*12, total)

	context_dict['rec_list'] = rec_list[(pg-1)*12:ub]
	maxrange = int(total/12)
	if total%12 > 0: 
		maxrange = maxrange + 1
	if maxrange == 1: 
		maxrange = 0
	context_dict['range'] = range(1,maxrange+1)

	return render(request, 'releases.html', context_dict)


def record_view(request):
	page_id = None
	context_dict = {}
	if request.method == 'GET':
		if 'record_id' in request.GET:
			record_id = request.GET['record_id']
			if record_id:
				record = Record.objects.get(id=record_id)
				context_dict['stores']=Store.objects.filter(record=record)#record.stores.all()
				context_dict['record'] = record
	return render(request, 'record.html', context_dict)
	
def add_record(request):
	if request.method == 'POST':
		form = RecordForm(request.POST)

		if form.is_valid():
			form.save(commit=True)

			return index(request)
		else:
			print form.errors
	else:
		form = RecordForm()

	return render(request, 'add_record.html', {'form': form})
	
def add_store(request, record_id):
	try:
	    if not isinstance(record_id, int):
	        rec = None
	    else:
		    rec = Record.objects.get(id=record_id)
	except:
		rec = None
	if request.method == 'POST':
		form = StoreForm(request.POST)
		if form.is_valid():
			if rec:
				s = form.save(commit=False)
				s.save()
				rec.stores.add(s)
				return redirect(reverse('records') + '?record_id=' + record_id)
		else:
			print form.errors
	else:
		form = StoreForm()

	context_dict = {'form': form, 'record': rec}

	return render(request, 'add_store.html', context_dict)