<?php
namespace SCart\Core\Front\Controllers;

use App\Http\Controllers\RootFrontController;
use SCart\Core\Front\Models\ShopBanner;
use SCart\Core\Front\Models\ShopProduct;
use SCart\Core\Front\Models\ShopEmailTemplate;
use SCart\Core\Front\Models\ShopNews;
use SCart\Core\Front\Models\ShopPage;
use SCart\Core\Front\Models\ShopSubscribe;
use Illuminate\Http\Request;

class ShopContentController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Home page
     * @return [view]
     */
    public function index()
    {
        sc_check_view($this->templatePath . '.screen.home');
        return view(
            $this->templatePath . '.screen.home',
            array(
                'title' => sc_store('title'),
                'keyword' => sc_store('keyword'),
                'description' => sc_store('description'),
                'layout_page' => 'home',
            )
        );
    }

    /**
     * Shop page
     * @return [view]
     */
    public function shop()
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $filter_sort = request('filter_sort') ?? '';
        $filterArr = [
            'price_desc' => ['price', 'desc'],
            'price_asc' => ['price', 'asc'],
            'sort_desc' => ['sort', 'desc'],
            'sort_asc' => ['sort', 'asc'],
            'id_desc' => ['id', 'desc'],
            'id_asc' => ['id', 'asc'],
        ];
        if (array_key_exists($filter_sort, $filterArr)) {
            $sortBy = $filterArr[$filter_sort][0];
            $sortOrder = $filterArr[$filter_sort][1];
        }

        $products = (new ShopProduct)
            ->setLimit(sc_config('product_list'))
            ->setPaginate()
            ->setSort([$sortBy, $sortOrder])
            ->getData();

        sc_check_view($this->templatePath . '.screen.shop_home');
        return view(
            $this->templatePath . '.screen.shop_home',
            array(
                'title' => trans('front.shop'),
                'keyword' => sc_store('keyword'),
                'description' => sc_store('description'),
                'products' => $products,
                'layout_page' => 'shop_home',
                'filter_sort' => $filter_sort,
            )
        );
    }

    /**
     * search product
     * @return [view]
     */
    public function search()
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $filter_sort = request('filter_sort') ?? '';
        $filterArr = [
            'price_desc' => ['price', 'desc'],
            'price_asc' => ['price', 'asc'],
            'sort_desc' => ['sort', 'desc'],
            'sort_asc' => ['sort', 'asc'],
            'id_desc' => ['id', 'desc'],
            'id_asc' => ['id', 'asc'],
        ];
        if (array_key_exists($filter_sort, $filterArr)) {
            $sortBy = $filterArr[$filter_sort][0];
            $sortOrder = $filterArr[$filter_sort][1];
        }
        $keyword = request('keyword') ?? '';
        $products = (new ShopProduct)->setKeyword($keyword)
                    ->setSort([$sortBy, $sortOrder])
                    ->setPaginate()
                    ->setLimit(sc_config('product_list'))
                    ->getData();

        sc_check_view($this->templatePath . '.screen.shop_product_list');          
        return view(
            $this->templatePath . '.screen.shop_product_list',
            array(
                'title' => trans('front.search') . ': ' . $keyword,
                'products' => $products,
                'layout_page' => 'product_list',
                'filter_sort' => $filter_sort,
            )
        );
    }

    /**
     * Process click banner
     *
     * @param   [int]  $id  
     *
     */
    public function clickBanner($id){
        $banner = ShopBanner::find($id);
        if($banner) {
            $banner->click +=1;
            $banner->save();
            return redirect(url($banner->url??'/'));
        }
        return redirect(url('/'));
    }

    /**
     * form contact
     * @return [view]
     */
    public function getContact()
    {
        $viewCaptcha = '';
        if(sc_captcha_method() && in_array('contact', sc_captcha_page())) {
            if (view()->exists(sc_captcha_method()->pathPlugin.'::render')){
                $dataView = [
                    'titleButton' => trans('front.contact_form.submit'),
                    'idForm' => 'form-process',
                    'idButtonForm' => 'button-form-process',
                ];
                $viewCaptcha = view(sc_captcha_method()->pathPlugin.'::render', $dataView)->render();
            }
        }
        sc_check_view($this->templatePath . '.screen.shop_contact');
        return view(
            $this->templatePath . '.screen.shop_contact',
            array(
                'title'       => trans('front.contact'),
                'description' => '',
                'keyword'     => '',
                'layout_page' => 'shop_contact',
                'og_image'    => '',
                'viewCaptcha' => $viewCaptcha,
            )
        );
    }


    /**
     * process contact form
     * @param  Request $request [description]
     * @return [mix]
     */
    public function postContact(Request $request)
    {
        $data   = $request->all();
        $validate = [
            'name' => 'required',
            'title' => 'required',
            'content' => 'required',
            'email' => 'required|email',
            'phone' => 'required|regex:/^0[^0][0-9\-]{7,13}$/',
        ];
        $message = [
            'name.required' => trans('validation.required', ['attribute' => trans('front.contact_form.name')]),
            'content.required' => trans('validation.required', ['attribute' => trans('front.contact_form.content')]),
            'title.required' => trans('validation.required', ['attribute' => trans('front.contact_form.title')]),
            'email.required' => trans('validation.required', ['attribute' => trans('front.contact_form.email')]),
            'email.email' => trans('validation.email', ['attribute' => trans('front.contact_form.email')]),
            'phone.required' => trans('validation.required', ['attribute' => trans('front.contact_form.phone')]),
            'phone.regex' => trans('validation.phone', ['attribute' => trans('front.contact_form.phone')]),
        ];

        if(sc_captcha_method() && in_array('contact', sc_captcha_page())) {
            $data['captcha_field'] = $data[sc_captcha_method()->getField()] ?? '';
            $validate['captcha_field'] = ['required', 'string', new \SCart\Core\Rules\CaptchaRule];
        }
        $validator = \Illuminate\Support\Facades\Validator::make($data, $validate, $message);
        if ($validator->fails()) {
            return redirect()->back()
                        ->withErrors($validator)
                        ->withInput();
        }
        //Send email
        $data['content'] = str_replace("\n", "<br>", $data['content']);

        if (sc_config('contact_to_admin')) {
            $checkContent = (new ShopEmailTemplate)
                ->where('group', 'contact_to_admin')
                ->where('status', 1)
                ->first();
            if ($checkContent) {
                $content = $checkContent->text;
                $dataFind = [
                    '/\{\{\$title\}\}/',
                    '/\{\{\$name\}\}/',
                    '/\{\{\$email\}\}/',
                    '/\{\{\$phone\}\}/',
                    '/\{\{\$content\}\}/',
                ];
                $dataReplace = [
                    $data['title'],
                    $data['name'],
                    $data['email'],
                    $data['phone'],
                    $data['content'],
                ];
                $content = preg_replace($dataFind, $dataReplace, $content);
                $dataView = [
                    'content' => $content,
                ];

                $config = [
                    'to' => sc_store('email'),
                    'replyTo' => $data['email'],
                    'subject' => $data['title'],
                ];
                sc_send_mail($this->templatePath . '.mail.contact_to_admin', $dataView, $config, []);
            }
        }

        return redirect()
            ->route('contact')
            ->with('success', trans('front.thank_contact'));
    }

    /**
     * Render page
     * @param  [string] $alias
     */
    public function pageDetail($alias)
    {
        $page = (new ShopPage)->getDetail($alias, $type = 'alias');
        if ($page) {

            sc_check_view($this->templatePath . '.screen.shop_page');
            return view(
                $this->templatePath . '.screen.shop_page',
                array(
                    'title' => $page->title,
                    'description' => $page->description,
                    'keyword' => $page->keyword,
                    'page' => $page,
                    'og_image' => asset($page->getImage()),
                    'layout_page' => 'shop_page',
                )
            );
        } else {
            return $this->pageNotFound();
        }
    }

    /**
     * Render news
     * @return [type] [description]
     */
    public function news()
    {
        $news = (new ShopNews)
            ->setLimit(sc_config('news_list'))
            ->setPaginate()
            ->getData();

        sc_check_view($this->templatePath . '.screen.shop_news');
        return view(
            $this->templatePath . '.screen.shop_news',
            array(
                'title' => trans('front.blog'),
                'description' => sc_store('description'),
                'keyword' => sc_store('keyword'),
                'news' => $news,
                'layout_page' => 'news_list',
            )
        );
    }

    /**
     * News detail
     *
     * @param   [string]  $alias 
     *
     * @return  view
     */
    public function newsDetail($alias)
    {
        $news = (new ShopNews)->getDetail($alias, $type ='alias');
        if ($news) {
            sc_check_view($this->templatePath . '.screen.shop_news_detail');
            return view(
                $this->templatePath . '.screen.shop_news_detail',
                array(
                    'title' => $news->title,
                    'news' => $news,
                    'description' => $news->description,
                    'keyword' => $news->keyword,
                    'og_image' => asset($news->getImage()),
                    'layout_page' => 'news_detail',
                )
            );
        } else {
            return $this->pageNotFound();
        }
    }

    /**
     * email subscribe
     * @param  Request $request
     * @return json
     */
    public function emailSubscribe(Request $request)
    {
        $validator = $request->validate([
            'subscribe_email' => 'required|email',
            ], [
            'email.required' => trans('validation.required'),
            'email.email'    => trans('validation.email'),
        ]);
        $data       = $request->all();
        $checkEmail = ShopSubscribe::where('email', $data['subscribe_email'])
            ->first();
        if (!$checkEmail) {
            ShopSubscribe::insert(['email' => $data['subscribe_email']]);
        }
        return redirect()->back()
            ->with(['success' => trans('subscribe.subscribe_success')]);
    }

}
