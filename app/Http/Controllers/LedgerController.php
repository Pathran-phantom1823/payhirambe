<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Increment\Finance\Models\Ledger;
use Carbon\Carbon;
class LedgerController extends APIController
{
    function __construct(){
      if($this->checkAuthenticatedUser() == false){
        return $this->response();
      }
      $this->localization();
      $this->model = new Ledger();
    }
    public $notificationClass = 'Increment\Common\Notification\Http\NotificationController';
    public $depositClass = 'App\Http\Controllers\DepositController';
    public $withdrawalClass = 'App\Http\Controllers\WithdrawController';
    // public $installmentRequestClass = 'Increment\Imarket\Installment\Http\InstallmentRequestController';
    // public $rentalClass = 'Increment\Imarket\Rental\Http\RentalController';
    public $requestClass = 'App\Http\Controllers\RequestMoneyController';
    public function dashboard($accountId){
      return array(
        'ledger' => $this->retrievePersonal($accountId),
        'available' => $this->available(),
        'approved' => app('App\Http\Controllers\InvestmentController')->approved(),
        'total_requests' => app('App\Http\Controllers\RequestMoneyController')->total(),
        'personal_total_requests' => app('App\Http\Controllers\RequestMoneyController')->getTotalActiveRequest($accountId),
        'request_status' => app('App\Http\Controllers\RequestMoneyController')->requestStatus($accountId),
        'withdrawal'  => app($this->withdrawalClass)->getByParams('account_id', $accountId),
        // 'installment_request'  => app($this->installmentRequestClass)->getByParams('account_id', $accountId),
        // 'rental_request'  => app($this->rentalClass)->getByParams('account_id', $accountId),
        'currency' => 'PHP'
      );
    }

    public function summary(Request $request){
      $data = $request->all();                                                                  
      $result = Ledger::where('account_id', '=', $data['account_id'])->where($data['column'], 'like', $data['value'])->limit(intval($data['limit']))->offset(intval($data['offset']))->orderBy($data['sort']['column'], $data['sort']['value'])->get();
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y h:i A');
          $result[$i]['investments'] = null;
          if($result[$i]['payload'] == 'investments'){
            $result[$i]['investments'] = app('App\Http\Controllers\InvestmentController')->retrieveById($result[$i]['payload_value']);
          }else{
            //
          }
          $i++;
        }
      }
      return response()->json(array(
        'data' => sizeof($result) > 0 ? $result : null,
        'ledger' => $this->dashboard($data['account_id'])
      ));
    }

    public function retrievePersonal($accountId){
      $ledger = Ledger::where('account_id', '=', $accountId)->sum('amount');
      // subtract total pending withdrawal
      // substract total pending requests
      $totalWithdrawal = app($this->withdrawalClass)->getTotalSumByParams('account_id', $accountId);
      $totalRequest = app($this->requestClass)->getTotalRequest($accountId);
      $total = doubleval($ledger) - $totalWithdrawal - $totalRequest;
      return doubleval($total);
    }

    public function createOnDeposit(Request $request){
      $data = $request->all();
      $ledger = new Ledger();
      $code = $this->generateCode();
      $ledger->code = $code;
      $ledger->account_id = $data['account_id'];
      $ledger->amount = $data['amount'];
      $ledger->currency = $data['currency'];
      $ledger->description = 'Deposit';
      $ledger->payload = 'deposit';
      $ledger->payload_value = $data['code'];
      $ledger->created_at = Carbon::now();
      $ledger->save();

      if($ledger->id){
        app($this->depositClass)->updateByParams('id', $data['id']); 

        $description = 'Your deposit transaction was successfully posted with the amount of '. $data['currency'].' '.$data['amount'];

        $details = array(
          'title' => $description,
          'transaction_id' => $code
        );

        $subject = 'Deposit payment confirmation';
        app('App\Http\Controllers\EmailController')->ledger($data['account_id'], $details, $subject);  

        $notification = array(
          'to'    => $data['account_id'],
          'from'  => $data['from'],
          'payload' => 'ledger',
          'payload_value' => $code,
          'route' => '/dashboard',
          'created_at' => Carbon::now()
        );
        app($this->notificationClass)->createByParams($notification);
      }
      $this->response['data'] = $ledger->id;
      return $this->response();
    }

    public function createOnWithdrawal(Request $request){
      // check if the account is sufficient
      $data = $request->all();
      $total = $this->retrievePersonal($data['account_id']);
      if(doubleval($total) <= 0){
        $this->response['data'] = null;
        $this->response['error'] = 'Insufficient Balance';
        return $this->response();
      }

      // credit to ledger of the requestor with charge

      $totalAmount = (doubleval($data['amount']) + doubleval($data['charge'])) * -1;
      $creditLedger = new Ledger();
      $code = $this->generateCode();
      $creditLedger->code = $code;
      $creditLedger->account_id = $data['account_id'];
      $creditLedger->amount = $totalAmount;
      $creditLedger->currency = $data['currency'];
      $creditLedger->description = 'Withdrawal via '.$data['bank'].' using the ff. account:'.$data['account_name'].'/'.$data['account_number'];
      $creditLedger->payload = 'withdrawal';
      $creditLedger->payload_value = $data['code'];
      $creditLedger->created_at = Carbon::now();
      $creditLedger->save();

      if($creditLedger->id){
        // update withdrawal to completed
        app($this->withdrawalClass)->updateByParams('id', $data['id']);

        $description = 'Your account was credited with the amount of '. $data['currency'].' '.$totalAmount.' for withdrawal transaction via '.$data['bank'].' using the ff. account:'.$data['account_name'].'/'.$data['account_number'];

        $details = array(
          'title' => $description,
          'transaction_id' => $code
        );

        $subject = 'Withdrawal via '.$data['bank'];
        // send email
        app('App\Http\Controllers\EmailController')->ledger($data['account_id'], $details, $subject);  


        // send notifications
        $notification = array(
          'to'    => $data['account_id'],
          'from'  => $data['from'],
          'payload' => 'ledger',
          'payload_value' => $code,
          'route' => '/dashboard',
          'created_at' => Carbon::now()
        );
        app($this->notificationClass)->createByParams($notification);
      }

      // debit to the payhiram account

      $debitLedger = new Ledger();
      $code = $this->generateCode();
      $debitLedger->code = $code;
      $debitLedger->account_id = env('PAYHIRAM_ACCOUNT');
      $debitLedger->amount = ($totalAmount * -1);
      $debitLedger->currency = $data['currency'];
      $debitLedger->description = 'Debit from withdrawal via '.$data['bank'].' using the ff. account:'.$data['account_name'].'/'.$data['account_number'];
      $debitLedger->payload = 'withdrawal';
      $debitLedger->payload_value = $data['code'];
      $debitLedger->created_at = Carbon::now();
      $debitLedger->save();

      if($debitLedger->id){

        $description = 'Your account was debited with the amount of '. $data['currency'].' '.$totalAmount.' from withdrawal transaction via '.$data['bank'].' using the ff. account:'.$data['account_name'].'/'.$data['account_number'];

        $details = array(
          'title' => $description,
          'transaction_id' => $code
        );

        $subject = 'Debit withdrawal via '.$data['bank'];
        // send email
        app('App\Http\Controllers\EmailController')->ledger(env('PAYHIRAM_ACCOUNT'), $details, $subject);  


        // send notifications
        $notification = array(
          'to'    => env('PAYHIRAM_ACCOUNT'),
          'from'  => $data['from'],
          'payload' => 'ledger',
          'payload_value' => $code,
          'route' => '/dashboard',
          'created_at' => Carbon::now()
        );
        app($this->notificationClass)->createByParams($notification);
      }
      $this->response['data'] = $debitLedger->id;
      return $this->response();
    }

    public function addToLedger($accountId, $amount, $description, $payload, $payloadValue, $to){
      $ledger = new Ledger();
      $code = $this->generateCode();
      $ledger->code = $code;
      $ledger->account_id = $accountId;
      $ledger->amount = $amount;
      $ledger->description = $description;
      $ledger->payload = $payload;
      $ledger->payload_value = $payloadValue;
      $ledger->created_at = Carbon::now();
      $ledger->save();

 //     sent email
      $details = array(
        'title' => $description.'PHP '.number_format(($amount * (-1)), 2).$to,
        'transaction_id' => $code
      );

      $subject = 'You made an investment to the borrower';
      app('App\Http\Controllers\EmailController')->ledger($accountId, $details, $subject);  

      return $ledger->id;
    }

    public function processPayment($data, $email, $notification){
      $code = $this->generateCode();
      $ledger = new Ledger();
      $ledger->code = $code;
      $ledger->account_id = $data['account_id'];
      $ledger->amount = $data['amount'];
      $ledger->description = $data['description'];
      $ledger->currency = $data['currency'];
      $ledger->payload = $data['payload'];
      $ledger->payload_value = $data['payload_value'];
      $ledger->created_at = Carbon::now();
      $ledger->save();
      // $details = array(
      //   'title' => $data['description'].$data['currency'].' '.number_format(($data['amount'] * (-1)), 2).$email['to'],
      //   'transaction_id' => $code
      // );
      // app('App\Http\Controllers\EmailController')->ledger($data['account_id'], $details, $email['subject']);  
      
      // app($this->notificationClass)->createByParams($notification);
      return $ledger->id;
    }
    
    public function generateCode(){
      $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 32);
      $codeExist = Ledger::where('code', '=', $code)->get();
      if(sizeof($codeExist) > 0){
        $this->generateCode();
      }else{
        return $code;
      }
    }

    public function available(){
      $result = Ledger::sum('amount');
      return doubleval($result);
    }
}
