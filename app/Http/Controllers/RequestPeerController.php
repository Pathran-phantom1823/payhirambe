<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\RequestPeer;
use Carbon\Carbon;
use App\Jobs\Notifications;
class RequestPeerController extends APIController
{
  public $notificationClass = 'Increment\Common\Notification\Http\NotificationController';
  public $ratingClass = 'Increment\Common\Rating\Http\RatingController';
  public $requestClass = 'App\Http\Controllers\RequestMoneyController';
    
  function __construct(){
    $this->model = new RequestPeer();
  }

  public function create(Request $request){
    $data = $request->all();
    if($this->checkIfExist($data['request_id'], $data['account_id']) == true){
      $this->response['data'] = null;
      $this->response['error'] = 'Already exist!';
      return $this->response();
    }
    if($this->checkIfApproved($data['request_id']) == true){
      $this->response['data'] = null;
      $this->response['error'] = 'Request already approved!';
      return $this->response();
    }
    $data['code'] = $this->generateCode();
    $this->model = new RequestPeer();
    $this->insertDB($data);
    if($this->response['data'] > 0){
      // notifications
      $this->response['error'] = null;
      $requestData = app($this->requestClass)->getByParams('id', $data['request_id']);
      $accountId = $this->retriveAccountIdByCode($data['to']);
      $data['message'] = "There's new processing proposal to your request";
      $data['title'] = 'New peer request';
      $data['account_id'] = $accountId;
      // app($this->notificationClass)->createByParamsOnFirebase($parameter);
      Notifications::dispatch('PeerRequest', $parameter);
    }
    return $this->response();
  }

  public function generateCode(){
    $code = 'per_'.substr(str_shuffle($this->codeSource), 0, 60);
    $codeExist = RequestPeer::where('code', '=', $code)->get();
    if(sizeof($codeExist) > 0){
      $this->generateCode();
    }else{
      return $code;
    }
  }

  public function checkIfExist($requestId, $accountId){
    $result = RequestPeer::where('request_id', '=', $requestId)->where('account_id', '=', $accountId)->get();
    return sizeof($result) > 0 ? true : false;
  }

  public function checkIfApproved($requestId){
    $result = RequestPeer::where('request_id', '=', $requestId)->where('status', '=', 'approved')->get();
    return sizeof($result) > 0 ? true : false;
  }

  public function getApprovedByParams($column, $value){
    $result = RequestPeer::where($column, '=', $value)->where('status', '=', 'approved')->get();
    if(sizeof($result) > 0){
      $result[0]['account'] = $this->retrieveAccountDetails($result[0]['account_id']);
    }
    return sizeof($result) > 0 ? $result[0] : null;
  }

  public function getApprovedByParamsPeersOnly($column, $value){
    $result = RequestPeer::where($column, '=', $value)->where('status', '=', 'approved')->get();
    return sizeof($result) > 0 ? $result[0] : null;
  }

  public function retrieveItem(Request $request){
    $data = $request->all();
    $result = null;
    if($data['account_code'] == $data['account_request_code']){
      $result = RequestPeer::where('request_id', '=', $data['request_id'])->get();
    }else{
      $accountId = $this->retriveAccountIdByCode($data['account_code']);
      if($accountId){
        $result = RequestPeer::where('request_id', '=', $data['request_id'])->where('account_id', '=', $accountId)->get();
      }
    }
    $i = 0;
    $status = false;
    foreach ($result as $key) {
      $result[$i]['account'] = $this->retrieveAccountDetails($key->account_id);
      $result[$i]['distance'] = '12km';
      $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
      if($status == false && $key->status == 'approved'){
        $status = true;
      }
      if($key->status == 'approved'){
        //view message thread
      }
      $i++;
    }
    $this->response['data'] = $result;
    return $this->response();
  }

  public function getByParams($column, $value){
    $result = RequestPeer::where($column, '=', $value)->get();
    $i = 0;
    $status = false;
    foreach ($result as $key) {
      $result[$i]['account'] = $this->retrieveAccountDetails($key->account_id);
      $result[$i]['distance'] = '12km';
      $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
      if($status == false && $key->status == 'approved'){
        $status = true;
      }
      if($key->status == 'approved'){
        //view message thread
      }
      $i++;
    }
    $response = array(
      'status' => $status,
      'peers' => (sizeof($result) > 0) ? $result : null
    );
    return $response;
  }
}
