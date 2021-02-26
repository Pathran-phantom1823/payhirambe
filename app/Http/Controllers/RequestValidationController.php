<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\RequestValidation;
class RequestValidationController extends APIController
{  

  public $messengerGroupClass = 'App\Http\Controllers\MessengerGroupController';
  function __construct(){
    $this->model = new RequestValidation();
  }

  public function getByParams($column, $value, $type = 1){
    $requirements = array();
    if(intval($type) == 1){
      $requirements = array(
        array(
          'payload' => 'id',
          'title'   => 'Receiver\'s ID',
          'validations' => null
        ),
        array(
          'payload' => 'photo',
          'title'   => 'Receiver\'s Picture',
          'validations' => null
        ),
        array(
          'payload' => 'signature',
          'title'   => 'Receiver\'s Signature',
          'validations' => null
        )
      );
    }else if(intval($type) == 4){
      $requirements = array(
        array(
          'payload' => 'photo',
          'title'   => 'Picture',
          'validations' => null
        )
      );
    }
    $i = 0;
    $flag = true;
    $transferStatus = 'approved';
    $initialFlag = false;
    $validationStatus = 0;
    foreach ($requirements as $key) {
      $validations = RequestValidation::where($column, '=', $value)->where('payload', '=', $key['payload'])->get();
      $requirements[$i]['validations'] = sizeof($validations) > 0 ? $validations[0] : null;

      if($initialFlag == false && sizeof($validations) > 0){
        $initialFlag = true;
      }
      
      if($flag == true && $requirements[$i]['validations'] == null){
        $flag = false;
      }
      if(sizeof($validations) > 0){
        $validationStatus++;
      }
      if($transferStatus == 'approved' && sizeof($validations) > 0 && $validations[0]['status'] != 'approved'){
        $transferStatus = $validations[0]['status'];
      }
      $i++;
    }
    
    return array(
      'complete_status' => $flag,
      'requirements' => $requirements,
      'validation_status' => $validationStatus,
      'transfer_status' => ($initialFlag == true) ? $transferStatus : 'initial'
    );
  }

  public function getDetailsByParams($column, $value){
    $result = RequestValidation::where($column, '=', $value)->get();
    return (sizeof($result) > 0) ? $result[0] : null;
  }

  public function create(Request $request){
    $data = $request->all();
    $insertData = array(
      'account_id'  => $data['account_id'],
      'payload'     => $data['payload'],
      'request_id'  => $data['request_id'],
      'status'      => $data['status']
    );
    $this->insertDB($insertData);
    // app($this->messengerGroupClass)->broadcastByParams($data['messages']['messenger_group_id'], $data['messages']['account_id'], false);
    return $this->response();
  }

  public function update(Request $request){
    $data = $request->all();
    $updateData = array(
      'id'      => $data['id'],
      'status'  => $data['status']
    );
    $this->updateDB($updateData);
    app($this->messengerGroupClass)->broadcastByParams($data['messages']['messenger_group_id'], $data['messages']['account_id'], true);
    return $this->response();
  }
}
