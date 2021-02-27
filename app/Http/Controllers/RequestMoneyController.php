<?php

namespace App\Http\Controllers;
use Increment\Account\Models\Account;
use Illuminate\Http\Request;
use App\RequestMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Jobs\Notifications;
class RequestMoneyController extends APIController
{

		public $ratingClass = 'Increment\Common\Rating\Http\RatingController';
    public $comakerClass = 'App\Http\Controllers\ComakerController'; 
    public $investmentClass = 'App\Http\Controllers\InvestmentController';
    public $notificationClass = 'Increment\Common\Notification\Http\NotificationController';
    public $penaltyClass = 'App\Http\Controllers\PenaltyController';
    public $pullingClass = 'App\Http\Controllers\PullingController';
    public $bookmarkClass = 'App\Http\Controllers\BookmarkController';
    public $requestLocationClass = 'App\Http\Controllers\RequestLocationController';
    public $requestImageClass = 'App\Http\Controllers\RequestImageController';
    public $requestPeerClass = 'App\Http\Controllers\RequestPeerController';
    public $ledgerClass = 'Increment\Finance\Http\LedgerController';
    public $messengerGroupClass = 'App\Http\Controllers\MessengerGroupController';
    public $accountClass = 'Increment\Account\Http\AccountController';
    // public $couponAccountClass = 'App\Http\Controllers\CouponAccountController';
    public $requestData = null;
    public $chargeData = null;
    function __construct(){
      if($this->checkAuthenticatedUser() == false){
        return $this->response();
      }
      $this->localization();
    	$this->model = new RequestMoney();
      $this->notRequired = array(
        'approved_date', 'months_payable', 'interest', 'reason', 'billing_per_month', 'max_charge', 'attachment_payload', 'attachment_value', 'location_id'
      );
    }

    public function create(Request $request){
    	$data = $request->all();
    	$data['code'] = $this->generateCode();
      $data['status'] = 0;
    	$this->model = new RequestMoney();
    	$this->insertDB($data);
      if(intval($data['type']) > 100){
        // comaker
        // images
        $getID = RequestMoney::where('code', '=', $data['code'])->get();
        $userExist = Account::where('email', '=', $data['comaker'])->get();
        if(sizeof($userExist) > 0){
          $comaker = $userExist[0]->id;
          app($this->comakerClass)->addToComaker($data['account_id'], $getID[0]->id, $comaker);
          $requestMoney = RequestMoney::where('id', '=', $this->response['data'])->get();
          $parameter = array(
            'to' => $comaker,
            'from' => $data['account_id'],
            'payload' => 'comaker',
            'payload_value' => $getID[0]->id,
            'route' => '/requests/'.$requestMoney[0]['code'],
            'created_at' => Carbon::now()
          );
          app($this->notificationClass)->createByParams($parameter);
        }
        if(isset($data['images'])){
          app($this->requestImageClass)->insert($data['images'], $this->response['data']);
        }
      }
      if($this->response['data']){
        $result = $this->getByParams('id', $this->response['data']);
        $result = $this->getRequestDetailOnCreate($result);
        $this->response['data'] = $result;
      }
    	return $this->response();
    }

    public function manageRequestByThread(Request $request){
      $data = $request->all();
      $error = null;
      $responseData = null;
      $result = RequestMoney::where('code', '=', $data['code'])->where('status', '=', 1)->get();
      if(sizeof($result) > 0){
        $result = $result[0];
        // get approved peer

        $result['account'] = $this->retrieveAccountDetailsOnRequests($result['account_id']);
        $peerApproved = app($this->requestPeerClass)->getApprovedByParamsPeersOnly('request_id', $result['id']);
        if($peerApproved != null){
          // check discount coupons
          // $coupon = app($this->couponAccountClass)->getByAccountIdAndPayload($result['account_id'], 'request', $result['id']);
          $coupon = null;
          if($coupon != null){
            if($coupon['type'] == 'percentage'){
              $peerApproved['charge'] = $peerApproved['charge'] * (1 - (intval($coupon['amount']) / 100));
            }else{
              $peerApproved['charge'] = $peerApproved['charge'] - intval($coupon['amount']);
            }
          }
          if($result['account']['code'] != $data['account_code']){
            return response()->json(array(
              'error' => 'Invalid accessed!',
              'data' => null,
              'timestamps' => Carbon::now()
            ));
          }
          $this->requestData = $result;
          $this->chargeData = $peerApproved;
          $response = $this->processPaymentByType();
          if($response['data'] != null){
            // update status of the requet
            RequestMoney::where('code', '=', $data['code'])->update(array(
              'status' => 2,
              'updated_at' => Carbon::now()
            ));
            // app($this->messengerGroupClass)->broadcastByParams($data['messenger_group_id'], $data['account_id']);
            $responseData = true;
          }else{
            $error = $response['error'];
          }
        }else{
          $error = 'No peer was selected! Invalid accessed';
        }
      }else{
        $error = 'Invalid accessed!';
      }

      return response()->json(array(
        'error' => $error,
        'data' => $responseData,
        'timestamps' => Carbon::now()
      ));
    }

    public function generateCode(){
      $code = 'req_'.substr(str_shuffle($this->codeSource), 0, 60);
      $codeExist = RequestMoney::where('code', '=', $code)->get();
      if(sizeof($codeExist) > 0){
        $this->generateCode();
      }else{
        return $code;
      }
    }

    public function getTypeDescription($type){
      switch ($type) {
        case 1:
          return 'Send';
        case 2:
          return 'Withdrawal';
        case 3:
          return 'Deposit';
        case 4:
          return 'Bills and Paymets';
        case 5:
          return 'Others';
      }
    }
    public function processPaymentByType(){
      $account = app($this->accountClass)->getAccountIdByParamsWithColumns(env('PAYHIRAM_ACCOUNT'), ['id', 'code']);
      
      if($this->requestData == null || $this->chargeData == null || $this->requestData['account'] == null){
        return array(
          'error' => 'Insufficient Information',
          'data'  => null
        );
      }
      $chargeAccount = $this->retrieveAccountDetailsOnRequests($this->chargeData['account_id']);

      if($account == null){
        return array(
          'error' => 'Please contact the system administrator',
          'data'  => null
        );       
      }

      if($chargeAccount == null){
        return array(
          'error' => 'Insufficient Information',
          'data'  => null
        );    
      }

      $type = intval($this->requestData['type']);

      $description = $this->getTypeDescription($type). ' request: Fund Transfer';
      $amount = floatval($this->requestData['amount']);
      $currency = $this->requestData['currency'];

      // Credit request amount from requestor
      $data = array(
        'account_id'  => $this->requestData['account_id'],
        'account_code' => $this->requestData['account']['code'],
        'description' => $description,
        'amount'      => $amount * -1,
        'currency'    => $currency,
        'payment_payload'  => 'request',
        'payment_payload_value' => $this->requestData['code'],
        'request_id' => $this->requestData['id'],
        'from'        => $account['id']
      );

      $result = app($this->ledgerClass)->addNewEntry($data);
      // debit to processor
      $charge = floatval($this->chargeData['charge']);
      $netCharge = $charge * env('CHARGE_RATE_PAYHIRAM');
      $total = $amount - $netCharge;
      // Credit request amount from requestor
      $data = array(
        'account_id'  => $this->chargeData['account_id'],
        'account_code' => $chargeAccount['code'],
        'description' => 'Processing from '.$description,
        'amount'      => $total,
        'currency'    => $currency,
        'payment_payload'  => 'request',
        'payment_payload_value' => $this->requestData['code'],
        'request_id' => $this->requestData['id'],
        'from'        => $this->requestData['account_id']
      );

      $result = app($this->ledgerClass)->addNewEntry($data);

      // Credit request amount from requestor
      $data = array(
        'account_id'  => $account['id'],
        'account_code' => $account['code'],
        'description' => 'Charge share from '.$description,
        'amount'      => $netCharge,
        'currency'    => $currency,
        'payment_payload'  => 'request',
        'payment_payload_value' => $this->requestData['code'],
        'request_id' => $this->requestData['id'],
        'from'        => $account['id']
      );

      $result = app($this->ledgerClass)->addNewEntry($data);

      return array(
        'error' => null,
        'data'  => $result
      );
    }

    public function retrieveItem(Request $request){
      $data = $request->all();
      $this->retrieveDB($data);
      $result = $this->response['data'];
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          // $this->response['data'][$i]['account'] =  $this->retrieveAccountDetails($result[$i]['account_id']);
          // $this->response['data'][$i]['peers'] = app($this->requestPeerClass)->getByParams('request_id', $result[$i]['id']);
          // $this->response['data'][$i]['images'] = app($this->requestImageClass)->getByParams('request_id', $result[$i]['id']);
          // $this->response['data'][$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
          // $this->response['data'][$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
          // $this->response['data'][$i]['needed_on_human'] = Carbon::createFromFormat('Y-m-d', $result[$i]['needed_on'])->copy()->tz($this->response['timezone'])->format('F j, Y'); // should not have a time
          // $this->response['data'][$i]['total'] = $this->getTotalBorrowed($result[$i]['account_id']);
          // $this->response['data'][$i]['initial_amount'] = $result[$i]['amount'];
          // $this->response['data'][$i]['coupon'] = null;
            $invested = app($this->investmentClass)->invested($result[$i]['id']);
            $amount = floatval($result[$i]['amount']);
            $result[$i]['location'] = null; 
            $result[$i]['images'] = app($this->requestImageClass)->getByParams('request_id', $result[$i]['id']);
            $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
            $result[$i]['account'] =  $this->retrieveAccountDetailsOnRequests($result[$i]['account_id']);
            $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
            $result[$i]['needed_on_human'] = Carbon::createFromFormat('Y-m-d', $result[$i]['needed_on'])->copy()->tz($this->response['timezone'])->format('F j, Y'); // should not have a time
            $result[$i]['total'] = $this->getTotalBorrowed($result[$i]['account_id']);
            $result[$i]['initial_amount'] = $result[$i]['amount'];
            $result[$i]['amount'] = $amount - $invested['total'];
            $result[$i]['invested'] = $invested['size'];
            $result[$i]['billing_per_month_human'] = $this->billingPerMonth($result[$i]['billing_per_month']);
            $result[$i]['coupon'] = null;
            $result[$i]['peer_flag'] = app('App\Http\Controllers\RequestPeerController')->checkIfExist($result[$i]['id'], $data['account_id']);
            $result[$i]['peer'] = app($this->requestPeerClass)->getApprovedByParams('request_id', $result[$i]['id']);
          $i++;
        }
      }
      $this->response['data'] = $result;
      return $this->response();
    }

    public function retrieve(Request $request){
    	$data = $request->all();
      $result = array();
      $size = array();

      // $accountLocation = app('App\Http\Controllers\InvestorLocationController')->getByParams('account_id', $data['account_id']);
      $accountLocation = null;
      $response = array();

      if($accountLocation == null){
        if(!isset($data['route_params'])){
        $result = RequestMoney::where('status', '=', 0)->where($data['column'], 'like', $data['value'])->offset($data['offset'])->limit($data['limit'])->orderBy($data['sort']['column'], $data['sort']['value'])->get(['id','account_id', 'code', 'type', 'money_type', 'currency',
        'amount', 'interest', 'months_payable', 'reason', 'needed_on', 'billing_per_month', 'max_charge', 'attachment_payload', 'status', 'approved_date', 'created_at']);
        $size = RequestMoney::where('status', '=', 0)->where($data['column'], 'like', $data['value'])->orderBy($data['sort']['column'], $data['sort']['value'])->get();
      }else{
          $result = RequestMoney::where('status', '=', 0)->where('id', '=', $data['route_params'])->where($data['column'], 'like', $data['value'])->offset($data['offset'])->limit($data['limit'])->orderBy($data['sort']['column'], $data['sort']['value'])->get(['id','account_id', 'code', 'type', 'money_type', 'currency',
          'amount', 'interest', 'months_payable', 'reason', 'needed_on', 'billing_per_month', 'max_charge', 'attachment_payload', 'status', 'approved_date', 'created_at']);
          $size = RequestMoney::where('status', '=', 0)->where('id', '=', $data['route_params'])->where($data['column'], 'like', $data['value'])->orderBy($data['sort']['column'], $data['sort']['value'])->get();
        }
        // $result = RequestMoney::where('status', '=', 0)->where($data['column'], 'like', $data['value'])->offset($data['offset'])->limit($data['limit'])->orderBy($data['sort']['column'], $data['sort']['value'])->get();
        // dd($result);
      }else{
        if(!isset($data['route_params'])){
          $result = DB::table('locations as T1')
            ->join('requests as T2', 'T2.id', '=', 'T1.request_id')
            ->where('T2.status', '=', 0)
            ->where('T1.country', '=', $accountLocation['country'])
            // ->where('T1.region', '=', $accountLocation['region'])
            ->whereIn('T1.locality', $accountLocation['locality'])
            ->where('T2.'.$data['column'], 'like', $data['value'])
            ->orderBy('T2.'.$data['sort']['column'], $data['sort']['value'])
            ->offset($data['offset'])
            ->limit($data['limit'])
            ->select('T2.id', 'T2.code', 'T2.account_id', 'T2.type', 'T2.money_type', 'T2.currency', 'T2.amount', 'T2.interest', 'T2.month_payable', 'T2.reason', 'T2.needed_on', 'T2.billing_per_month', 'T2.max_charge'
            , 'T2.attachment_payload', 'T2.approved_date')
            ->get();
          // dd($result);
          $result = json_decode($result, true);

          $size = DB::table('locations as T1')
            ->join('requests as T2', 'T2.id', '=', 'T1.request_id')
            ->where('T2.status', '=', 0)
            ->where('T1.country', '=', $accountLocation['country'])
            // ->where('T1.region', '=', $accountLocation['region'])
            ->whereIn('T1.locality', $accountLocation['locality'])
            ->where('T2.'.$data['column'], 'like', $data['value'])
            ->orderBy('T2.'.$data['sort']['column'], $data['sort']['value'])
            ->select('T2.*')
            ->get();
        }else{
          $result = DB::table('locations as T1')
            ->join('requests as T2', 'T2.id', '=', 'T1.request_id')
            ->where('T2.id', '=', $data['route_params'])
            ->where('T2.status', '=', 0)
            ->where('T1.country', '=', $accountLocation['country'])
            // ->where('T1.region', '=', $accountLocation['region'])
            ->whereIn('T1.locality', $accountLocation['locality'])
            ->where('T2.'.$data['column'], 'like', $data['value'])
            ->orderBy('T2.'.$data['sort']['column'], $data['sort']['value'])
            ->offset($data['offset'])
            ->limit($data['limit'])
            ->select('T2.id', 'T2.code', 'T2.account_id', 'T2.type', 'T2.money_type', 'T2.currency', 'T2.amount', 'T2.interest', 'T2.month_payable', 'T2.reason', 'T2.needed_on', 'T2.billing_per_month', 'T2.max_charge'
            , 'T2.attachment_payload', 'T2.approved_date')
            ->get();
          // dd($result);
          $result = json_decode($result, true);

          $size = DB::table('locations as T1')
            ->join('requests as T2', 'T2.id', '=', 'T1.request_id')
            ->where('T2.id', '=', $data['route_params'])
            ->where('T2.status', '=', 0)
            ->where('T1.country', '=', $accountLocation['country'])
            // ->where('T1.region', '=', $accountLocation['region'])
            ->whereIn('T1.locality', $accountLocation['locality'])
            ->where('T2.'.$data['column'], 'like', $data['value'])
            ->orderBy('T2.'.$data['sort']['column'], $data['sort']['value'])
            ->select('T2.*')
            ->get();
        }
      }
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $peerApproved = app($this->requestPeerClass)->checkIfApproved($result[$i]['id']);
          if($peerApproved == false || $data['value'] == $result[$i]['code'].'%'){
            $invested = app($this->investmentClass)->invested($result[$i]['id']);
            $amount = floatval($result[$i]['amount']);
            $result[$i]['location'] = null; 
            $result[$i]['images'] = app($this->requestImageClass)->getByParams('request_id', $result[$i]['id']);
            $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
            $result[$i]['account'] =  $this->retrieveAccountDetailsOnRequests($result[$i]['account_id']);
            $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
            $result[$i]['needed_on_human'] = Carbon::createFromFormat('Y-m-d', $result[$i]['needed_on'])->copy()->tz($this->response['timezone'])->format('F j, Y'); // should not have a time
            $result[$i]['total'] = $this->getTotalBorrowed($result[$i]['account_id']);
            $result[$i]['initial_amount'] = $result[$i]['amount'];
            $result[$i]['amount'] = $amount - $invested['total'];
            $result[$i]['invested'] = $invested['size'];
            $result[$i]['billing_per_month_human'] = $this->billingPerMonth($result[$i]['billing_per_month']);
            $result[$i]['coupon'] = null;
            $result[$i]['peer_flag'] = app('App\Http\Controllers\RequestPeerController')->checkIfExist($result[$i]['id'], $data['account_id']); 
            unset($result[$i]['account_id']);
            $response[] = $result[$i];
          }
          $i++;
        }
      }
    	return response()->json(array(
        'data' => sizeof($response) > 0 ? $response : null,
        'size' => sizeof($size),
        // 'ledger' => app($this->ledgerClass)->retrievePersonal($data['account_id']),
        'ledger' => null,
        'locations' => $accountLocation
      ));
    }

    public function getRequestDetailOnCreate($result){
      if($result){
        $amount = floatval($result['amount']);
        $result['location'] = null; 
        $result['images'] = app($this->requestImageClass)->getByParams('request_id', $result['id']);
        $result['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result['account_id']);
        $result['account'] =  $this->retrieveAccountDetailsOnRequests($result['account_id']);
        $result['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
        $result['needed_on_human'] = Carbon::createFromFormat('Y-m-d', $result['needed_on'])->copy()->tz($this->response['timezone'])->format('F j, Y'); // should not have a time
        $result['total'] = $this->getTotalBorrowed($result['account_id']);
        $result['initial_amount'] = $result['amount'];
        $result['amount'] = $amount;
        $result['invested'] = 0;
        $result['billing_per_month_human'] = $this->billingPerMonth($result['billing_per_month']);
        $result['coupon'] = null;
        $result['peer_flag'] = false; 
        unset($result['account_id']);
        // push notification here
        return $result;        
      }else{
        return null;
      }
    }

    public function retrieveById($id, $type = null){
      $result = RequestMoney::where('id', '=', $id)->get();
      $result = $this->getAttributes($result, $type);
      return (sizeof($result) > 0) ? $result[0] : null;
    }

    public function getByParams($column, $value){
      $result = RequestMoney::where($column, '=', $value)->get();
      return (sizeof($result) > 0) ? $result[0] : null;
    }

    public function getAttributes($result, $type = null){
      $this->localization();
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $invested = app($this->investmentClass)->invested($result[$i]['id']);
          $result[$i]['location'] = app($this->requestLocationClass)->getByParams('request_id', $result[$i]['id']);
          $result[$i]['images'] = app($this->requestImageClass)->getByParams('request_id', $result[$i]['id']);
          $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('profile', $result[$i]['account_id']);
          $result[$i]['account'] = $this->retrieveAccountDetails($result[$i]['account_id']);
          $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
          $result[$i]['needed_on_human'] = Carbon::createFromFormat('Y-m-d', $result[$i]['needed_on'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
          $result[$i]['total'] = $this->getTotalBorrowed($result[$i]['account_id']);
          $result[$i]['invested'] = $invested['size'];
          $result[$i]['billing_per_month_human'] = $this->billingPerMonth($result[$i]['billing_per_month']);
          $i++;
        }
      }
      return $result;
    }

    public function billingPerMonth($value){
      switch (intval($value)) {
        case 0:
          return 'every end of the month.';
          break;
        case 1:
          return 'twice a month.';
          break;
        case 2: 
          return 'every end of the week';
          break;
      }
    }
    
    public function updateStatus($id, $status = 1){
      RequestMoney::where('id', '=', $id)->update(array(
        'status' => $status,
        'updated_at' => Carbon::now()
      ));
    }

    public function updateStatusByParams($column, $value, $status = 1){
      RequestMoney::where($column, '=', $value)->update(array(
        'status' => $status,
        'updated_at' => Carbon::now()
      ));
    }

    public function getAmount($requestId){
      $result = RequestMoney::where('id', '=', $requestId)->get();
      return sizeof($result) > 0 ? floatval($result[0]['amount']) : null;
    }   

    public function getTotalBorrowed($accountId){
    	$result = RequestMoney::where('account_id', '=', $accountId)->where('status', '=', 1)->sum('amount');
    	return doubleval($result);
    }

    public function getTotalRequest($accountId){
      $result = RequestMoney::where('account_id', '=', $accountId)->where('type', '<=', 2)->where('status', '!=', 2)->sum('amount');
      return doubleval($result);
    }

    public function getTotalActiveRequest($accountId, $currency){
      $result = RequestMoney::where('account_id', '=', $accountId)->where('currency', '=', $currency)->where('status', '!', 2)->sum('amount');
      return doubleval($result);
    }

    public function total(){
      $result = RequestMoney::where('status', '=', 0)->sum('amount');
      return doubleval($result);
    }

    public function approved(){
      $result = RequestMoney::where('status', '=', 1)->sum('amount');
      return doubleval($result);
    }

    public function requestStatus($accountId){
      $result = RequestMoney::where('account_id', '=', $accountId)->where('status', '=', 1)->get();
      return (sizeof($result) > 0) ? true : false;
    }

    public function payments($data){
      $result = RequestMoney::where('account_id', '=', $data['account_id'])->where('status', '=', 1)->where('approved_date', '!=', null)->get();
      $result = $this->getAttributes($result);

      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $billingDate = $this->manageNextBilling($result[$i]['approved_date'], $result[$i]['billing_per_month']);
          $result[$i]['next_billing_date_human'] = $billingDate->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
          $result[$i]['next_billing_date'] = $billingDate->copy()->tz($this->response['timezone'])->format('Y-m-d');
          $result[$i]['penalty'] = app($this->penaltyClass)->getTotalPenalty($result[$i]['request_id'], $data['account_id']); 
          $i++;
        }
      }
      return sizeof($result) > 0 ? $result : null;
    }

    public function billingSchedule(){
      $result = RequestMoney::where('status', '=', 1)->where('approved_date', '!=', null)->get();

      $result = $this->getAttributes($result);

      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $billingDate = $this->manageNextBilling($result[$i]['approved_date'], $result[$i]['billing_per_month']);
          $result[$i]['next_billing_date_human'] = $billingDate->copy()->tz('Asia/Manila')->format('F j, Y h:i A');
          $result[$i]['next_billing_date'] = $billingDate->copy()->tz('Asia/Manila')->format('Y-m-d');
          $result[$i]['send_billing_flag'] = true;
          $i++;
        }
      }
      return $result;
    }

    public function manageNextBilling($approvedDate, $billingPerMonth){
      $days = 0;
      $approvedDate = Carbon::createFromFormat('Y-m-d H:i:s', $approvedDate);
      $currentDate = Carbon::now();
      $diff = $currentDate->diffInDays($approvedDate, false);
      
        // 31, 30
      if($diff > 0){
        if($billingPerMonth == 0){
          return Carbon::createFromFormat('Y-m-d H:i:s', $approvedDate)->addMonth();
        }else if($billingPerMonth == 1){
          return Carbon::createFromFormat('Y-m-d H:i:s', $approvedDate)->addMonth()->subWeeks(2);
        }
      }else{
        if($approvedDate->month == $currentDate->month && $approvedDate->year == $currentDate->year){
          if($billingPerMonth == 0){
            return Carbon::createFromFormat('Y-m-d H:i:s', $approvedDate)->addMonth();
          }else if($billingPerMonth == 1){
            return Carbon::createFromFormat('Y-m-d H:i:s', $approvedDate)->addMonth()->subWeeks(2);
          }
        }else{
          $stringDate = $currentDate->year.'-'.$currentDate->month.'-'.$approvedDate->day;
          if($billingPerMonth == 0){
            return Carbon::createFromFormat('Y-m-d', $stringDate);
          }else if($billingPerMonth == 1){
            return Carbon::createFromFormat('Y-m-d', $stringDate)->subWeeks(2);
          }
          
        }
      }
      if($billingPerMonth == 2){
        return Carbon::now()->endOfWeek()->subDay();
      }
      return null;
    }

    public function getByParamsWithColumns($column, $value, $columns){
      $result = RequestMoney::where($column, '=', $value)->get($columns);
      return (sizeof($result) > 0) ? $result[0] : null;
    }
}
