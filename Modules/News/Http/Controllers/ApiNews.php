<?php

namespace Modules\News\Http\Controllers;

use App\Http\Models\News;
use App\Http\Models\NewsFormStructure;
use App\Http\Models\NewsFormData;
use App\Http\Models\NewsFormDataDetail;
use App\Http\Models\NewsOutlet;
use App\Http\Models\NewsProduct;
use App\Http\Models\Configs;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;
use File;

use Modules\News\Http\Requests\Create;
use Modules\News\Http\Requests\Update;
use Modules\News\Http\Requests\CreateRelation;
use Modules\News\Http\Requests\DeleteRelation;

class ApiNews extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
    }

    public $saveImage = "img/news/";
    public $endPoint  = "http://localhost/crmsys-api/public/"; 

    /* Cek inputan */
    function cekInputan($post = []) {
        
        $data = [];

        if (isset($post['news_slug'])) {
            $data['news_slug'] = $post['news_slug'];
        }
        
        if (isset($post['news_title'])) {
            $data['news_title'] = $post['news_title'];
        }

        if (isset($post['news_content_short'])) {
            $data['news_content_short'] = $post['news_content_short'];
        }

        if (isset($post['news_content_long'])) {
            $data['news_content_long'] = $post['news_content_long'];
        }

        if (isset($post['news_image_luar'])) {
            $upload = MyHelper::uploadPhotoStrict($post['news_image_luar'], $this->saveImage, 500, 500);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['news_image_luar'] = $upload['path'];
            }
            else {
                $result = [
                    'error'    => 1,
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return $result;
            }
        }

        if (isset($post['news_image_dalam'])) {
            $upload = MyHelper::uploadPhotoStrict($post['news_image_dalam'], $this->saveImage, 500, 250);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['news_image_dalam'] = $upload['path'];
            }
            else {
                $result = [
                    'error'    => 1,
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return $result;
            }

        }

        if (isset($post['news_post_date'])) {
            $data['news_post_date'] = $post['news_post_date'];
        }

        if (isset($post['news_publish_date'])) {
            $data['news_publish_date'] = $post['news_publish_date'];
        }

        if (isset($post['news_expired_date'])) {
            $data['news_expired_date'] = $post['news_expired_date'];
        }

        if (isset($post['news_video_text'])) {
            $data['news_video_text'] = $post['news_video_text'];
        }
        else {
            $data['news_video_text'] = null;   
        }

        if (isset($post['news_video'])) {
            $data['news_video'] = $post['news_video'];
        }
        else {
            $data['news_video'] = null;
        }

        if (isset($post['news_event_date_start'])) {
            $data['news_event_date_start'] = $post['news_event_date_start'];
        }
        else {
            $data['news_event_date_start'] = null;   
        }
		
		if (isset($post['news_button_form_text'])) {
            $data['news_button_form_text'] = $post['news_button_form_text'];
        }
        else {
            $data['news_button_form_text'] = null;   
        }
		
		 if (isset($post['news_button_form_expired'])) {
            $data['news_button_form_expired'] = $post['news_button_form_expired'];
        }
        else {
            $data['news_button_form_expired'] = null;   
        }
		
        if (isset($post['news_event_date_end'])) {
            $data['news_event_date_end'] = $post['news_event_date_end'];
        }
        else {
            $data['news_event_date_end'] = null;   
        }

        if (isset($post['news_event_time_start'])) {
            $data['news_event_time_start'] = $post['news_event_time_start'];
        }
        else {
            $data['news_event_time_start'] = null;   
        }

        if (isset($post['news_event_time_end'])) {
            $data['news_event_time_end'] = $post['news_event_time_end'];
        }
        else {
            $data['news_event_time_end'] = null;   
        }

        if (isset($post['news_event_location_name'])) {
            $data['news_event_location_name'] = $post['news_event_location_name'];
        }
        else {
            $data['news_event_location_name'] = null;   
        }

        if (isset($post['news_event_location_phone'])) {
            $data['news_event_location_phone'] = $post['news_event_location_phone'];
        }
        else {
            $data['news_event_location_phone'] = null;   
        }

        if (isset($post['news_event_location_address'])) {
            $data['news_event_location_address'] = $post['news_event_location_address'];
        }
        else {
            $data['news_event_location_address'] = null;   
        }

        if (isset($post['news_event_location_map'])) {
            $data['news_event_location_map'] = $post['news_event_location_map'];
        }
        else {
            $data['news_event_location_map'] = null;   
        }

        if (isset($post['news_event_latitude'])) {
            $data['news_event_latitude'] = $post['news_event_latitude'];
        }
        else {
            $data['news_event_latitude'] = null;   
        }

        if (isset($post['news_event_longitude'])) {
            $data['news_event_longitude'] = $post['news_event_longitude'];
        }
        else {
            $data['news_event_longitude'] = null;   
        }

        if (isset($post['news_outlet_text'])) {
            $data['news_outlet_text'] = $post['news_outlet_text'];
        }
        else {
            $data['news_outlet_text'] = null;   
        }

        if (isset($post['news_product_text'])) {
            $data['news_product_text'] = $post['news_product_text'];
        }
        else {
            $data['news_product_text'] = null;   
        }

        if (isset($post['news_form_success_message'])) {
            $data['news_form_success_message'] = $post['news_form_success_message'];
        }
		
		if (isset($post['customform'])) {
            $data['customform'] = $post['customform'];   
        }
        else {
            $data['customform'] = null;   
        }

        return $data;
    }

    /* Create News */
    function create(Create $request) {
            // data news
            $post = $request->json()->all();
            if(isset($request->news_video)){
                $youtube = MyHelper::parseYoutube($post['news_video']);
                if($youtube['status'] == 'success'){
                    $post['news_video'] = $youtube['data'];
                }else{
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['url youtube not valid.']
                    ]);
                }
            }

            $data = $this->cekInputan($post);

            if (isset($data['error'])) {
                unset($data['error']);        
                return response()->json($data);
            }
            // dd($data);

            // pengecekan apakah slugnya udah ada atau belum
            if ( $this->cekSlug("", $data['news_slug']) ) {
                $customform = $data['customform'];
                unset($data['customform']);
                
                DB::beginTransaction();

                $save = News::create($data);
                // jika ada custom form 
                if(!empty($customform)){
                    foreach($customform as $key => $form){
                        $dataForm = [];
                        $dataForm['id_news'] = $save->id_news;
                        $dataForm['form_input_types'] = $form['form_input_types'];
                        if($form['form_input_options'] != "")
                            $dataForm['form_input_options'] = $form['form_input_options'];
                        else
                            $dataForm['form_input_options'] = null;
                        
                        $dataForm['form_input_label'] = $form['form_input_label'];
                        $dataForm['form_input_autofill'] = $form['form_input_autofill'];
                        $dataForm['is_required'] = $form['is_required'];
                        $dataForm['is_unique'] = $form['is_unique'];
                        $dataForm['position'] = $key + 1;

                        $saveForm = NewsFormStructure::create($dataForm);

                        if ( !($save && $saveForm) ) {
                            DB::rollback();
                        }
                    }
                }
                DB::commit();

                return response()->json(MyHelper::checkUpdate($save));
            }
            else {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['slug already used another news.']
                ]);
            }
            // $save = News::create($data);

            return response()->json(MyHelper::checkCreate($save));
    }

    /* Upadate News */
    function update(Update $request) {
            // info news
            $post = $request->json()->all();
            if(isset($post['news_video'])){
                $youtube = MyHelper::parseYoutube($post['news_video']);
                if($youtube['status'] == 'success'){
                    $post['news_video'] = $youtube['data'];
                }else{
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['url youtube not valid.']
                    ]);
                }
            }
            $dataNews = News::where('id_news', $request->json('id_news'))->get()->toArray();

            if (empty($dataNews)) {
                return response()->json(MyHelper::checkGet($dataNews));
            }
            // data news
            $data = $this->cekInputan($post);

            if (isset($data['error'])) {
                unset($data['error']);
                return response()->json($data);
            }

            // pengecekan apakah slugnya udah ada atau belum
            if ($this->cekSlug($request->json('id_news'), $request->json('news_slug'))) {
                $customform = $data['customform'];
				unset($data['customform']);
				
                $save = News::where('id_news', $request->json('id_news'))->update($data);

                // jika ada upload file
                if (isset($data['news_image_luar'])) {
                    MyHelper::deletePhoto($dataNews[0]['news_image_luar']);
                }
                if (isset($data['news_image_dalam'])) {
                    MyHelper::deletePhoto($dataNews[0]['news_image_dalam']);
                }
                // jika ada upload 
				if(!empty($customform)){
					$clear = NewsFormStructure::where('id_news', $request->json('id_news'))->delete();
					foreach($customform as $key => $form){
						$dataForm = [];
						$dataForm['id_news'] = $request->json('id_news');
						$dataForm['form_input_types'] = $form['form_input_types'];
						if($form['form_input_options'] != "")
							$dataForm['form_input_options'] = $form['form_input_options'];
						else
							$dataForm['form_input_options'] = null;
                        $dataForm['form_input_label'] = $form['form_input_label'];
                        $dataForm['form_input_autofill'] = $form['form_input_autofill'];
                        $dataForm['is_required'] = $form['is_required'];
                        $dataForm['is_unique'] = $form['is_unique'];
						$dataForm['position'] = $key + 1;
						$saveForm = NewsFormStructure::create($dataForm);
					}
				}
                return response()->json(MyHelper::checkUpdate($save));
            }
            else {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['slug already used another news.']
                ]);
            }
    }

    /* Cek Slug when update */
    function cekSlug($id_news=null, $slug) {
        if($id_news=="")
            $cek = News::where('news_slug', $slug)->first();
        else
            $cek = News::where('news_slug', $slug)->select('id_news')->first();

        if (empty($cek)) {
            return true;
        }
        else {
            if ($id_news!="" && $cek->id_news == $id_news) {
                return true;
            }
            else {
                return false;
            }
        }
    }

    /* Delete News */
    function delete(Request $request) {
            // info news
            $dataNews = News::where('id_news', $request->json('id_news'))->get()->toArray();

            if (empty($dataNews)) {
                return response()->json(MyHelper::checkGet($dataNews));
            }

            // hapus semua relasi tablenya
            $this->deleteRelationTable($request->json('id_news'));

            // hapus newsnya
            $delete = News::where('id_news', $request->json('id_news'))->delete();

            // hapus filenya
            MyHelper::deletePhoto($dataNews[0]['news_image_luar']);
            MyHelper::deletePhoto($dataNews[0]['news_image_dalam']);

            return response()->json(MyHelper::checkDelete($delete));
    }

    /* Delete news di berbagai table */
    function deleteRelationTable($id_news) {
        $table = ['news_outlets', 'news_products', 'news_form_structures', 'news_form_datas', 'news_form_data_details'];

        foreach ($table as $value) {
            $delete = DB::table($value)->where('id_news', $id_news)->delete();
        }

        return true;
    }

    /* Create Relasi Outlet Product */
    function createRelation(CreateRelation $request) {

        $data['id_news'] = $request->json('id_news');

        switch ($request->json('type')) {
            case 'outlet' :
                $data['id_outlet'] = $request->json('id_outlet');

                // save
                $insert = NewsOutlet::insert($data);
            break;

            case 'product' :
                $data['id_product'] = $request->json('id_product');

                // save
                $insert = NewsProduct::insert($data);
            break;

            default:
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['type is not define.']
                ]);

                break;
        }

        // pake check update karena kalo insert cuma return true or false
        return response()->json(MyHelper::checkUpdate($insert));
    }

    /* Delete Relasi Outlet */
    function deleteRelation(DeleteRelation $request) {
        $post = $request->json()->all();

        switch ($request->json('type')) {
            case 'outlet' :
                // delete
                $delete = NewsOutlet::where('id_news', $request->json('id_news'))->delete();
            break;

            case 'product' :
                // delete
                $delete = NewsProduct::where('id_news', $request->json('id_news'))->delete();
            break;

            default:
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['type is not define.']
                ]);
            break;
        }

        return response()->json(MyHelper::checkDelete($delete));
    }

    /* List */
    function listNews(Request $request) {
            $post = $request->json()->all();

            $news = News::select('*')->with('news_form_structures'); 

            if (isset($post['id_news'])) {
                $news->where('id_news', $post['id_news'])->with(['newsOutlet', 'newsProduct', 'newsOutlet.outlet.city', 'newsOutlet.outlet.photos', 'newsProduct.product.photos']);
            }
            
            if (isset($post['news_slug'])) {
                $news->slug($post['news_slug'])->with(['newsOutlet', 'newsProduct', 'newsOutlet.outlet.city', 'newsOutlet.outlet.photos', 'newsProduct.product.photos']);
            }

            if (isset($post['published'])) {
                $now = date('Y-m-d');
                $news->where('news_publish_date', '<=', $now)->where('news_expired_date', '>=', $now)->orWhere('news_expired_date', null);
            }

            if (isset($post['admin'])) {
                $news = $news->orderBy('news_post_date', 'DESC')->get()->toArray();
            }
            else {

                if (!isset($post['id_news'])) {
                    $news = $news->orderBy('news_post_date', 'DESC')->paginate(10);
                }
                else {
                    $news = $news->orderBy('news_post_date', 'DESC')->get()->toArray();
                }
            }

            return response()->json(MyHelper::checkGet($news));
    }

    function webview(Request $request)
    {
        $post = $request->json()->all();

        $news = News::select('news_post_date', 'news_publish_date', 'news_title')->where('id_news', $post['id_news'])->get(); 

        if (count($news) == 0) {
            return response()->json(['status' => 'fail', 'messages' => ['News not found']]);
        }

        $news[0]['url'] = env('VIEW_URL').'/news/webview/'.$post['id_news'];

        return response()->json(MyHelper::checkGet($news));
    }

    // get news for custom form webview
    public function getNewsById(Request $request)
    {
        $post = $request->json()->all();
        $news = News::with('news_form_structures')->where('id_news', $post['id_news'])->first();

        return response()->json(MyHelper::checkGet($news));
    }

    // submit custom form webview
    public function customForm(Request $request)
    {
        $post = $request->json()->all();

        DB::beginTransaction();
            $newsFormData = NewsFormData::create([
                'id_news' => $post['id_news'],
                'id_user' => $post['id_user']
            ]);
            // dd($newsFormData);

            $id_news = $post['id_news'];

            if ($newsFormData) {
                foreach ($post['news_form'] as $key => $news_form) {
                    $value = $this->checkCustomFormValue($news_form);
                    if(!$value && $news_form['input_value']!=""){
                        DB::rollback();
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['Fail to save data.'],
                        ]);
                    }

                    // check unique
                    if ($news_form['is_unique'] == 1) {
                        $check = NewsFormDataDetail::where('id_news', $id_news)
                            ->where('form_input_label', $news_form['input_label'])
                            ->where('form_input_value', $news_form['input_value'])
                            ->first();
                        if ($check) {
                            return response()->json([
                                'status'   => 'fail',
                                'messages' => ['Please check your input again.'],
                                'is_unique'=> 1
                            ]);
                        }
                    }
                    $newsFormDataDetail = NewsFormDataDetail::create([
                        'id_news_form_data' => $newsFormData->id_news_form_data,
                        'id_news' => $id_news,
                        'form_input_label' => $news_form['input_label'],
                        'form_input_value' => $value
                    ]);

                    if ( !($newsFormData && $newsFormDataDetail) ) {
                        DB::rollback();

                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['Fail to save data.']
                        ]);
                    }
                }
            }
            else {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Fail to save data.']
                ]);
            }

        DB::commit();

        $news = News::find($id_news);

        return response()->json([
            'status'   => 'success',
            'messages' => [$news->news_form_success_message]
        ]);
    }

    // format the input to save in db
    private function checkCustomFormValue($news_form)
    {
        $input_value = $news_form['input_value'];

        switch ($news_form['input_type']) {
            case 'Date':
                $value = date('Y-m-d', strtotime($input_value));
                break;
            /*case 'Date & Time':
                $value = date('Y-m-d H:i', strtotime($input_value));
                break;*/
            case 'Multiple Choice':
                $value = implode(', ', $input_value);
                break;
            case 'Image Upload':
                $value = "";
                if ($input_value != "") {
                    $path = 'img/news-custom-form/';
                    $upload = MyHelper::uploadPhoto($input_value, $path);
                    if (isset($upload['status']) && $upload['status'] == "success") {
                        $value = $upload['path'];
                    } else {
                        return false;
                    }
                }
                break;
            
            default:
                $value = $input_value;
                break;
        }

        return $value;
    }

    // upload file in custom form webview
    public function customFormUploadFile(Request $request)
    {
        $file = $request->file('news_form_file');
        $path = public_path('upload/news-custom-form/');
        $ext = $request->file('news_form_file')->getClientOriginalExtension();
        $filename = $file->getClientOriginalName();
        $filename_only = explode('.', $filename)[0];
        $new_filename = $filename_only ."-". date('Ymd-His') .".". $ext;

        $upload = $file->move($path, $new_filename);

        if ($upload) {
            $result = [
                        'status'    => 'success',
                        'filename'  => 'upload/news-custom-form/' . $new_filename
                    ];
            return response()->json($result);
        }
        else{
            $result = [
                        'status'    => 'fail',
                        'messages'  => ['Upload File Failed.']
                    ];
            return response()->json($result);
        }
    }

    public function formData(Request $request)
    {
        $post = $request->json()->all();

        $news_form_structures = NewsFormStructure::where('id_news', $post['id_news'])->pluck('form_input_label');
        $form_data = NewsFormData::with('news_form_data_details')->select('id_news_form_data', 'id_news', 'id_user', 'created_at')->where('id_news', $post['id_news'])->get();
        $news_form_data = $form_data->map(function ($item, $key) use ($news_form_structures) {
            // get user if not null
            if ($item->id_user != null) {
                $item['user'] = $item->user;
            }

            // assign form value based on form structure
            foreach ($news_form_structures as $form_structure) {
                $item[$form_structure] = "";
                foreach ($item->news_form_data_details as $detail) {
                    if ($detail->form_input_label == $form_structure) {
                        $item[$form_structure] = $detail->form_input_value;
                    }
                }
            }

            unset($item['news_form_data_details']);

            return $item;
        });
        $news_form_data->all();

        $data['news_form_structures'] = $news_form_structures;
        $data['news_form_data'] = $news_form_data;

        return response()->json(MyHelper::checkGet($data));
    }
}