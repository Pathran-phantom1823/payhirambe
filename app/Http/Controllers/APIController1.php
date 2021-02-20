<?php

namespace App\Http\Controllers;

use Storage;
use Illuminate\Http\Request;
use Illuminate\Http\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuthExceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Carbon\Carbon;
use App\UserAuth;
use Increment\Account\Models\Account;
use App\LoginLogger;
use App\Jobs\Email;

class APIController extends Controller
{
  /*
    Author: Kennette Canales
    Version: 1.0
    Company: Payhiram
    Website: www.payhiram.ph
  */
  protected $model = NULL;
  protected $foreignTable = [];
  protected $editableForeignTable = array();
  protected $requiredForeignTable = array();
  protected $codeSource = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  protected $response = array(
      "data" => null,
      "error" => array(),// {status, message}
      "debug" => null,
      "request_timestamp" => 0,
      "timezone" => 'Asia/Manila'
  );

  protected $whiteListedDomain = array(
    'https://payhiram.ph/',
    'https://www.payhiram.ph/',
    'http://www.payhiram.ph/',
    'http://payhiram.ph/',
    'http://localhost:8001/',
    'com.payhiram'
  );

  protected $whiteListedDomainOrigin = array(
    'https://payhiram.ph',
    'https://www.payhiram.ph',
    'http://www.payhiram.ph',
    'http://payhiram.ph',
    'com.payhiram',
    'http://localhost:8001'
  );

  protected $notRequired = array();
  protected $responseType = 'json'; 
  protected $rawRequest = null;
  protected $singleImageFileUpload = array();
  protected $validation = array();

  protected $installment = true;

  public function checkAuthenticatedUser($flag = false)
  {
    // if(env('TEST') == false){
    //   if($flag == true){
    //     if(isset($_SERVER['HTTP_REFERER']) && !in_array($_SERVER['HTTP_REFERER'], $this->whiteListedDomain)){
    //       $this->response['error'] = array(
    //         'message' => 'Invalid Domain!',
    //         'status'  => 404
    //       );
    //       return false;
    //     }
    //     if(isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $this->whiteListedDomainOrigin)){
    //       $this->response['error'] = array(
    //         'message' => 'Invalid Domain!',
    //         'status'  => 404
    //       );
    //       return false;
    //     }
    //     return true;
    //   }
    //   if(isset($_SERVER['HTTP_REFERER']) && !in_array($_SERVER['HTTP_REFERER'], $this->whiteListedDomain)){
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Domain!',
    //       'status'  => 404
    //     );
    //     return false;
    //   }
    //   if(isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $this->whiteListedDomainOrigin)){
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Domain!',
    //       'status'  => 404
    //     );
    //     return false;
    //   }
    //   try {
    //     $user = JWTAuth::parseToken()->authenticate();
    //     return true;
    //   } catch (TokenExpiredException $e) {
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Credentials',
    //       'status'  => $e->getStatusCode()
    //     );
    //     return false;
    //   } catch (TokenInvalidException $e) {
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Credentials',
    //       'status'  => $e->getStatusCode()
    //     );
    //     return false;
    //   }      
    // }else{
    //   if(isset($_SERVER['HTTP_REFERER']) && !in_array($_SERVER['HTTP_REFERER'], $this->whiteListedDomain)){
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Domain!',
    //       'status'  => 404
    //     );
    //     return false;
    //   }
    //   if(isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $this->whiteListedDomainOrigin)){
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Domain!',
    //       'status'  => 404
    //     );
    //     return false;
    //   }
    //   try {
    //     $user = JWTAuth::parseToken()->authenticate();
    //     return true;
    //   } catch (TokenExpiredException $e) {
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Credentials',
    //       'status'  => $e->getStatusCode()
    //     );
    //     return false;
    //   } catch (TokenInvalidException $e) {
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Credentials',
    //       'status'  => $e->getStatusCode()
    //     );
    //     return false;

    //   } catch (JWTException $e) {
    //     $this->response['error'] = array(
    //       'message' => 'Invalid Credentials',
    //       'status'  => $e->getStatusCode()
    //     );
    //     return false;
    //   }
    // }
    // the token is valid and we have found the user via the sub claim
    return true;
  }

  public function test()
  {
    return "Welcome to ".env('APP_NAME')." Controller!";
  }

  public function response()
  {
    $this->response["request_timestamp"] = date("Y-m-d h:i:s");
    if($this->responseType == 'array'){
      return $this->response;
    }else{
      return response()->json($this->response);
    }
  }

  public function create(Request $request)
  { 
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    $this->rawRequest = $request;
    $this->insertDB($request->all());
    return $this->response();
  }

  public function retrieve(Request $request)
  {
    $this->rawRequest = $request;
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    $this->retrieveDB($request->all());
    return $this->response();
  }

  public function update(Request $request)
  {
    $this->rawRequest = $request;
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    if ($request->hasFile('image_file')){
    }
    else{
    }
    $this->updateDB($request->all());
    return $this->response();
  }

  public function delete(Request $request)
  {
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    $this->deleteDB($request->all());
    return $this->response();
  }

  public function insertDB($request, $flag = false)
  {
    if($this->checkAuthenticatedUser($flag) == false){
      return $this->response();
    }
    $tableColumns = $this->model->getTableColumns();
    $this->tableColumns = $tableColumns;
    if(!$this->isValid($request, "create")){
      return $this->response();
    }
    unset($tableColumns[0]);
    foreach($tableColumns as $columnName){
      if(isset($request[$columnName])){
        $this->model->$columnName = $request[$columnName];
      }else if(isset($this->defaultValue[$columnName])){
        $this->model->$columnName = $this->defaultValue[$columnName];
      }
    }
    $this->model->save();
    $childID = array();
    if($this->model->id && count($this->singleImageFileUpload)){
      for($x = 0; $x < count($this->singleImageFileUpload); $x++){
        $this->uploadSingleImageFile(
          $this->model->id,
          $this->singleImageFileUpload[$x]['name'],
          $this->singleImageFileUpload[$x]['path'],
          $this->singleImageFileUpload[$x]['column']
        );
      }
    }
    if($this->model->id && $this->editableForeignTable){
      foreach($this->editableForeignTable as $childTable){
        if(isset($request[$childTable]) && $request[$childTable]){
          $child = $request[$childTable];
          if(count(array_filter(array_keys($child), 'is_string')) > 0){//associative
            if(!isset($childID[$childTable])){
              $childID[$childTable] = array();
            }
            $child[str_singular($this->model->getTable()).'_id'] = $this->model->id;
            $foreignTable = $this->model->newModel($childTable, $child);
            foreach($child as $childKey => $childValue){
              $foreignTable->$childKey = $childValue;
            }
            $result = $this->model->find($this->model->id)->$childTable()->save($foreignTable);
            $childID[$childTable][] = $result["id"];
          }else{ // list
            foreach($child as $childValue){
              if(!isset($childID[$childTable])){
                $childID[$childTable] = array();
              }
              $childValue[str_singular($this->model->getTable()).'_id'] = $this->model->id;
              $foreignTable = $this->model->newModel($childTable, $childValue);
              foreach($childValue as $childValueKey => $childValueValue){
                if($childValueValue == null || $childValueValue == "" || empty($childValueValue)){
                  $foreignTable->$childValueKey = $childValueValue;
                }
              }
              $result = $this->model->find($this->model->id)->$childTable()->save($foreignTable);
              $childID[$childTable][] = $result["id"];
            }
          }
        }
      }
      $response = $this->model->id;
      if(count($childID)){
        $childID["id"] = $this->model->id;;
        $response = $childID;
      }
      $this->response["data"] = $response;
      return $response;
    }else{
      if($this->model->id){
        $this->response["data"] = $this->model->id;
        return true;
      }else{
        $this->response["error"]["status"] = 1;
        $this->response["error"]["message"] = "Failed to create entry in database";
        return false;
      }
    }
  }
  
  public function isValid($request, $action = "create", $subTableName = false){
    unset($this->tableColumns[0]);
    array_pop($this->tableColumns);//deleted at
    array_pop($this->tableColumns);//updated at
    array_pop($this->tableColumns);//created at
    foreach($this->tableColumns as $column){
      $this->validation[$column] = (isset($this->validation[$column])) ? $this->validation[$column] : '';
      if(!in_array($column, $this->notRequired) && !isset($this->defaultValue[$column])){//requiring all field by default
        if($action !== "update"){
          $this->validation[$column] = $this->validation[$column].($this->validation[$column] ? "| ":"")."required";
        }else if($action === "update"){
          if(in_array($column, $request)){
            $this->validation[$column] = $this->validation[$column].($this->validation[$column] ? "| ":"")."required";
          }else{

            unset($this->validation[$column]);
          }
        }
      }
    }
    if($action == "update"){
      $this->validation["id"] = "required";
      if(!isset($request["id"])){
        $this->response["error"]["status"] = 102;
        $this->response["error"]["message"] = "ID required";
        return false;
      }
    }
    if(count($this->validation)){
      foreach($this->validation as $validationKey => $validationValue){
        if($action == "update"){
          if(strpos( $validationValue, "unique" ) !== false ) { //check if rule has unique
            $rules = explode("|", $this->validation[$validationKey]);
            foreach($rules as $ruleKey => $ruleValue){ //find the unique rule
              if(strpos( $ruleValue, "unique" ) !== false){
                $rules[$ruleKey] = Rule::unique(str_replace("unique:", "", $ruleValue), $validationKey)->ignore($request["id"], "id");
              }
            }
            $this->validation[$validationKey] = $rules;
          }
        }
        if(strpos( $validationKey, "_id" ) !== false){
          $table = explode(".", str_plural(str_replace("_id", "", $validationKey)));
          $table = (count($table) > 1) ? $table[1] : $table[0];
          if(strpos( $validationKey, "parent" ) !== false){
            $table = $this->model->getTable();
          }
          $this->validation[$validationKey] = $this->validation[$validationKey]."|exists:".$table.",id";
        }
      }
      $validator = Validator::make($request, $this->validation);
      if ($validator->fails()) {
        if(!$subTableName){
          $this->response["error"]["status"] = 100;
          $this->response["error"]["message"] = $validator->errors()->toArray();
        }
        return false;
      }else{
        return true;
      }
    }
  }

  public function localization(){
      $ip = null;
      if(isset($_SERVER['HTTP_CLIENT_IP'])){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
      }else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
      }else{
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
      }
      $result = @unserialize(file_get_contents('http://ip-api.com/php/'.$ip));
      if($result && $result['status'] == 'success') {
        $this->response['timezone'] = $result['timezone'];
      }
  }
  public function retrieveDB($request)
  {
      if($this->checkAuthenticatedUser() == false){
        return $this->response();
      }
      $this->localization();
      $tableName = $this->model->getTable();
      $singularTableName = str_singular($tableName);
      $tableColumns = $this->model->getTableColumns();
      if($this->requiredForeignTable){
        $this->model = $this->model->with($this->requiredForeignTable);
        for($x = 0; $x < count($this->requiredForeignTable); $x++){
          $singularForeignTable = str_singular($this->requiredForeignTable[$x]);
          $pluralForeignTable = str_plural($this->requiredForeignTable[$x]);
          $this->model = $this->model->leftJoin($pluralForeignTable, $pluralForeignTable.'.id', '=', $tableName.'.'.$singularForeignTable.'_id');
        }
      }
      if(isset($request['with_foreign_table'])){
        $foreignTable = array();
        foreach($request['with_foreign_table'] as $tempForeignTable){
          if(in_array($tempForeignTable, $this->foreignTable)){
            $foreignTable[] = $tempForeignTable;
          }
        }
        if(count($foreignTable)){
          $this->model = $this->model->with($foreignTable);
        }
      }
      if(isset($request["id"])){
         $this->model = $this->model->where($tableName.".id", "=", $request["id"]);
      }else{
        (isset($request['condition'])) ? $this->addCondition($request['condition']) : null;
        (isset($request['sort'])) ? $this->addOrderBy($request['sort']) : null;
        (isset($request['offset'])) ? $this->model->offset($request['offset']) : null;
        (isset($request['limit'])) ? $this->model = $this->model->limit($request['limit']) : null;
      }
      if(isset($request['with_soft_delete'])){
        $this->model = $this->model->withTrashed();
      }

      for($x = 0; $x < count($tableColumns); $x++){
        $tableColumns[$x] = $tableName.'.'.$tableColumns[$x];
      }
      $result = $this->model->get($tableColumns);
      if($result){
        $this->response["data"] = $result->toArray();
        if(isset($request["id"])){
          $this->response["data"] = $this->response["data"][0];
        }
      }else{
        $this->response["error"][] = [
          "status" => 200,
          "message" => "No Result"
        ];
      }
      return $result;
  }

  public function addCondition($conditions){
    /*
      column, clause, value
    */
    if($conditions){
      foreach($conditions as $condition){
        /*Table.Column, Clause, Value*/
        $condition["clause"] = (isset($condition["clause"])) ? $condition["clause"] : "=";
        $condition["value"] = (isset($condition["value"])) ? $condition["value"] : null;
        switch($condition["clause"]){
          default :
            $this->model = $this->model->where($condition["column"], $condition["clause"], $condition["value"]);
        }
      }
    }
  }
  public function addOrderBy($sort)
  {
    foreach($sort as $column => $order){
      $this->model = $this->model->orderBy($column, $order);
    }
  }



  public function updateDB($request, $noFile = false)
  {
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    $responseType = isset($request['response_type']) ? $request['response_type'] : 'json';
      $tableColumns = $this->model->getTableColumns();
      $this->tableColumns = $tableColumns;
      $tableColumns = $this->model->getTableColumns();

      $this->tableColumns = $tableColumns;
      if(!$this->isValid($request, "update")){
        return $this->response();
      }
      $this->model = $this->model->find($request["id"]);
      foreach($this->tableColumns as $columnName){
        if(isset($request[$columnName])){
          $this->model->$columnName = $request[$columnName];//setting attribute
        }else if(isset($this->defaultValue[$columnName]) && isset($request[$columnName])){
          $this->model->$columnName = $this->defaultValue[$columnName];
        }

      }
      $result = $this->model->save();
      if($result && count($this->singleImageFileUpload) && !$noFile){
        $id = $this->model->id;
        // for($x = 0; $x < count($this->singleImageFileUpload); $x++){
        //   $this->uploadSingleImageFile(
        //     $id,
        //     $this->singleImageFileUpload[$x]['name'],
        //     $this->singleImageFileUpload[$x]['path'],
        //     $this->singleImageFileUpload[$x]['column'],
        //     $this->singleImageFileUpload[$x]['replace']
        //   );
        // }
      }
      if($result && $this->editableForeignTable){
        $childID = array();
        foreach($this->editableForeignTable as $childTable => $childTableValue){
          if(is_string($childTableValue)){
            $childTable = $childTableValue;
          }
          if(isset($request[$childTable]) && $request[$childTable]){
            $child = $request[$childTable];
            if(count(array_filter(array_keys($child), 'is_string')) > 0){//associative
              if(!isset($childID[$childTable])){
                $childID[$childTable] = array();
              }
              $result = false;
              if(isset($child["id"]) && $child["id"]*1) { // update
                $pk = $child["id"];
                unset($child["id"]);
                $result = $this->model->find($this->model->id)->$childTable()->where('id', $pk)->where(str_singular($this->model->getTable()).'_id', $request["id"])->update($child);
              }else if(!isset($childTableValue['no_create_on_update'])){
                $result = $this->model->find($this->model->id)->$childTable()->create($child)->id;
              }
              $childID[$childTable] = $result;
            }else{ // list
              foreach($child as $childValue){
                if(!isset($childID[$childTable])){
                  $childID[$childTable] = array();
                }
                $result = false;
                if(isset($childValue["id"]) && $childValue["id"]*1) {//update
                  $pk = $childValue["id"];
                  unset($childValue["id"]);
                  $foreignTable = $this->model->find($this->model->id)->$childTable()
                    ->where('id', $pk)
                    ->where(str_singular($this->model->getTable()).'_id', $request["id"]);
                  foreach($childValue as $childValueKey => $childValueValue){
                    if($childValueValue == null || $childValueValue == ""){
                      $foreignTable->$childValueKey = $childValueValue;
                    }
                  }
                  $result = $foreignTable
                    ->update($childValue);
                  // $foreignTable->save($foreignTable);

                }else{ //create
                  $childValue[str_singular($this->model->getTable()).'_id'] = $this->model->id;
                  $foreignTable = $this->model->newModel($childTable, $childValue);
                  // foreach($childValue as $childValueKey => $childValueValue){
                  //   if($childValueValue == null || $childValueValue == ""){
                  //     $foreignTable->$childValueKey = $childValueValue;
                  //   }
                  // }
                  $result = $this->model->find($this->model->id)->$childTable()->save($foreignTable)->id;
                }
                $childID[$childTable][] = $result;
              }
            }
          }
          if(isset($request['deleted_foreign_table'][$childTable])){
            for($x = 0; $x < count($request['deleted_foreign_table'][$childTable]); $x++){
              $this->model->find($this->model->id)->$childTable()->where('id', $request['deleted_foreign_table'][$childTable][$x])->delete();
            }
          }
        }

        $response = $this->model->id;
        if(count($childID)){
          $childID["id"] = $response;
          $response = $childID;
        }
        $this->response["data"] = $response;
        return $response;
      }else{
        if($result){
          $this->response["data"] = $result;
        }else{
          $this->response["error"] = "Failed to update entry";
        }
      }
  }

  public function deleteDB($request)
  {
    if($this->checkAuthenticatedUser() == false){
      return $this->response();
    }
    $responseType = isset($request['response_type']) ? $request['response_type'] : 'json';
    $validator = Validator::make($request, ["id" => "required"]);
    if ($validator->fails()) {
      $this->response["error"]["status"] = 101;
      $this->response["error"]["message"] = $validator->errors()->toArray();
      return false;
    }
    $this->response["data"] = $this->model->destroy($request["id"]);
  }

  public function retrieveAccountDetails($accountId){
    $result = app('Increment\Account\Http\AccountController')->retrieveById($accountId);
    if(sizeof($result) > 0){
      $result[0]['profile'] =  app('Increment\Account\Http\AccountProfileController')->getAccountProfile($accountId);
      $result[0]['information'] = app('Increment\Account\Http\AccountInformationController')->getAccountInformation($accountId);
      $result[0]['billing'] = app('Increment\Account\Http\BillingInformationController')->getBillingInformation($accountId);
      return $result[0];
    }else{
      return null;
    }
  }

  public function retrieveAppDetails($result, $accountId){
    $result['rating'] = app('Increment\Common\Rating\Http\RatingController')->getRatingByPayload('profile', $accountId);
    $result['cards'] = app('App\Http\Controllers\AccountCardController')->getByParams('account_id', $accountId, 'ADMIN');
    $result['works'] = app('App\Http\Controllers\WorkController')->getByParams('account_id', $accountId);
    $result['guarantors'] = app('App\Http\Controllers\GuarantorController')->getByParams('sender', $accountId);
    $result['educations'] = app('App\Http\Controllers\EducationController')->getByParams('account_id', $accountId);
    return $result;
  }

  public function retrieveDetailsOnLogin($result){
    $accountId = $result['id'];
    $result['account_information_flag'] = false;
    $result['account_profile_flag'] = false;
    $result['account_information'] = app('Increment\Account\Http\AccountInformationController')->getAccountInformation($accountId);
    $result['account_profile'] = app('Increment\Account\Http\AccountProfileController')->getAccountProfile($accountId);
    $result['notification_settings'] = app('App\Http\Controllers\NotificationSettingController')->getNotificationSettings($accountId);
    $result['sub_account'] = app('Increment\Account\Http\SubAccountController')->retrieveByParams('member', $accountId);

    if($result['sub_account'] != null){
      $admin = $result['sub_account']['account_id'];
      $result['sub_account']['merchant'] = app('Increment\Imarket\Merchant\Http\MerchantController')->getByParams('account_id', $admin);
    }
    return $result;
  }


  public function retrieveNameOnly($accountId){
    $result = app('Increment\Account\Http\AccountController')->retrieveById($accountId);
    if(sizeof($result) > 0){
      $result[0]['information'] = app('Increment\Account\Http\AccountInformationController')->getAccountInformation($accountId);
      $name = null;
      if($result[0]['information'] != null && $result[0]['information']['first_name'] !== null && $result[0]['information']['last_name'] != null){
        $name = $result[0]['information']['first_name'].' '.$result[0]['information']['last_name'];
        return $name;
      }
      return $result[0]['username'];
    }else{
      return null;
    }
  }

  public function retrieveAccountDetailsProfileOnly($accountId){
    $result = app('Increment\Account\Http\AccountController')->retrieveById($accountId);
    if(sizeof($result) > 0){
      $profile =  app('Increment\Account\Http\AccountProfileController')->getAccountProfile($accountId);
      return array(
        'username'  => $result[0]['username'],
        'profile'   => $profile ? array(
          'url' =>  $profile['url']
        ) : null
      );
    }else{
      return null;
    }
  }

}
