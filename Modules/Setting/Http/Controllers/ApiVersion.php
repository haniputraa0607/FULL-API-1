<?php

namespace Modules\Setting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Setting\Http\Requests\Version\VersionList;

use App\Http\Models\Setting;
use Modules\Setting\Entities\Version;

use App\Lib\MyHelper;
use DB;

class ApiVersion extends Controller
{
    public function index(VersionList $request)
    {
        $post = $request->json()->all();
        $dbSetting = Setting::where('key', 'like', 'version_%')->get()->toArray();
        $dbDevice = Version::select('app_type', 'app_version')->orderBy('app_version', 'desc')->where('rules', '1')->get()->toArray();
        $setting = array();
        foreach ($dbSetting as $val) {
            $setting[$val['key']] = $val['value'];
        }
        $setting['Device'] = $dbDevice;
        $device = null;
        if (isset($post['device'])) {
            $device = $post['device'];
        } else {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            if (stristr($agent, 'okhttp')) $device = 'android';
            if (stristr($agent, 'android')) $device = 'android';
            if (stristr($agent, 'ios')) $device = 'ios';
        }
        if ($device != null) {
            if ($device == 'android') {
                foreach ($setting['Device'] as $value) {
                    if (in_array('Android', $value)) {
                        $value['app_type'] = strtolower($value['app_type']);
                        $compare_version[] = $value;
                    }
                }
                for ($i = 0; $i < count($compare_version); $i++) {
                    if ($post['version'] == $compare_version[$i]['app_version']) {
                        return response()->json(['status' => 'success']);
                    }
                }
                $versionRec = array_shift($compare_version);
                $setting['version_text_alert_mobile'] = str_replace('%version_app%', $versionRec['app_version'], $setting['version_text_alert_mobile']);
                return response()->json([
                    'status' => 'fail',
                    'image' => env('AWS_URL') . $setting['version_image_mobile'],
                    'text' => $setting['version_text_alert_mobile'],
                    'button_text' => $setting['version_text_button_mobile'],
                    'button_url' => $setting['version_playstore']
                ]);
            }
            if ($device == 'ios') {
                foreach ($setting['Device'] as $value) {
                    if (in_array('IOS', $value)) {
                        $value['app_type'] = strtolower($value['app_type']);
                        $compare_version[] = $value;
                    }
                }
                for ($i = 0; $i < count($compare_version); $i++) {
                    if ($post['version'] == $compare_version[$i]['app_version']) {
                        return response()->json(['status' => 'success']);
                    }
                }
                $versionRec = array_shift($compare_version);
                $setting['version_text_alert_mobile'] = str_replace('%version_app%', $versionRec['app_version'], $setting['version_text_alert_mobile']);
                return response()->json([
                    'status' => 'fail',
                    'image' => env('AWS_URL') . $setting['version_image_mobile'],
                    'text' => $setting['version_text_alert_mobile'],
                    'button_text' => $setting['version_text_button_mobile'],
                    'button_url' => $setting['version_appstore']
                ]);
            }
            if ($device == 'outlet') {
                foreach ($setting['Device'] as $value) {
                    if (in_array('OutletApp', $value)) {
                        $value['app_type'] = strtolower($value['app_type']);
                        $compare_version[] = $value;
                    }
                }
                for ($i = 0; $i < count($compare_version); $i++) {
                    if ($post['version'] == $compare_version[$i]['app_version']) {
                        return response()->json(['status' => 'success']);
                    }
                }
                $versionRec = array_shift($compare_version);
                $setting['version_text_alert_outlet'] = str_replace('%version_app%', $versionRec['app_version'], $setting['version_text_alert_outlet']);
                return response()->json([
                    'status' => 'fail',
                    'image' => env('AWS_URL') . $setting['version_image_outlet'],
                    'text' => $setting['version_text_alert_outlet'],
                    'button_text' => $setting['version_text_button_outlet'],
                    'button_url' => $setting['version_outletstore']
                ]);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Device tidak teridentifikasi']);
        }
    }

    function getVersion()
    {
        $display = Setting::where('key', 'LIKE', 'version%')->get();
        $version = Version::select('app_type', 'app_version', 'rules')->orderBy('app_version', 'desc')->get()->toArray();

        foreach ($version as $value) {
            if ($value['app_type'] == 'Android') {
                if ($value['rules'] == 1) {
                    $result['Android']['allowed'][] = $value;
                } else {
                    $result['Android']['not_allowed'][] = $value;
                }
            } elseif ($value['app_type'] == 'IOS') {
                if ($value['rules'] == 1) {
                    $result['IOS']['allowed'][] = $value;
                } else {
                    $result['IOS']['not_allowed'][] = $value;
                }
            } elseif ($value['app_type'] == 'OutletApp') {
                if ($value['rules'] == 1) {
                    $result['OutletApp']['allowed'][] = $value;
                } else {
                    $result['OutletApp']['not_allowed'][] = $value;
                }
            }
        }

        foreach ($display as $value) {
            $explode = explode('_', $value['key']);
            if (end($explode) == 'android') {
                $result['Android'][$value['key']] = $value['value'];
            } elseif (end($explode) == 'ios') {
                $result['IOS'][$value['key']] = $value['value'];
            } elseif (end($explode) == 'outlet') {
                $result['OutletApp'][$value['key']] = $value['value'];
            }
        }
        
        return response()->json(MyHelper::checkGet($result));
    }

    function updateVersion(Request $request)
    {
        $post = $request->json()->all();
        DB::beginTransaction();
        foreach ($post as $key => $data) {
            if ($key == 'Display') {
                foreach ($data as $keyData => $value) {
                    if ($keyData == 'version_image_mobile' || $keyData == 'version_image_outlet') {
                        if (!file_exists('img/setting/version/')) {
                            mkdir('img/setting/version/', 0777, true);
                        }
                        $upload = MyHelper::uploadPhoto($value, 'img/setting/version/');
                        if (isset($upload['status']) && $upload['status'] == "success") {
                            $value = $upload['path'];
                        } else {
                            return false;
                        }
                    }
                    $setting = Setting::updateOrCreate(['key' => $keyData], ['value' => $value]);
                    if (!$setting) {
                        DB::rollBack();
                        return response()->json(['status' => 'fail', 'messages' => $setting]);
                    }
                }
                DB::commit();
                return response()->json(['status' => 'success']);
            } else {
                $store = array_slice($data, -2, 2);
                foreach ($store as $keyStore => $value) {
                    $setting = Setting::updateOrCreate(['key' => $keyStore], ['value' => $value]);
                }
                if (!$setting) {
                    DB::rollBack();
                    return response()->json(['status' => 'fail', 'messages' => $setting]);
                }
                $sumVersion = array_pop($data);
                array_pop($data);
                // dd($data);
                if ($data == null) {
                    Version::where('app_type', $key)->delete();
                } else {
                    foreach ($data as $value) {
                        $reindex[] = $value;
                    }
                    for ($i = 0; $i < count($reindex); $i++) {
                        $reindex[$i]['app_type'] = $key;
                    }
                    foreach ($reindex as $value) {
                        if ($value['rules'] == 1) {
                            $checkData[] = $value;
                        }
                    }
                    if (count($checkData) > $sumVersion) {
                        asort($checkData);
                        $lastVersion = array_slice($checkData, -$sumVersion, $sumVersion);
                        $versionLast = array_column($lastVersion, 'app_version');
                    }
                    Version::where('app_type', $key)->delete();
                    foreach ($reindex as $value) {
                        if (!isset($versionLast)) {
                            $version = new Version;
                            $version->app_version = $value['app_version'];
                            $version->app_type = $value['app_type'];
                            $version->rules = $value['rules'];
                            $version->save();
                        } else {
                            if (in_array($value['app_version'], $versionLast)) {
                                $version = new Version;
                                $version->app_version = $value['app_version'];
                                $version->app_type = $value['app_type'];
                                $version->rules = $value['rules'];
                                $version->save();
                            } else {
                                $version = new Version;
                                $version->app_version = $value['app_version'];
                                $version->app_type = $value['app_type'];
                                $version->rules = 0;
                                $version->save();
                            }
                        }
                    }
                }
                DB::commit();
                return response()->json(['status' => 'success']);
            }
        }
    }
}
