<?php

/**
 * @package iCMS
 * @copyright 2007-2017, iDreamSoft
 * @license http://www.idreamsoft.com iDreamSoft
 * @author coolmoo <idreamsoft@qq.com>
 */
class contentApp {
    public $methods = array('iCMS', 'hits','vote', 'good', 'bad', 'like_comment', 'comment');
    public $appid   = null;
    public $app     = null;
    public $table   = null;

    public function __construct($data) {
        $this->data    = $data;
        $this->appid   = $data['id'];
        $this->app     = $data['app'];
        $this->table   = apps::get_table($data);
        $this->primary = $this->table['primary'];
        $this->id      = (int)$_GET[$this->primary];
        unset($data);
    }
    public function API_iCMS(){
        return $this->do_iCMS();
    }
    public function do_iCMS($a = null) {
        return $this->content($this->id, isset($_GET['p']) ? (int) $_GET['p'] : 1);;
    }
    public function hooked($data){
        return iPHP::hook($this->app,$data,iCMS::$config['hooks'][$this->app]);
    }
    public function content($id, $page = 1, $tpl = true) {
        $rs = apps_mod::get_data($this->data,$id);
        $rs OR iPHP::error_404('找不到'.$this->data['name'].': <b>'.$this->primary.':' . $id . '</b>', 10001);
        if ($rs['url']) {
            if (iView::$gateway == "html") {
                return false;
            } else {
                $this->API_hits($id);
                iPHP::redirect($rs['url']);
            }
        }

        $rs = $this->value($rs,$page,$tpl);
        $rs = $this->hooked($rs);

        if ($tpl) {
            iView::clear_tpl();
            $app_tpl = empty($rs['tpl']) ? $rs['category']['template'][$this->app] : $rs['tpl'];
            strstr($tpl, '.htm') && $app_tpl = $tpl;
            iView::assign('category', $rs['category']);unset($rs['category']);
            iView::assign('app', $this->app_lite());
            iView::assign($this->app, $rs);
            iView::assign('content', $rs);
            iView::assign('func_list', 'iCMS:test:list');
            iView::assign('func_app', $this->app);

            $html = iView::render($app_tpl, $this->app);
            if (iView::$gateway == "html") {
                return array($html, $rs);
            }
        } else {
            return $rs;
        }
    }

    public function app_lite($data=null) {
        $data===null && $data = $this->data;
        unset($data['table'],$data['config'],$data['fields'],$data['menu']);
        return $data;
    }
    public function value($rs, $page = 1, $tpl = false) {
        $rs['appid'] = $this->appid;
        $category = categoryApp::category($rs['cid'],false);

        if ($tpl) {
            $category OR iPHP::error_404('找不到该'.$this->data['name'].'的栏目缓存<b>cid:' . $rs['cid'] . '</b> 请更新栏目缓存或者确认栏目是否存在', 10002);
        } else {
            if (empty($category)) {
                return false;
            }

        }

        if ($category['status'] == 0) {
            return false;
        }

        if (iView::$gateway == "html" && $tpl && (strstr($category['rule'][$this->app], '{PHP}') || $category['outurl'] || $category['mode'] == "0")) {
            return false;
        }

        $rs['category'] = categoryApp::get_lite($category);

        $rs['iurl'] = iURL::get($this->app, array($rs, $category));
        $rs['url'] OR $rs['url'] = $rs['iurl']->href;
        $rs['link'] = '<a href="'.$rs['url'].'" class="'.$this->app.'">'.$rs['title'].'</a>';

        ($tpl && $category['mode'] == '1') && iCMS::redirect_html($rs['iurl']->path, $rs['iurl']->href);

        if($category['mode'] && stripos($rs['url'], '.php?')===false){
            iURL::page_url($rs['iurl']);
        }
        if ($rs['postype']) {
            $rs['user'] = user::empty_info($rs['userid'], '#' . $rs['editor']);
        } else {
            $rs['user'] = user::info($rs['userid'], $rs['editor']);
        }
        $rs['hits'] = array(
            'script' => iCMS_API . '?app='.$this->app.'&do=hits&cid=' . $rs['cid'] . '&id=' . $rs[$this->primary],
            'count'  => $rs['hits'],
            'today'  => $rs['hits_today'],
            'yday'   => $rs['hits_yday'],
            'week'   => $rs['hits_week'],
            'month'  => $rs['hits_month'],
        );
        $rs['comment'] = array(
            'url'   => iCMS_API . '?app='.$this->app.'&do=comment&appid='.$rs['appid'].'&iid='.$rs[$this->primary].'&cid='.$rs['cid'].'',
            'count' => $rs['comments'],
        );
        $rs['param'] = array(
            "appid" => $rs['appid'],
            "iid"   => $rs['id'],
            "cid"   => $rs['cid'],
            "suid"  => $rs['userid'],
            "title" => $rs['title'],
            "url"   => $rs['url'],
        );

        $fields = array();
        if($this->data['fields']){
            $fields = iFormer::fields($this->data['fields']);
        }
        foreach ($fields as $key => $field) {
            $value  = $rs[$key];
            $values = array();
            $nkey   = null;
            switch ($field['type']) {
                case 'multi_image':
                    $nkey     = $key.'_array';
                    $valArray = explode("\n", $value);
                    foreach ($valArray as $i => $val) {
                        $val && $values[$i]= filesApp::get_pic(trim($val));
                    }
                break;
                case 'image':
                    $nkey   = $key.'_array';
                    $values = filesApp::get_pic($value);
                break;
                case 'file':
                    $nkey = $key.'_file';
                    $pi   = pathinfo($value);
                    $values   = array(
                        'name' => $pi['filename'],
                        'ext'  => $pi['extension'],
                        'dir'  => $pi['dirname'],
                        'url'  => filesApp::get_url($pi['filename'],'download')
                    );
                break;
                case 'multi_file':
                    $nkey = $key.'_file';
                    $valArray = explode("\n", $value);
                    foreach ($valArray as $i => $val) {
                        if($val){
                            $pi   = pathinfo($val);
                            $values[$i]   = array(
                                'name' => $pi['filename'],
                                'ext'  => $pi['extension'],
                                'dir'  => $pi['dirname'],
                                'url'  => filesApp::get_url($pi['filename'],'download')
                            );
                        }
                    }
                break;
                case 'category':
                    if($key=='cid'){
                        continue;
                    }
                    $nkey     = $key.'_category';
                    $category = iCache::get(categoryApp::CACHE_CATEGORY_ID.$value);
                    $values   = categoryApp::get_lite($category);
                break;
                case 'multi_category':
                    $nkey   = $key.'_category';
                    $valArray = explode(",", $value);
                    foreach ($valArray as $i => $val) {
                        $category   = iCache::get(categoryApp::CACHE_CATEGORY_ID.$val);
                        $values[$i] = categoryApp::get_lite($category);
                    }
                break;
                case 'userid':
                    $nkey   = $key.'_user';
                    if ($rs['postype']) {
                        // $values = user::empty_info($value);
                    } else {
                        $values = user::info($value);
                    }
                break;
                case 'multi_prop':
                case 'prop':
                    if($key=='pid'){
                        continue;
                    }
                    $nkey   = $key.'_prop';
                    $values['prop'] = propApp::value($this->app,$key);
                    $valArray = explode(",", $value);
                    if($values['prop'])foreach ($values['prop'] as $i => $val) {
                        if(in_array($val['val'], $valArray)){
                            $values['value'][$val['val']] = $val;
                        }
                    }
                break;
                default:
                    // $values = $value;
                break;
            }
            if($field['option']){
                $nkey = $key.'_array';
                $optionArray = explode(";", $field['option']);
                $valArray = explode(",", $value);
                foreach ($optionArray as $ok => $val) {
                    $val = trim($val,"\r\n");
                    if($val){
                        list($opt_text,$opt_value) = explode("=", $val);
                        $values['option'][$opt_value] = $opt_text;
                        if(in_array($opt_value, $valArray)){
                            $values['value'][$opt_value] = $opt_text;
                        }
                    }
                }
            }
            $nkey && $rs[$nkey] = $values;
        }
        return $rs;
    }

}