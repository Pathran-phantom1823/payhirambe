<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Increment\Messenger\Models\MessengerGroup;
use Increment\Messenger\Models\MessengerMember;
use Increment\Messenger\Models\MessengerMessage;
use Carbon\Carbon;
use Increment\Account\Models\Account;
use Illuminate\Support\Facades\DB;
use App\Events\Message;
use App\Jobs\Notifications;
class MessengerGroupController extends APIController
{
    public $notificationClass = 'Increment\Common\Notification\Http\NotificationController';
    public $requestValidationClass = 'App\Http\Controllers\RequestValidationController';
    public $ratingClass = 'Increment\Common\Rating\Http\RatingController';
    public $requestClass = 'App\Http\Controllers\RequestMoneyController';
    public $requestPeerClass = 'App\Http\Controllers\RequestPeerController';
    public $installmentClass = 'Increment\Imarket\Installment\Http\InstallmentRequestController';
    public $rentalClass = 'Increment\Imarket\Rental\Http\RentalController';
    public $messengerMessagesClass = 'Increment\Messenger\Http\MessengerMessageController';
    function __construct(){
      if($this->checkAuthenticatedUser() == false){
        return $this->response();
      }
      $this->model = new MessengerGroup();
      $this->localization();
    }

    public function create(Request $request){
      $data = $request->all();

      $creator = intval($data['creator']);
      $memberData = intval($data['member']);
      $result = $this->getByParams('title', $data['title']);

      $this->response['error'] = null;
      if($result != null){
        $this->response['data'] = $result['id'];
        $this->response['error'] = array(
          'message' => 'Already exist!',
          'status'  => 'duplicate'
        );
        return $this->response();
      }
      
      $this->model = new MessengerGroup();
      $insertData = array(
        'account_id'  => $creator,
        'title'       => $data['title'],
        'payload'     => $data['payload'] 
      );
      $this->insertDB($insertData);
      $id = intval($this->response['data']);
      if($this->response['data'] > 0){
        $member = new MessengerMember();
        $member->messenger_group_id = $id;
        $member->account_id = $creator;
        $member->status = 'admin';
        $member->created_at = Carbon::now();
        $member->save();

        $member = new MessengerMember();
        $member->messenger_group_id = $id;
        $member->account_id = $memberData;
        $member->status = 'member';
        $member->created_at = Carbon::now();
        $member->save();

        $message = new MessengerMessage();
        $message->messenger_group_id = $id;
        $message->account_id = $creator;
        $message->payload = 'text';
        $message->payload_value = null;
        $message->message = 'Greetings!';
        $message->status = 0;
        $message->created_at = Carbon::now();
        $message->save();

        app($this->requestClass)->updateStatusByParams('code', $data['title'], 1);
        app($this->requestPeerClass)->updateStatusByParams('code', $data['title'], 'approved', $memberData);

        $parameter = array(
          'to' => $memberData,
          'from' => $creator,
          'payload' => 'thread',
          'payload_value' => $id,
          'route' => '/thread/'.$data['title'],
          'created_at' => Carbon::now()
        );
        app($this->notificationClass)->createByParams($parameter);
      }
      return $this->response();
    }

    public function retrieve(Request $request){
      $data = $request->all();
      $accountId = $data['account_id'];
      $existed = array();
      $flag = false;
      $active = 0;
      $response = array();
      $result = DB::table('messenger_members as T1')
        ->join('messenger_groups as T2', 'T2.id', '=', 'T1.messenger_group_id')
        ->where('T1.account_id', '=', $accountId)
        ->where('T2.payload', '!=', 'support')
        ->orderBy('T2.updated_at', 'DESC')
        ->select('T2.*')
        ->get();
      $result = json_decode($result, true);
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $result[$i] = $this->manageResult($result[$i]);
          $i++;
        }
      }
      $accounts = null;
      return response()->json(array(
        'data'  => (sizeof($result) > 0) ? $result : null,
        'accounts'  => $accounts,
        'active'  => $active,
        'error' => null,
        'timestamps'  => Carbon::now()
      ));
    }

    public function retrieveByParams(Request $request){
      $data = $request->all();
      $this->model = new MessengerGroup();
      $this->retrieveDB($data);
      $result = $this->response['data'];
      if(sizeof($result) > 0){
        $this->response['data'] = $this->manageResult($result[0], $data['account_id'], $result[0]['title']);
      }else{
        $this->response['data'] = null;
      }
      return $this->response();
    }

    public function broadcastByParams($id, $accountId, $update = null){
      $result = MessengerGroup::where('id', '=', $id)->get();
      $messengerGroup = null;
      if(sizeof($result) > 0){
        $result = $result[0];
        $messengerGroup = $result;
        $request = app($this->requestClass)->getByParams('code', $result['payload']);
        $messengerGroup['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result['updated_at'] != null ?  $result['updated_at'] : $result['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
        $messengerGroup['validations'] = app($this->requestValidationClass)->getByParams('request_id', $result['payload'], $request['type']);
        $messengerGroup['rating'] = app($this->ratingClass)->getByParams($accountId, 'request', $result['payload']);
        $messengerGroup['message_update'] = $update;
        Notifications::dispatch('message_group', $messengerGroup->toArray());
      }else{
        $messengerGroup = null;
      }
    }

    public function getByParamsTwoColumns($column1, $value1, $column2, $value2){
      $result = MessengerGroup::where($column1, '=', $value1)->where($column2, '=', $value2)->get();
      return sizeof($result) > 0 ? $result[0] : null;
    }

    public function manageResult($result){
      // dd($result);
      $request = app('App\Http\Controllers\RequestMoneyController')->getByParamsWithColumns('code', $result['title'], ['amount', 'currency', 'status', 'type']);
      $result['amount']   = $request ? $request['amount'] : null;
      $result['currency'] = $request ? $request['currency'] : null;
      $result['status'] = $request ? $request['status'] : null;
      $result['type'] = $request ? $request['type'] : null;
      unset($result['updated_at']);
      unset($result['created_at']);
      unset($result['deleted_at']);
      // dd($result);
      return $result;
    }

    public function getMembers($messengerGroupId, $username){
      $result = MessengerMember::where('messenger_group_id', '=', $messengerGroupId)->get();
      $flag = false;
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $account = $this->retrieveAccountDetails($result[$i]['account_id'])->only(['username', 'profile']);
          // $account = $this->retrieveUserInfoLimited($result[$i]['account_id']);
          // dd($account);
          $result[$i]['account_details'] = $account;
          if($account['username'] == $username){
            $flag = true;
          }
          $i++;
        }
      }
      return (sizeof($result) > 0) ? array(
        'result' => $result,
        'exist_username' => $flag
      ) : null;
    }


    public function getMemberExisted($messengerGroupId){
      $result = MessengerMember::where('messenger_group_id', '=', $messengerGroupId)->where('status', '=', 'member')->get();
      return (sizeof($result) > 0) ? $result[0]['account_id'] : null;
    }

    public function getTitle($messengerGroupId){
      $result = MessengerMember::where('messenger_group_id', '=', $messengerGroupId)->where('status', '=', 'member')->get();
      $title = null;
      if(sizeof($result) > 0){
          $title = $this->retrieveAccountDetails($result[0]['account_id']);
      }
      return ($title) ? $title : null;
    }

    public function getByParams($column, $value){
      $result = MessengerGroup::where($column, '=', $value)->get();
      return sizeof($result) > 0 ? $result[0] : null;
    }

    public function getPartner($username){
      $accounts = null;
      $accounts = Account::where('username', '=', $username)->where('account_type', '=', 'PARTNER')->get();
      if(sizeof($accounts) > 0){
        $i = 0;
        foreach ($accounts as $key) {
          $accounts[$i]['title'] = $this->retrieveAccountDetails($accounts[$i]['id']);
          $accounts[$i]['flag'] = true;
          $accounts[$i]['new'] = true;
          $i++;
        }
      }
      return (sizeof($accounts) > 0) ? $accounts : null;
    }

}
