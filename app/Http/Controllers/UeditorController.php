<?php
namespace App\Http\Controllers;

use Validator;
use App\Http\Controllers\Controller;
use Request;
use App\Libs\UeditorUploader;
class UeditorController extends Controller
{
    private $CONFIG;
    private $action;
    public function __construct()
    {
            $this->CONFIG =  include(config_path().'/ueditor.php');
    }
    public function index()
    {
          //$this->CONFIG =  include(config_path().'/ueditor.php'); 
      $validator = Validator::make(Request::only('action'),[
              'action'=>'required|in:config,uploadimage,uploadscraw,uploadvideo,uploadfile,listimage,listfile,catchimage',
      ]);
      if ($validator->fails()) {
        return json_encode([
            'state'=>$validator->errors()->first(),
          ]);
      }
      $this->action = Request::input('action');
      switch ($this->action) {
          case 'config':
          //print_r($this->CONFIG);
            $result =  json_encode($this->CONFIG);
            break;
          /* 上传图片 */
          case 'uploadimage':
          /* 上传涂鸦 */
          case 'uploadscrawl':
          /* 上传视频 */
          case 'uploadvideo':
          /* 上传文件 */
          case 'uploadfile':
            $result = $this->uploadfile();
            break;
          /* 列出图片 */
          case 'listimage':
            $result = $this->actionList();
            break;
          /* 列出文件 */
          case 'listfile':
            $result = $this->actionList();
            break;
          /* 抓取远程文件 */
          case 'catchimage':
            $result = $this->actionCrawler();
            break;
          default:
            break;
      }
          /* 输出结果 */
      if (isset($_GET["callback"])){
          if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
            return  htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
          }else{
            return  json_encode([
                  'state'=> 'callback参数不合法'
            ]);
          }
      }else {
            return $result;
      }
    }
    private function uploadfile(){
              /* 上传配置 */
            $base64 = "upload";
            switch ($this->action) {
                   case 'uploadimage':
                      $config = array(
                          "pathFormat" => $this->CONFIG['imagePathFormat'],
                          "maxSize" => $this->CONFIG['imageMaxSize'],
                          "allowFiles" => $this->CONFIG['imageAllowFiles']
                      );
                      $fieldName = $this->CONFIG['imageFieldName'];
                      break;
                   case 'uploadscrawl':
                      $config = array(
                          "pathFormat" => $this->CONFIG['scrawlPathFormat'],
                          "maxSize" => $this->CONFIG['scrawlMaxSize'],
                          "allowFiles" => $this->CONFIG['scrawlAllowFiles'],
                          "oriName" => "scrawl.png"
                      );
                      $fieldName = $this->CONFIG['scrawlFieldName'];
                      $base64 = "base64";
                      break;
                   case 'uploadvideo':
                      $config = array(
                          "pathFormat" => $this->CONFIG['videoPathFormat'],
                          "maxSize" => $this->CONFIG['videoMaxSize'],
                          "allowFiles" => $this->CONFIG['videoAllowFiles']
                      );
                      $fieldName = $this->CONFIG['videoFieldName'];
                      break;
                   case 'uploadfile':
                   default:
                      $config = array(
                          "pathFormat" => $this->CONFIG['filePathFormat'],
                          "maxSize" => $this->CONFIG['fileMaxSize'],
                          "allowFiles" => $this->CONFIG['fileAllowFiles']
                      );
                      $fieldName = $this->CONFIG['fileFieldName'];
                      break;
            }

              /* 生成上传实例对象并完成上传 */
            $up = new UeditorUploader($fieldName, $config, $base64);

              /**
               * 得到上传文件所对应的各个参数,数组结构
               * array(
               *     "state" => "",          //上传状态，上传成功时必须返回"SUCCESS"
               *     "url" => "",            //返回的地址
               *     "title" => "",          //新文件名
               *     "original" => "",       //原始文件名
               *     "type" => ""            //文件类型
               *     "size" => "",           //文件大小
               * )
               */

              /* 返回数据 */
              return json_encode($up->getFileInfo());
    }
    private function actionList(){
          /* 判断类型 */
          switch ($this->action) {
              /* 列出文件 */
              case 'listfile':
                  $allowFiles = $this->CONFIG['fileManagerAllowFiles'];
                  $listSize = $this->CONFIG['fileManagerListSize'];
                  $path = $this->CONFIG['fileManagerListPath'];
                  break;
              /* 列出图片 */
              case 'listimage':
              default:
                  $allowFiles = $this->CONFIG['imageManagerAllowFiles'];
                  $listSize = $this->CONFIG['imageManagerListSize'];
                  $path = $this->CONFIG['imageManagerListPath'];
          }
          $allowFiles = substr(str_replace(".", "|", implode("", $allowFiles)), 1);
          /* 获取参数 */
          $size = Request::input('size',$listSize);
          $start = Request::input('start',0);
          /*$size = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : $listSize;
          $start = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 0;*/
          $end = $start + $size;
          /* 获取文件列表 */
          $path = $_SERVER['DOCUMENT_ROOT'] . (substr($path, 0, 1) == "/" ? "":"/") . $path;
          $files = $this->getfiles($path, $allowFiles);
          if (!count($files)) {
              return json_encode(array(
                  "state" => "no match file",
                  "list" => array(),
                  "start" => $start,
                  "total" => count($files)
              ));
          }

          /* 获取指定范围的列表 */
          $len = count($files);
          for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
              $list[] = $files[$i];
          }
          //倒序
          //for ($i = $end, $list = array(); $i < $len && $i < $end; $i++){
          //    $list[] = $files[$i];
          //}

          /* 返回数据 */
          $result = json_encode(array(
              "state" => "SUCCESS",
              "list" => $list,
              "start" => $start,
              "total" => count($files)
          ));

          return $result;
    }
    private function actionCrawler()
    {

            /* 上传配置 */
            $config = array(
                "pathFormat" => $this->CONFIG['catcherPathFormat'],
                "maxSize" => $this->CONFIG['catcherMaxSize'],
                "allowFiles" => $this->CONFIG['catcherAllowFiles'],
                "oriName" => "remote.png"
            );
            $fieldName = $this->CONFIG['catcherFieldName'];
            $validator = Validator::make(Request::only($fieldName),[
              $fieldName=>'required',
            ]);
            if ($validator->fails()) {
              return json_encode([
                  'state'=>$validator->errors()->first(),
                ]);
            }
            /* 抓取远程图片 */
            $list = array();
            $source = Request::get($fieldName);
            foreach ($source as $imgUrl) {
                $item = new UeditorUploader($imgUrl, $config, "remote");
                $info = $item->getFileInfo();
                array_push($list, array(
                    "state" => $info["state"],
                    "url" => $info["url"],
                    "size" => $info["size"],
                    "title" => htmlspecialchars($info["title"]),
                    "original" => htmlspecialchars($info["original"]),
                    "source" => htmlspecialchars($imgUrl)
                ));
            }
            /* 返回抓取数据 */
            return json_encode(array(
                'state'=> count($list) ? 'SUCCESS':'ERROR',
                'list'=> $list
            ));
    }
    private function  getfiles($path, $allowFiles, &$files = array())
    {
        if (!is_dir($path)) return null;
        if(substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(".$allowFiles.")$/i", $file)) {
                        $files[] = array(
                            'url'=> substr($path2, strlen($_SERVER['DOCUMENT_ROOT'])),
                            'mtime'=> filemtime($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }
  }
